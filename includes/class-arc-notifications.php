<?php
defined('ABSPATH') || exit;
/** In-app notifications with optional transactional email; no billing or marketing without opt-in. */
final class ARC_Notifications {
    private static function table(): string {global $wpdb;return $wpdb->prefix.'arc_notifications';}
    private static function insert(int $recipient,int $actor,string $kind,int $object,string $event): void {
        if($recipient<1 || $actor<1 || $recipient===$actor || !get_userdata($recipient))return;
        global $wpdb;
        $ok=$wpdb->query($wpdb->prepare(
            'INSERT IGNORE INTO '.self::table().' (user_id,actor_id,event_key,kind,object_id,is_read,created_at) VALUES (%d,%d,%s,%s,%d,0,%s)',
            $recipient,$actor,substr($event,0,140),$kind,$object,current_time('mysql',true)
        ));
        if($ok)self::schedule_mail();
    }
    public static function boot(): void {
        add_action('arc_notify_mail_batch',[self::class,'deliver_mail_batch']);
        add_action('init',[self::class,'initialize_mail_cursor'],18);
    }
    public static function initialize_mail_cursor(): void {
        if(get_option('arc_notify_mail_cursor',null)===null){
            global $wpdb;
            update_option('arc_notify_mail_cursor',(int)$wpdb->get_var('SELECT MAX(id) FROM '.self::table()),false);
        }
    }
    public static function schedule_mail(): void {
        if(!wp_next_scheduled('arc_notify_mail_batch'))wp_schedule_single_event(time()+10,'arc_notify_mail_batch');
    }
    public static function deliver_mail_batch(): void {
        global $wpdb;$table=self::table();$cursor=(int)get_option('arc_notify_mail_cursor',0);
        $rows=$wpdb->get_results($wpdb->prepare("SELECT id,user_id,actor_id,kind,object_id FROM $table WHERE id>%d ORDER BY id ASC LIMIT 15",$cursor));
        foreach($rows as $r){
            $cursor=(int)$r->id; $uid=(int)$r->user_id;
            // Commit cursor before delivery: a slow SMTP connection must not duplicate mail on retry.
            update_option('arc_notify_mail_cursor',$cursor,false);
            if(!ARC_Profile::email_notifications_enabled($uid))continue;
            $user=get_userdata($uid);if(!$user || !is_email($user->user_email))continue;
            $link=ARC_Navigation::app_link('oznameni');
            $who=ARC_Profile::display_alias((int)$r->actor_id);
            $message=match($r->kind){
                'invite'=>$who.' tě zve mezi přátele.',
                'accepted'=>$who.' přijal/a tvou pozvánku.',
                'affiliate_other'=>$who.' je tvým přítelem, ale již má jiného referenčního partnera.',
                'comment'=>$who.' přidal/a komentář k tvému příspěvku nebo odpověděl/a na tvůj komentář.',
                'post','friend_share'=>$who.' sdílel/a nový komunitní příspěvek.',
                'article'=>$who.' publikoval/a nový článek.',
                'broadcast'=>get_the_title((int)$r->object_id),
                default=>'Nové oznámení z Adrenaline Live.',
            };
            if($r->kind==='broadcast'){
                $post=get_post((int)$r->object_id);
                if(!$post || $post->post_type!=='arc_broadcast')continue;
                if(get_post_meta($post->ID,'_arc_kind',true)==='promo' && !get_user_meta($uid,'_arc_marketing_optin',true))continue;
                $target=(string)get_post_meta($post->ID,'_arc_target_url',true);
                if($target && str_starts_with($target,home_url('/')))$link=$target;
            }
            $subject='Adrenaline Live – nové oznámení';
            $body="Ahoj,\n\n".wp_strip_all_tags($message)."\n\nZobrazit oznámení: ".$link."\n\nE-mailová oznámení můžeš vypnout v Moje komunita → Můj profil.";
            wp_mail($user->user_email,$subject,$body);
        }
        update_option('arc_notify_mail_cursor',$cursor,false);
        if(count($rows)===15)self::schedule_mail();
    }
    public static function comment_added(int $actor,int $target,int $postId,int $commentId): void {
        if($target<1||$target===$actor)return;
        self::insert($target,$actor,'comment',$postId,'comment:'.$commentId);
    }
    public static function invitation(int $actor,int $target): void {
        if($actor<1 || $target<1 || $actor===$target || ARC_Relations::connected($actor,$target))return;
        global $wpdb;$t=ARC_Relations::table();
        $edge=(int)$wpdb->get_var($wpdb->prepare("SELECT id FROM $t WHERE user_id=%d AND target_id=%d AND kind='connection' AND status='pending'",$actor,$target));
        if($edge)self::insert($target,$actor,'invite',$edge,'invite:'.$edge);
    }
    public static function accepted(int $actor,int $inviter): void {
        if(!ARC_Relations::connected($actor,$inviter))return;
        global $wpdb;$t=ARC_Relations::table();
        $edge=(int)$wpdb->get_var($wpdb->prepare("SELECT id FROM $t WHERE user_id=%d AND target_id=%d AND kind='connection' AND status='accepted'",$inviter,$actor));
        if($edge)self::insert($inviter,$actor,'accepted',$edge,'accept:'.$edge);
    }
    public static function affiliate_conflict(int $actor,int $inviter): void {
        if(!ARC_Integrations::referral_partner_active($inviter) || !ARC_Relations::connected($actor,$inviter))return;
        self::insert($inviter,$actor,'affiliate_other',0,'affiliate_other:'.$actor.':'.$inviter);
    }
    public static function social_post(int $actor,int $postId,string $scope,string $type='post'): void {
        if(!in_array($scope,['public','network'],true))return;
        // Friends may read network and public posts; followers only public posts.
        $users=ARC_Relations::connected_ids($actor);
        if($scope==='public') {
            global $wpdb;$t=ARC_Relations::table();
            $followers=$wpdb->get_col($wpdb->prepare("SELECT user_id FROM $t WHERE target_id=%d AND kind='follow' AND status='accepted' LIMIT 150",$actor));
            $users=array_merge($users,array_map('intval',$followers));
        }
        // Pilot safeguard. Larger-scale fanout must become a background queue.
        foreach(array_slice(array_unique(array_map('intval',$users)),0,150) as $user)self::insert($user,$actor,$type,$postId,$type.':'.$postId);
    }
    public static function friend_share(int $actor,int $target,int $postId): void {
        if(!ARC_Relations::connected($actor,$target))return;
        self::insert($target,$actor,'friend_share',$postId,'friendshare:'.$postId);
    }
    public static function article_published(string $new,string $old,WP_Post $post): void {
        if($new!=='publish' || $old==='publish' || $post->post_type!=='post')return;
        $actor=absint(get_post_meta($post->ID,'_arc_verified_author_user_id',true)) ?: (int)$post->post_author;
        if(!$actor || !get_userdata($actor))return;
        // Only verified community authors or articles explicitly submitted by the community.
        if(!get_post_meta($post->ID,'_arc_verified_author_user_id',true) && !get_post_meta($post->ID,'_arc_community_submitted',true))return;
        self::social_post($actor,(int)$post->ID,'public','article');
    }
    public static function inbox(int $user): array {
        ARC_Relations::expire_pending();
        global $wpdb;$table=self::table();
        $rows=$wpdb->get_results($wpdb->prepare("SELECT id,actor_id,kind,object_id,is_read,created_at FROM $table WHERE user_id=%d ORDER BY id DESC LIMIT 35",$user));
        $out=[];
        foreach($rows as $r){
            $actor=(int)$r->actor_id; if($actor===$user)continue; $name=ARC_Profile::display_alias($actor);
            $link=ARC_Navigation::app_link('pratele');$message='Nové upozornění';
            switch($r->kind){
                case 'invite':
                    // Discard stale invitation notifications if request no longer exists.
                    global $wpdb;$rel=ARC_Relations::table();
                    $pending=$wpdb->get_var($wpdb->prepare("SELECT id FROM $rel WHERE id=%d AND target_id=%d AND user_id=%d AND kind='connection' AND status='pending'",(int)$r->object_id,$user,$actor));
                    if(!$pending)continue 2;
                    $message=$name.' tě zve do své sítě.';break;
                case 'broadcast':
                    $campaign=get_post((int)$r->object_id);
                    if(!$campaign || $campaign->post_type!=='arc_broadcast')continue 2;
                    $message=wp_strip_all_tags($campaign->post_title);
                    $link=esc_url_raw((string)get_post_meta($campaign->ID,'_arc_target_url',true))?:home_url('/app/');
                    break;
                case 'accepted':$message=$name.' přijal/a tvou pozvánku.';break;
                case 'affiliate_other':$message=$name.' je nyní tvým přítelem, ale už patří jinému referenčnímu partnerovi.';break;
                case 'comment':$post=get_post((int)$r->object_id);if(!$post || !ARC_Wall::can_read($post,$user))continue 2;$message=$name.' napsal/a komentář nebo odpověď.';$link=add_query_arg('arc_post',$post->ID,home_url('/app/'));break;
                case 'post':
                    $post=get_post((int)$r->object_id);
                    if(!$post || !ARC_Wall::can_read($post,$user))continue 2;
                    $message=$name.' zveřejnil/a nový příspěvek.';$link=home_url('/app/');break;
                case 'friend_share':
                    $post=get_post((int)$r->object_id);
                    if(!$post || !ARC_Wall::can_read($post,$user))continue 2;
                    $message=$name.' sdílel/a příspěvek na tvou zeď.';
                    $link=ARC_Data::member_url($user);break;
                case 'article':
                    $post=get_post((int)$r->object_id);
                    if(!$post || $post->post_status!=='publish')continue 2;
                    $message=$name.' publikoval/a článek: '.get_the_title($post);$link=get_permalink($post);break;
                default:continue 2;
            }
            $out[]=['id'=>(int)$r->id,'actor_name'=>$name,'message'=>$message,'url'=>esc_url_raw($link),'read'=>(bool)$r->is_read,'date'=>wp_date('j. n. Y H:i',strtotime($r->created_at.' UTC')),'type'=>$r->kind,'actor_id'=>$actor,'avatar'=>ARC_Profile::avatar_url($actor)];
        }
        return ['items'=>$out,'unread'=>count(array_filter($out,static fn($v)=>!$v['read']))];
    }
    /** Soft-delete per recipient: never remove a notification belonging to another member. */
    public static function delete_one(int $user,int $id): array|WP_Error {
        if($user<1 || $id<1)return new WP_Error('arc_notification','Neplatné oznámení.',['status'=>400]);
        global $wpdb;$table=self::table();
        $deleted=$wpdb->delete($table,['id'=>$id,'user_id'=>$user],['%d','%d']);
        if($deleted===false)return new WP_Error('arc_notification_db','Oznámení se nepodařilo smazat.',['status'=>500]);
        return ['ok'=>true,'deleted'=>(bool)$deleted];
    }
    public static function mark_read(int $user,int $id): array {
        global $wpdb;$table=self::table();
        if($id) $wpdb->update($table,['is_read'=>1],['id'=>$id,'user_id'=>$user],['%d'],['%d','%d']);
        else $wpdb->update($table,['is_read'=>1],['user_id'=>$user],['%d'],['%d']);
        return ['ok'=>true];
    }
}
