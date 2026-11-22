<?php
defined('ABSPATH') || exit;
/** Integrates social relationships with Fresh Affiliate, preserving first-touch lifetime attribution and existing assignments. */
final class ARC_Integrations {
    public static function affiliate(int $uid) {
        if (!class_exists('Fresh_Affiliate_DB')) return false;
        $record=Fresh_Affiliate_DB::get_affiliate_by_user($uid);
        return $record && isset($record->status) && $record->status==='active' ? $record : false;
    }
    public static function affiliate_active(int $uid): bool { return (bool)self::affiliate($uid); }
    /** Distinguish a genuine active referral partner from an ordinary affiliate record. */
    public static function referral_partner_active(int $uid): bool {
        $record=self::affiliate($uid);
        return (bool)($record && !empty($record->is_partner));
    }
    /** Is this member genuinely in the current viewer's referral network?
     *  Only reads Fresh Affiliate data; never changes customer attribution.
     *  Includes first-touch customers and partner descendants, with cycle protection.
     */
    public static function in_my_network(int $viewer,int $member): bool {
        if($viewer<=0 || $member<=0 || $viewer===$member || !class_exists('Fresh_Affiliate_DB'))return false;
        $owner=self::affiliate($viewer);
        if(!$owner)return false;
        $ownerId=(int)$owner->id;
        global $wpdb;
        $customerTable=Fresh_Affiliate_DB::table('lifetime_customers');
        $customerOwner=(int)$wpdb->get_var($wpdb->prepare(
            "SELECT affiliate_id FROM $customerTable WHERE customer_user_id=%d LIMIT 1",$member));
        if($customerOwner===$ownerId)return true;
        $partner=Fresh_Affiliate_DB::get_affiliate_by_user($member);
        if(!$partner)return false;
        $parent=(int)$partner->parent_id;$visited=[];
        // Limited ancestry walk; matches descendants without exposing other networks.
        for($i=0;$i<12 && $parent>0;$i++){
            if($parent===$ownerId)return true;
            if(isset($visited[$parent]))break;
            $visited[$parent]=true;
            $record=Fresh_Affiliate_DB::get_affiliate($parent);
            if(!$record)break;
            $parent=(int)$record->parent_id;
        }
        return false;
    }
    public static function link(int $uid, string $url): string {
        $affiliate=self::affiliate($uid);
        if(!$affiliate || !class_exists('Fresh_Affiliate_Tracker')) return $url;
        if(!wp_http_validate_url($url)) return $url;
        // Only local destinations: never transfer a referral to another domain.
        if(strtolower((string)wp_parse_url($url,PHP_URL_HOST))!==strtolower((string)wp_parse_url(home_url(),PHP_URL_HOST))) return $url;
        return Fresh_Affiliate_Tracker::referral_url($affiliate,$url);
    }
    /** Called only after a new, accepted friendship, never upon a pending request. */
    public static function attribute_accepted_invite(int $inviter,int $newFriend): string {
        if($inviter<1 || $newFriend<1 || $inviter===$newFriend || !ARC_Relations::connected($inviter,$newFriend))return 'none';
        if(!class_exists('Fresh_Affiliate_DB') || !class_exists('Fresh_Affiliate_Lifetime'))return 'unavailable';
        $partner=self::affiliate($inviter);
        if(!$partner || empty($partner->is_partner))return 'inactive';
        $customer=get_userdata($newFriend);if(!$customer)return 'none';
        $existing=Fresh_Affiliate_Lifetime::get($newFriend,$customer->user_email);
        if($existing)return (int)$existing->affiliate_id===(int)$partner->id?'mine':'other';
        $result=Fresh_Affiliate_Lifetime::assign($newFriend,$customer->user_email,(int)$partner->id,'arc_friend',0);
        if(is_wp_error($result))return 'error';
        return $result && (int)$result->affiliate_id===(int)$partner->id?'mine':'other';
    }
    /** Status is exposed only to the member who is connected to this friend. */
    public static function friend_affiliate_status(int $viewer,int $friend): string {
        if(!ARC_Relations::connected($viewer,$friend) || !self::referral_partner_active($viewer))return 'not_partner';
        if(!class_exists('Fresh_Affiliate_Lifetime'))return 'unavailable';
        $person=get_userdata($friend);if(!$person)return 'none';
        $row=Fresh_Affiliate_Lifetime::get($friend,$person->user_email);
        if(!$row)return 'not_assigned';
        return self::in_my_network($viewer,$friend)?'mine':'other';
    }
    public static function status(): array {
        return ['woocommerce'=>class_exists('WooCommerce'),
            'affiliate'=>class_exists('Fresh_Affiliate_DB') && class_exists('Fresh_Affiliate_Tracker'),
            'fresh_sales'=>defined('FRESH_SALES_VERSION'),
            'advisor_provider'=>(bool)is_callable(apply_filters('arc_advisor_provider',null))];
    }
}
