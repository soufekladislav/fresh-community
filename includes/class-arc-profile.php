<?php
defined('ABSPATH') || exit;
/** One WP identity; user_meta stores presentation only – never Woo billing or health data. */
final class ARC_Profile {
    private const META_INIT = '_arc_profile_initialized';
    private const META_VIS = '_arc_profile_visibility';
    private const META_ALIAS = '_arc_profile_alias';
    private const META_KIND = '_arc_profile_type';
    /** Presentation only: simple emphasis and safe links, never scripts or inline CSS. */
    public static function bio_html(string $text): string {
        $text = wp_kses($text, [
            'strong'=>[], 'b'=>[], 'br'=>[], 'p'=>[],
            'a'=>['href'=>true, 'title'=>true],
        ]);
        // Stored href protocols remain restricted by wp_kses; external links never inherit opener privileges.
        $text = preg_replace_callback('~<a\b[^>]*>~i', static function($m) {
            $tag = preg_replace('~\s+(target|rel)\s*=\s*(?:"[^"]*"|\'[^\']*\'|[^\s>]+)~i','',$m[0]);
            return str_replace('>', ' target="_blank" rel="noopener noreferrer nofollow">', $tag);
        }, $text);
        return (string)$text;
    }
    public static function initialize(int $uid): void {
        if ($uid < 1 || !get_userdata($uid) || get_user_meta($uid,self::META_INIT,true)) return;
        // Honor the explicit old boolean setting, but new accounts are PRIVATE.
        $legacy = metadata_exists('user',$uid,ARC_Data::META_PUBLIC);
        $visibility = $legacy
            ? ((string)get_user_meta($uid,ARC_Data::META_PUBLIC,true)==='1' ? 'public' : 'private')
            : 'private';
        add_user_meta($uid,self::META_VIS,$visibility,true);
        add_user_meta($uid,self::META_KIND,'customer',true);
        add_user_meta($uid,self::META_INIT,'1',true);
    }
    public static function initialize_current(): void { if(is_user_logged_in()) self::initialize(get_current_user_id()); }
    public static function effective_type(int $uid): string {
        $t=(string)get_user_meta($uid,self::META_KIND,true);
        if($t==='business') return 'business';
        return ARC_Integrations::affiliate_active($uid) ? 'affiliate' : 'customer';
    }
    public static function visibility(int $uid): string {
        if(!$uid || !get_userdata($uid)) return 'private';
        self::initialize($uid);
        $v=(string)get_user_meta($uid,self::META_VIS,true);
        return in_array($v,['private','network','public'],true)?$v:'private';
    }
    /** Display the same user-facing name as WordPress and WooCommerce. Login stays unchanged. */
    public static function display_alias(int $uid): string {
        $user=get_userdata($uid);
        if($user instanceof WP_User) {
            $name=trim((string)$user->display_name);
            if($name!=='') return $name;
        }
        $alias=trim((string)get_user_meta($uid,self::META_ALIAS,true));
        return $alias!==''?$alias:'Člen #'.$uid;
    }
    public static function can_view(int $uid,int $viewer): bool {
        if($viewer && ($viewer===$uid || user_can($viewer,'manage_options'))) return true;
        if(get_user_meta($uid,'_arc_community_suspended',true))return false;
        $v=self::visibility($uid);
        if($v==='public') return true;
        if($v==='network' && $viewer>0) return ARC_Relations::connected($uid,$viewer);
        return false;
    }
    public static function public_card(int $uid,int $viewer=0): ?array {
        if(!self::can_view($uid,$viewer)) return null;
        $type=self::effective_type($uid);
        return ['id'=>$uid,'alias'=>self::display_alias($uid),'type'=>$type,
            'visibility'=>self::visibility($uid),
            'bio'=>self::bio_html((string)get_user_meta($uid,ARC_Data::META_BIO,true)),
            'interests'=>sanitize_text_field((string)get_user_meta($uid,ARC_Data::META_INTERESTS,true)),
            'sport_relation'=>ARC_Config::enabled('sport_fields') ? sanitize_text_field((string)get_user_meta($uid,'_arc_sport_relation',true)) : '',
            'sport_profession'=>ARC_Config::enabled('sport_fields') ? sanitize_text_field((string)get_user_meta($uid,'_arc_sport_profession',true)) : '',
            'sport_experience'=>ARC_Config::enabled('sport_fields') ? sanitize_textarea_field((string)get_user_meta($uid,'_arc_sport_experience',true)) : '',
            'url'=>ARC_Data::member_url($uid),
            'avatar'=>self::avatar_url($uid), // Only shown if permission check above passed; no Gravatar from billing email.
            'connected'=>$viewer>0?ARC_Relations::connected($uid,$viewer):false,
            'referral_partner'=>ARC_Integrations::referral_partner_active($uid)];
    }
    public static function avatar_url(int $uid,string $size='thumbnail'): string {
        $id=(int)get_user_meta($uid,'_arc_avatar_attachment_id',true);
        if(!$id || get_post_type($id)!=='attachment') return '';
        $url=wp_get_attachment_image_url($id,in_array($size,['thumbnail','medium'],true)?$size:'thumbnail');
        if(!$url)$url=wp_get_attachment_url($id); // Some hosts defer image-size generation.
        return $url ? (string)$url : '';
    }
    public static function email_notifications_enabled(int $uid): bool { return (string)get_user_meta($uid,'_arc_email_notifications',true)!=='0'; }
    public static function own_card(int $uid): array {
        self::initialize($uid);
        $card=self::public_card($uid,$uid)?:[];
        return $card+['commerce_url'=>function_exists('wc_get_page_permalink')?wc_get_page_permalink('myaccount'):wp_login_url(),
          'affiliate_active'=>ARC_Integrations::affiliate_active($uid), 'marketing_optin'=>(bool)get_user_meta($uid,'_arc_marketing_optin',true), 'email_notifications'=>self::email_notifications_enabled($uid)];
    }
    public static function save(int $uid, WP_REST_Request $r): array|WP_Error {
        self::initialize($uid);
        // Validate the complete request before making partial changes to the profile.
        if($r->has_param('bio') && mb_strlen(wp_strip_all_tags((string)$r->get_param('bio')))>1400)
            return new WP_Error('arc_bio_length','Text O mně může mít maximálně 1400 znaků.',['status'=>400]);
        if($r->has_param('alias')) {
            $check=trim(sanitize_text_field((string)$r->get_param('alias')));
            if(mb_strlen($check)<2 || mb_strlen($check)>65)
                return new WP_Error('arc_alias','Zobrazované jméno musí mít 2–65 znaků.',['status'=>400]);
        }
        if($r->has_param('alias')) {
            $alias=trim(sanitize_text_field((string)$r->get_param('alias')));
            if(mb_strlen($alias)>65 || mb_strlen($alias)<2) return new WP_Error('arc_alias','Zobrazované jméno musí mít 2–65 znaků.',['status'=>400]);
            // Do not change user_login, e-mail or user_nicename: only the public display name and nickname.
            $result=wp_update_user(['ID'=>$uid,'display_name'=>$alias]);
            if(is_wp_error($result)) return $result;
            update_user_meta($uid,'nickname',$alias);
            update_user_meta($uid,self::META_ALIAS,$alias);
        }
        if($r->has_param('bio')) {
            $raw=(string)$r->get_param('bio');
            // No hidden scripts, styles, images, embeds or HTML attributes except safe href/title.
            $clean=self::bio_html($raw);
            if(mb_strlen(wp_strip_all_tags($clean))>1400) return new WP_Error('arc_bio_length','Text O mně může mít maximálně 1400 znaků.',['status'=>400]);
            update_user_meta($uid,ARC_Data::META_BIO,$clean);
        }
        if($r->has_param('interests')) update_user_meta($uid,ARC_Data::META_INTERESTS,mb_substr(sanitize_text_field((string)$r->get_param('interests')),0,250));
        if($r->has_param('sport_relation')) update_user_meta($uid,'_arc_sport_relation',mb_substr(sanitize_text_field((string)$r->get_param('sport_relation')),0,120));
        if($r->has_param('sport_profession')) update_user_meta($uid,'_arc_sport_profession',mb_substr(sanitize_text_field((string)$r->get_param('sport_profession')),0,160));
        if($r->has_param('sport_experience')) update_user_meta($uid,'_arc_sport_experience',mb_substr(sanitize_textarea_field((string)$r->get_param('sport_experience')),0,900));
        if($r->has_param('email_notifications'))update_user_meta($uid,'_arc_email_notifications',rest_sanitize_boolean($r->get_param('email_notifications'))?'1':'0');
        if($r->has_param('marketing_optin')) {
            update_user_meta($uid,'_arc_marketing_optin',rest_sanitize_boolean($r->get_param('marketing_optin'))?'1':'0');
        }
        if($r->has_param('visibility')) {
            $v=sanitize_key((string)$r->get_param('visibility'));
            if(!in_array($v,['private','network','public'],true)) return new WP_Error('arc_visibility','Neplatná viditelnost.',['status'=>400]);
            // This is explicitly user-driven – no automatic public exposure on role upgrade.
            update_user_meta($uid,self::META_VIS,$v);
            update_user_meta($uid,ARC_Data::META_PUBLIC,$v==='public'?'1':'0');
        }
        return self::own_card($uid);
    }
    /** Business status is an admin-reviewed presentation flag, not an affiliate attribution role. */
    public static function admin_fields(WP_User $u): void {
        if(!current_user_can('edit_user',$u->ID))return;
        echo '<h2>Fresh Community – profesionální profil</h2><table class="form-table"><tr><th><label for="arc_business_status">Obchodní partner</label></th><td>';
        wp_nonce_field('arc_business_admin_'.$u->ID,'arc_business_nonce');
        echo '<label><input id="arc_business_status" name="arc_business_status" type="checkbox" value="1" '.checked((string)get_user_meta($u->ID,self::META_KIND,true),'business',false).'> Ověřený obchodní partner</label><p class="description">Toto není změna Fresh Affiliate přiřazení. Veřejný profil zapni teprve po výslovné dohodě s partnerem.</p>';
        echo '<label><input name="arc_business_public_consent" type="checkbox" value="1"> Partner souhlasí se zveřejněním profesionálního profilu</label></td></tr></table>';
    }
    public static function save_admin(int $uid): void {
        if(!current_user_can('edit_user',$uid))return;
        $nonce=isset($_POST['arc_business_nonce'])?sanitize_text_field(wp_unslash($_POST['arc_business_nonce'])):'';
        if(!$nonce || !wp_verify_nonce($nonce,'arc_business_admin_'.$uid))return;
        self::initialize($uid);
        $business=!empty($_POST['arc_business_status']);
        update_user_meta($uid,self::META_KIND,$business?'business':'customer');
        if($business && !empty($_POST['arc_business_public_consent'])) {
            update_user_meta($uid,self::META_VIS,'public');
            update_user_meta($uid,ARC_Data::META_PUBLIC,'1');
        }
    }
    public static function directory(int $viewer,int $page=1): array {
        // Fetch explicitly public users and viewer's accepted contacts only; never list private by default.
        $ids=get_users(['fields'=>'ID','number'=>100,'offset'=>($page-1)*100,
            'meta_query'=>[['key'=>self::META_VIS,'value'=>'public']],'orderby'=>'registered','order'=>'DESC']);
        $result=[];
        foreach($ids as $id) {
            $card=self::public_card((int)$id,$viewer);
            if($card) $result[]=$card;
        }
        return $result;
    }
}
