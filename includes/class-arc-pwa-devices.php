<?php
/** Lightweight opt-in-by-use telemetry: only launches in installed display mode, admin only. */
defined('ABSPATH') || exit;
final class ARC_PWA_Devices {
    private const META = '_arc_pwa_devices';
    public static function boot(): void { add_action('rest_api_init',[self::class,'routes']); }
    public static function routes(): void {
        register_rest_route('ar-community/v1','/pwa-device',[
            'methods'=>'POST','callback'=>[self::class,'record'],
            'permission_callback'=>static function(){return is_user_logged_in() && current_user_can('read');},
        ]);
    }
    public static function record(WP_REST_Request $r): WP_REST_Response {
        $uid=get_current_user_id();
        $id=(string)$r->get_param('id');
        if(!preg_match('/^[a-zA-Z0-9_-]{24,65}$/D',$id))return new WP_REST_Response(['message'=>'Neplatná identifikace zařízení.'],400);
        $source=sanitize_key((string)$r->get_param('source'));
        if(!in_array($source,['standalone_launch','installed_event'],true))return new WP_REST_Response(['message'=>'Neplatný typ potvrzení.'],400);
        $platform=mb_substr(sanitize_text_field((string)$r->get_param('platform')),0,40);
        $browser=mb_substr(sanitize_text_field((string)$r->get_param('browser')),0,40);
        $devices=get_user_meta($uid,self::META,true);
        $devices=is_array($devices)?$devices:[];
        $key=hash('sha256',$id);
        $now=time();
        $previous=$devices[$key]??[];
        $devices[$key]=[
            'first_seen'=>absint($previous['first_seen']??$now),
            'last_seen'=>$now,
            'platform'=>$platform,'browser'=>$browser,
            'source'=>$source==='installed_event'?'installed_event':($previous['source']??$source),
        ];
        uasort($devices,static fn($a,$b)=>($b['last_seen']??0)<=>($a['last_seen']??0));
        $devices=array_slice($devices,0,12,true); // Maximum 12 device records per user
        update_user_meta($uid,self::META,$devices);
        return new WP_REST_Response(['recorded'=>true],200);
    }
    public static function admin_cell(int $uid): string {
        $items=get_user_meta($uid,self::META,true);
        if(!is_array($items)||!$items)return 'Nezjištěno';
        $latest=max(array_map(static fn($v)=>absint($v['last_seen']??0),$items));
        $label=count($items).' zařízení';
        $first=reset($items);
        $label.=' · '.(string)($first['platform']??'');
        if($latest)$label.=' · '.wp_date('j. n. Y H:i',$latest);
        return esc_html($label);
    }
}
