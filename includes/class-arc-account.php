<?php
/** Community account conveniences: friend discovery, invitation and avatar. Independent from affiliate. */
defined('ABSPATH') || exit;
final class ARC_Account {
    public static function boot(): void { /* REST registrations are in ARC_Live_API. */ }
    private static function error(string $s,int $code=400): WP_Error {return new WP_Error('arc_account',$s,['status'=>$code]);}
    public static function friends(int $uid): array {
        $ids=ARC_Relations::connected_ids($uid);
        // Most recently registered friends at the top; reused by full Friends page.
        usort($ids,static function($a,$b){
            $ua=get_userdata((int)$a);$ub=get_userdata((int)$b);
            return strcmp($ub instanceof WP_User?(string)$ub->user_registered:'', $ua instanceof WP_User?(string)$ua->user_registered:'');
        });
        $list=[];
        foreach(array_slice($ids,0,100) as $id) {
            if(!get_userdata($id)) continue;
            $card=ARC_Profile::public_card($id,$uid);
            if($card) $list[]=['id'=>$id,'alias'=>$card['alias'],'url'=>$card['url'],'avatar'=>$card['avatar'],'hidden'=>false,'referral_partner'=>ARC_Integrations::in_my_network($uid,$id),'affiliate_status'=>ARC_Integrations::friend_affiliate_status($uid,$id)];
            else $list[]=['id'=>$id,'alias'=>ARC_Profile::display_alias($id),'url'=>'','avatar'=>'','hidden'=>true,'referral_partner'=>ARC_Integrations::in_my_network($uid,$id),'affiliate_status'=>ARC_Integrations::friend_affiliate_status($uid,$id)];
        }
        return ['items'=>$list];
    }
    private static function searchable(int $id,int $viewer): bool {
        if($id<1 || $id===$viewer || !get_userdata($id) || get_user_meta($id,'_arc_community_suspended',true))return false;
        return ARC_Profile::visibility($id)!=='private';
    }
    private static function search_card(int $id,int $viewer): array {
        $visible=ARC_Profile::can_view($id,$viewer);
        return ['id'=>$id,'alias'=>ARC_Profile::display_alias($id),
            'url'=>$visible?ARC_Data::member_url($id):'',
            'avatar'=>$visible?ARC_Profile::avatar_url($id):'',
            'connected'=>ARC_Relations::connected($viewer,$id),
            'referral_partner'=>ARC_Integrations::in_my_network($viewer,$id),
            'affiliate_status'=>ARC_Integrations::friend_affiliate_status($viewer,$id)];
    }
    public static function search(int $uid,string $query): array|WP_Error {
        if(ARC_Profile::visibility($uid)==='private')return self::error('Pro vyhledávání přátel změň viditelnost profilu na Viditelný v síti nebo Veřejný.',403);
        $term=trim(sanitize_text_field($query));
        if(mb_strlen($term)<2 || mb_strlen($term)>100)return self::error('Zadej alespoň dva znaky.');
        if(is_email($term)){
            $person=get_user_by('email',$term);$id=$person?(int)$person->ID:0;
            return ['items'=>self::searchable($id,$uid)?[self::search_card($id,$uid)]:[],'email'=>true];
        }
        global $wpdb;$like='%'.$wpdb->esc_like($term).'%';
        $ids=$wpdb->get_col($wpdb->prepare("SELECT DISTINCT u.ID FROM {$wpdb->users} u
            LEFT JOIN {$wpdb->usermeta} a ON a.user_id=u.ID AND a.meta_key='_arc_profile_alias'
            WHERE (u.display_name LIKE %s OR a.meta_value LIKE %s) AND u.ID!=%d
            ORDER BY u.ID DESC LIMIT 60",$like,$like,$uid));
        $out=[];foreach($ids as $id){$id=(int)$id;if(!self::searchable($id,$uid))continue;
            $out[]=self::search_card($id,$uid);if(count($out)>=12)break;
        }
        return ['items'=>$out,'email'=>false];
    }
    /** Search members with network/public visibility; private accounts never appear. */
    public static function directory(int $uid,string $query='',int $page=1): array|WP_Error {
        $query=trim(sanitize_text_field($query));
        if($query==='')return ['items'=>[],'has_more'=>false,'page'=>1];
        $results=self::search($uid,$query);
        if(is_wp_error($results))return $results;
        return ['items'=>$results['items'],'has_more'=>false,'page'=>1];
    }
    public static function invite(int $uid,WP_REST_Request $request): array|WP_Error {
        if(ARC_Profile::visibility($uid)==='private')return self::error('Pro odesílání komunitních pozvánek změň viditelnost profilu na Viditelný v síti nebo Veřejný.',403);
        $k='arc_invite_hour_'.$uid;
        $n=(int)get_transient($k);
        if($n>=12) return self::error('Bylo odesláno příliš mnoho pozvánek. Zkus to znovu později.',429);
        $action=sanitize_key((string)$request->get_param('action'));
        $target=absint($request->get_param('target_id'));
        $email=trim(sanitize_email((string)$request->get_param('email')));
        if(!$target && is_email($email)) {
            $existing=get_user_by('email',$email);
            if($existing) $target=(int)$existing->ID;
            // Do not confirm if email belongs to a Woo/WordPress account.
            if(!$existing || $target===$uid) {
                set_transient($k,$n+1,HOUR_IN_SECONDS);
                if(!$existing && is_email($email))ARC_Invites::mail($uid,$email);
                return ['ok'=>true,'message'=>'Pozvánka byla zpracována. Pokud je adresát v síti, najde ji ve svém účtu. Jinak obdrží registrační odkaz.'];
            }
            // Known-email invitation creates only a pending connection; private accounts are not discoverable.
            $r=ARC_Relations::invite_known_email($uid,$target);
            // In-app notification also queues the transactional email once, respecting opt-out.
        } elseif($target && $action==='request') {
            $r=ARC_Relations::change($uid,$target,'request');
        } elseif($target && in_array($action,['accept','decline','disconnect'],true)) {
            $r=ARC_Relations::change($uid,$target,$action);
        } else return self::error('Neplatná žádost.');
        if(is_wp_error($r))return $r;
        // Transactional email to existing members is queued by ARC_Notifications.
        if(!in_array($action,['accept','decline','disconnect'],true))set_transient($k,$n+1,HOUR_IN_SECONDS);
        return ['ok'=>true,'target_id'=>$target,'new_request'=>!empty($r['new_request']),'message'=> $action==='accept' ? (($r['affiliate_status']??'')==='other' ? 'Pozvánka přijata. Jste přátelé; tento člen už patří do sítě jiného referenčního partnera.' : 'Pozvánka přijata, nyní jste přátelé.') : ($action==='decline' ? 'Pozvánka odmítnuta.' : ($action==='disconnect' ? 'Spojení bylo odebráno.' : 'Pozvánka odeslána. Propojení vznikne až po potvrzení druhou stranou.'))];
    }
    public static function avatar(int $uid): array|WP_Error {
        if(empty($_FILES['avatar']) || !is_array($_FILES['avatar'])) return self::error('Vyber fotografii.');
        if(empty($_POST['consent']) || (string)wp_unslash($_POST['consent'])!=='1')return self::error('Potvrď, že rozumíš veřejné adrese fotografie.');
        $file=$_FILES['avatar'];
        if(($file['error']??UPLOAD_ERR_NO_FILE)!==UPLOAD_ERR_OK || empty($file['size']) || $file['size']>2*1024*1024) return self::error('Fotografie nesmí přesáhnout 2 MB.');
        $info=wp_check_filetype_and_ext($file['tmp_name'],(string)($file['name']??''));
        if(!in_array($info['ext']??'', ['jpg','jpeg','png','webp'],true) || !in_array($info['type']??'', ['image/jpeg','image/png','image/webp'],true))return self::error('Povoleny jsou JPG, PNG a WebP.');
        $k='arc_avatar_limit_'.$uid;$n=(int)get_transient($k);
        if($n>=6)return self::error('Příliš mnoho fotografií. Zkus to později.',429);
        require_once ABSPATH.'wp-admin/includes/file.php';
        require_once ABSPATH.'wp-admin/includes/image.php';
        require_once ABSPATH.'wp-admin/includes/media.php';
        $id=media_handle_upload('avatar',0,[],['test_form'=>false]);
        if(is_wp_error($id))return self::error('Fotografii se nepodařilo nahrát: '.sanitize_text_field($id->get_error_message()),400);
        wp_update_post(['ID'=>$id,'post_author'=>$uid]);
        if(!wp_attachment_is_image($id)){wp_delete_attachment($id,true);return self::error('Nahraný soubor není platný obrázek.');}
        $written=update_user_meta($uid,'_arc_avatar_attachment_id',(int)$id);
        if($written===false && (int)get_user_meta($uid,'_arc_avatar_attachment_id',true)!==(int)$id)return self::error('Fotografie je na serveru, ale nepodařilo se ji přiřadit k profilu.',500);
        $actual=(int)get_user_meta($uid,'_arc_avatar_attachment_id',true);
        if($actual!==(int)$id)return self::error('Fotografii se nepodařilo uložit k profilu.',500);
        $url=ARC_Profile::avatar_url($uid,'medium');
        if(!$url)return self::error('Fotografie je nahraná, ale WordPress nevrací její adresu.',500);
        set_transient($k,$n+1,HOUR_IN_SECONDS);
        return ['ok'=>true,'avatar'=>$url,'message'=>'Fotografie byla uložena a přiřazena k profilu.'];
    }
}
