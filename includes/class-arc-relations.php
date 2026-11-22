<?php
defined('ABSPATH') || exit;
/** Completely separate from Fresh Affiliate attribution. */
final class ARC_Relations {
    public static function table(): string {global $wpdb;return $wpdb->prefix.'arc_relations';}
    public static function connected(int $a,int $b): bool {
        if(!$a||!$b||$a===$b) return $a>0 && $a===$b;
        global $wpdb;$t=self::table();
        return (bool)$wpdb->get_var($wpdb->prepare("SELECT id FROM $t WHERE ((user_id=%d AND target_id=%d) OR (user_id=%d AND target_id=%d)) AND kind='connection' AND status='accepted' LIMIT 1",$a,$b,$b,$a));
    }
    public static function follows(int $uid,int $target): bool {
        global $wpdb;$t=self::table();return (bool)$wpdb->get_var($wpdb->prepare("SELECT id FROM $t WHERE user_id=%d AND target_id=%d AND kind='follow' AND status='accepted' LIMIT 1",$uid,$target));
    }
    public static function change(int $uid,int $target,string $action): array|WP_Error {
        if($uid<1||$target<1||$uid===$target||!get_userdata($target)) return new WP_Error('arc_relation','Neplatný člen.',['status'=>400]);
        if($action==='request' && (ARC_Profile::visibility($uid)==='private' || ARC_Profile::visibility($target)==='private')) return new WP_Error('arc_private','Pro nové pozvánky musí být oba profily viditelné v síti nebo veřejné.',['status'=>403]);
        global $wpdb;$t=self::table();
        if($action==='follow') {
            // Following never grants visibility to private / network posts.
            if(ARC_Profile::visibility($target)!=='public') return new WP_Error('arc_private','Sledování je dostupné jen pro veřejné profily.',['status'=>403]);
            $wpdb->query($wpdb->prepare("INSERT INTO $t (user_id,target_id,kind,status,created_at) VALUES (%d,%d,'follow','accepted',%s) ON DUPLICATE KEY UPDATE status='accepted'",$uid,$target,current_time('mysql',true)));
        } elseif($action==='unfollow') {
            $wpdb->delete($t,['user_id'=>$uid,'target_id'=>$target,'kind'=>'follow'],['%d','%d','%s']);
        } elseif($action==='request') {
            if(self::connected($uid,$target))return ['ok'=>true,'connected'=>true,'new_request'=>false,'target_id'=>$target];
            self::expire_pending();
            $reverse=(int)$wpdb->get_var($wpdb->prepare("SELECT id FROM $t WHERE user_id=%d AND target_id=%d AND kind='connection' AND status='pending' LIMIT 1",$target,$uid));
            if($reverse)return new WP_Error('arc_request_exists','Tento člen tě již pozval. Potvrď jeho žádost v Nových žádostech.',['status'=>409]);
            $existing=(int)$wpdb->get_var($wpdb->prepare("SELECT id FROM $t WHERE user_id=%d AND target_id=%d AND kind='connection' AND status='pending' LIMIT 1",$uid,$target));
            if($existing)return ['ok'=>true,'connected'=>false,'new_request'=>false,'target_id'=>$target];
            $written=$wpdb->query($wpdb->prepare("INSERT INTO $t (user_id,target_id,kind,status,created_at) VALUES (%d,%d,'connection','pending',%s) ON DUPLICATE KEY UPDATE status=IF(status='accepted',status,'pending'),created_at=IF(status='accepted',created_at,VALUES(created_at))",$uid,$target,current_time('mysql',true)));
            if($written===false)return new WP_Error('arc_db_request','Žádost se nepodařilo odeslat.',['status'=>500]);
            $actual=(int)$wpdb->get_var($wpdb->prepare("SELECT id FROM $t WHERE user_id=%d AND target_id=%d AND kind='connection' AND status='pending' LIMIT 1",$uid,$target));
            if(!$actual)return new WP_Error('arc_request_state','Žádost se nepodařilo potvrdit v databázi.',['status'=>409]);
            ARC_Notifications::invitation($uid,$target);
            $created=true;
        } elseif($action==='accept' || $action==='decline') {
            $row=$wpdb->get_row($wpdb->prepare("SELECT id FROM $t WHERE user_id=%d AND target_id=%d AND kind='connection' AND status='pending' LIMIT 1",$target,$uid));
            if(!$row) return new WP_Error('arc_request','Žádost nenalezena.',['status'=>404]);
            if($action==='accept') $wpdb->update($t,['status'=>'accepted'],['id'=>(int)$row->id],['%s'],['%d']);
            else $wpdb->delete($t,['id'=>(int)$row->id],['%d']);
        } elseif($action==='disconnect') {
            $wpdb->query($wpdb->prepare("DELETE FROM $t WHERE kind='connection' AND ((user_id=%d AND target_id=%d) OR (user_id=%d AND target_id=%d))",$uid,$target,$target,$uid));
        } else return new WP_Error('arc_relation_action','Neplatná akce.',['status'=>400]);
        if($action==='accept') {
            ARC_Notifications::accepted($uid,$target);
            // The sender of the accepted request is the inviter; never replace an existing lifetime affiliate.
            $affiliateResult=ARC_Integrations::attribute_accepted_invite($target,$uid);
            if($affiliateResult==='other')ARC_Notifications::affiliate_conflict($uid,$target);
        }
        return ['ok'=>true,'connected'=>self::connected($uid,$target),'following'=>self::follows($uid,$target),'target_id'=>$target,'new_request'=>!empty($created),'affiliate_status'=>$action==='accept'?ARC_Integrations::friend_affiliate_status($target,$uid):''];
    }
    /** Explicit invitation by known email. Does not expose existence in the UI. */
    public static function invite_known_email(int $uid,int $target): array|WP_Error {
        if($uid<1 || $target<1 || $uid===$target || !get_userdata($target)) return new WP_Error('arc_relation','Neplatná žádost.',['status'=>400]);
        if(ARC_Profile::visibility($uid)==='private' && !user_can($uid,'manage_options'))return new WP_Error('arc_private','Pro pozvání nastav profil na Viditelný v síti nebo Veřejný.',['status'=>403]);
        if(self::connected($uid,$target)) return ['ok'=>true,'connected'=>true];
        global $wpdb;$t=self::table();
        $reverse=$wpdb->get_var($wpdb->prepare("SELECT id FROM $t WHERE user_id=%d AND target_id=%d AND kind='connection' LIMIT 1",$target,$uid));
        if(!$reverse) {
            $wpdb->query($wpdb->prepare("INSERT INTO $t (user_id,target_id,kind,status,created_at) VALUES (%d,%d,'connection','pending',%s) ON DUPLICATE KEY UPDATE status=IF(status='accepted',status,'pending')",$uid,$target,current_time('mysql',true)));
            ARC_Notifications::invitation($uid,$target);
        }
        return ['ok'=>true,'connected'=>false];
    }
    /** Pending invitations expire after 30 days. Only pending edges are removed. */
    public static function expire_pending(): void {
        // Do not execute a DELETE on every notifications poll.
        if(get_transient('arc_pending_expired_cleanup'))return;
        global $wpdb;$t=self::table();
        $cutoff=gmdate('Y-m-d H:i:s',time()-30*DAY_IN_SECONDS);
        $result=$wpdb->query($wpdb->prepare("DELETE FROM $t WHERE kind='connection' AND status='pending' AND created_at < %s",$cutoff));
        if($result!==false)set_transient('arc_pending_expired_cleanup',1,HOUR_IN_SECONDS);
    }
    public static function sent(int $uid): array {
        self::expire_pending();
        global $wpdb;$t=self::table();
        $cutoff=gmdate('Y-m-d H:i:s',time()-30*DAY_IN_SECONDS);
        $rows=$wpdb->get_results($wpdb->prepare("SELECT target_id,created_at FROM $t WHERE user_id=%d AND kind='connection' AND status='pending' AND created_at>=%s ORDER BY created_at DESC,id DESC LIMIT 100",$uid,$cutoff));
        $out=[];foreach($rows as $row){
            $id=(int)$row->target_id;
            if($id===$uid || !get_userdata($id))continue;
            $out[]=['id'=>$id,'alias'=>ARC_Profile::display_alias($id),'avatar'=>ARC_Profile::avatar_url($id),
                'url'=>ARC_Profile::can_view($id,$uid)?ARC_Data::member_url($id):'',
                'created_at'=>wp_date('j. n. Y',strtotime($row->created_at.' UTC'))];
        }
        return $out;
    }
    public static function pending(int $uid): array {
        self::expire_pending();
        global $wpdb;$t=self::table();$cutoff=gmdate('Y-m-d H:i:s',time()-30*DAY_IN_SECONDS);
        $rows=$wpdb->get_results($wpdb->prepare("SELECT user_id FROM $t WHERE target_id=%d AND kind='connection' AND status='pending' AND created_at>=%s ORDER BY id DESC LIMIT 40",$uid,$cutoff));
        $out=[];foreach($rows as $r) {
            $id=(int)$r->user_id;
            if($id===$uid || !get_userdata($id))continue;
            $out[]=['id'=>$id,'alias'=>ARC_Profile::display_alias($id),'avatar'=>ARC_Profile::avatar_url($id),'url'=>ARC_Data::member_url($id),'referral_partner'=>ARC_Integrations::in_my_network($uid,$id)];
        }return $out;
    }
    public static function connected_ids(int $uid): array {
        global $wpdb;$t=self::table();$rows=$wpdb->get_col($wpdb->prepare("SELECT CASE WHEN user_id=%d THEN target_id ELSE user_id END FROM $t WHERE (user_id=%d OR target_id=%d) AND kind='connection' AND status='accepted' LIMIT 500",$uid,$uid,$uid));
        return array_map('intval',$rows);
    }
    public static function following_ids(int $uid): array {
        global $wpdb;$t=self::table();return array_map('intval',$wpdb->get_col($wpdb->prepare("SELECT target_id FROM $t WHERE user_id=%d AND kind='follow' AND status='accepted' LIMIT 500",$uid)));
    }
}
