<?php
/**
 * Private 1:1 text messages between accepted friends. No third-party service,
 * no public WP post type, no HTML messages and no cross-member REST reads.
 */
defined('ABSPATH') || exit;
final class ARC_Messages {
    private const NS='ar-community/v1';
    public static function table(): string { global $wpdb;return $wpdb->prefix.'arc_direct_messages'; }
    public static function install_table(): void {
        global $wpdb;
        require_once ABSPATH.'wp-admin/includes/upgrade.php';
        $table=self::table();$collate=$wpdb->get_charset_collate();
        dbDelta("CREATE TABLE $table (
            id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
            sender_id bigint(20) unsigned NOT NULL,
            recipient_id bigint(20) unsigned NOT NULL,
            body text NOT NULL,
            created_at datetime NOT NULL,
            read_at datetime DEFAULT NULL,
            PRIMARY KEY  (id),
            KEY sender_thread (sender_id,recipient_id,id),
            KEY recipient_thread (recipient_id,sender_id,id),
            KEY recipient_unread (recipient_id,read_at,id)
        ) $collate;");
    }
    public static function boot(): void {
        add_shortcode('ar_community_messages',[self::class,'shortcode']);
        add_action('rest_api_init',[self::class,'routes']);
    }
    public static function routes(): void {
        $permission=static fn()=>is_user_logged_in() && ARC_Access::member() && !get_user_meta(get_current_user_id(),'_arc_community_suspended',true);
        register_rest_route(self::NS,'/messages/threads',['methods'=>'GET','callback'=>[self::class,'threads'],'permission_callback'=>$permission]);
        register_rest_route(self::NS,'/messages/(?P<peer>\d+)',['methods'=>'GET','callback'=>[self::class,'conversation'],'permission_callback'=>$permission]);
        register_rest_route(self::NS,'/messages/(?P<peer>\d+)',['methods'=>'POST','callback'=>[self::class,'send'],'permission_callback'=>$permission]);
        register_rest_route(self::NS,'/messages/(?P<peer>\d+)/read',['methods'=>'POST','callback'=>[self::class,'read'],'permission_callback'=>$permission]);
    }
    private static function can_talk(int $peer): bool {
        $me=get_current_user_id();
        return $peer>0 && $peer!==$me && ARC_Access::member($peer)
            && ARC_Relations::connected($me,$peer)
            && !get_user_meta($peer,'_arc_community_suspended',true);
    }
    private static function denied(): WP_Error {
        return new WP_Error('arc_message_permission','Soukromé zprávy jsou dostupné jen mezi potvrzenými přáteli.',['status'=>403]);
    }
    public static function shortcode(): string {
        wp_enqueue_style('arc-messages',ARC_URL.'assets/messages.css',[],ARC_VERSION);
        if(!is_user_logged_in() || !ARC_Access::member())return ARC_Access::denied();
        wp_enqueue_script('arc-messages',ARC_URL.'assets/messages.js',[],ARC_VERSION,true);
        wp_add_inline_script('arc-messages','window.ARCChat='.wp_json_encode([
            'root'=>rest_url(self::NS.'/messages/'),
            'nonce'=>wp_create_nonce('wp_rest'),
            'peer'=>isset($_GET['with'])?absint($_GET['with']):0,
        ]).';','before');
        return '<section class="arc-msg" data-arc-msg><div class="arc-msg-list"><h2>Zprávy</h2><div data-arc-msg-threads role="list"><p>Načítám přátele…</p></div></div><div class="arc-msg-conversation"><h2 data-arc-msg-title>Vyber přítele</h2><div class="arc-msg-history" data-arc-msg-history role="log" aria-live="polite"><p>Soukromé zprávy mohou posílat potvrzení přátelé.</p></div><form data-arc-msg-compose hidden><label for="arc-msg-body">Zpráva</label><textarea id="arc-msg-body" name="body" maxlength="1500" required rows="2" placeholder="Napiš zprávu…"></textarea><button type="submit">Odeslat</button></form><p data-arc-msg-status role="status"></p></div></section>';
    }
    public static function threads(): array {
        nocache_headers();
        $me=get_current_user_id();$friends=array_slice(ARC_Relations::connected_ids($me),0,300);
        global $wpdb;$table=self::table();$result=[];
        foreach($friends as $peer) {
            if(!self::can_talk($peer))continue;
            $last=$wpdb->get_row($wpdb->prepare("SELECT id,body,sender_id,created_at FROM $table WHERE (sender_id=%d AND recipient_id=%d) OR (sender_id=%d AND recipient_id=%d) ORDER BY id DESC LIMIT 1",$me,$peer,$peer,$me));
            $unread=(int)$wpdb->get_var($wpdb->prepare("SELECT COUNT(*) FROM $table WHERE sender_id=%d AND recipient_id=%d AND read_at IS NULL",$peer,$me));
            $result[]=['id'=>$peer,'name'=>ARC_Profile::display_alias($peer),'avatar'=>ARC_Profile::avatar_url($peer),
                'latest'=>$last ? mb_substr($last->body,0,80) : '',
                'last_id'=>$last?(int)$last->id:0,'unread'=>$unread];
        }
        usort($result,static fn($a,$b)=>($b['last_id']<=>$a['last_id']) ?: strcasecmp($a['name'],$b['name']));
        return ['items'=>$result,'unread'=>array_sum(array_column($result,'unread'))];
    }
    public static function conversation(WP_REST_Request $r): array|WP_Error {
        nocache_headers();$peer=absint($r['peer']);if(!self::can_talk($peer))return self::denied();
        $me=get_current_user_id();global $wpdb;$table=self::table();
        $before=absint($r->get_param('before'));
        $where=$before?' AND id < %d':'';
        $sql="SELECT id,sender_id,recipient_id,body,created_at,read_at FROM $table WHERE ((sender_id=%d AND recipient_id=%d) OR (sender_id=%d AND recipient_id=%d))".$where." ORDER BY id DESC LIMIT 50";
        $params=[$me,$peer,$peer,$me];if($before)$params[]=$before;
        $rows=$wpdb->get_results($wpdb->prepare($sql,$params));
        $items=[];
        foreach(array_reverse($rows) as $v)$items[]=['id'=>(int)$v->id,'mine'=>(int)$v->sender_id===$me,'body'=>$v->body,'date'=>wp_date('j. n. Y H:i',strtotime($v->created_at.' UTC')),'read'=>$v->read_at!==null];
        return ['peer'=>$peer,'items'=>$items,'before'=>count($rows)===50 && $items?(int)$items[0]['id']:0];
    }
    public static function send(WP_REST_Request $r): array|WP_Error {
        nocache_headers();$peer=absint($r['peer']);if(!self::can_talk($peer))return self::denied();
        $body=trim(sanitize_textarea_field((string)$r->get_param('body')));
        if($body==='' || mb_strlen($body)>1500)return new WP_Error('arc_message_length','Zpráva musí mít 1 až 1500 znaků.',['status'=>400]);
        $me=get_current_user_id();$rate_key='arc_dm_rate_'.$me;$count=(int)get_transient($rate_key);
        if($count>=40)return new WP_Error('arc_message_limit','Limit 40 zpráv za hodinu byl dosažen.',['status'=>429]);
        set_transient($rate_key,$count+1,HOUR_IN_SECONDS);
        global $wpdb;$table=self::table();
        $ok=$wpdb->insert($table,['sender_id'=>$me,'recipient_id'=>$peer,'body'=>$body,'created_at'=>current_time('mysql',true)],['%d','%d','%s','%s']);
        if(!$ok)return new WP_Error('arc_message_storage','Zprávu nelze uložit.',['status'=>500]);
        $id=(int)$wpdb->insert_id;
        ARC_Notifications::direct_message($me,$peer,$id);
        return ['ok'=>true,'id'=>$id];
    }
    public static function read(WP_REST_Request $r): array|WP_Error {
        nocache_headers();$peer=absint($r['peer']);if(!self::can_talk($peer))return self::denied();
        $me=get_current_user_id();global $wpdb;$table=self::table();
        $wpdb->query($wpdb->prepare("UPDATE $table SET read_at=%s WHERE recipient_id=%d AND sender_id=%d AND read_at IS NULL",current_time('mysql',true),$me,$peer));
        return ['ok'=>true];
    }
}
