<?php
defined('ABSPATH') || exit;
/** Scoped PWA manifest and service worker. Never caches private WP data. */
final class ARC_PWA {
    public static function boot(): void {
        add_action('template_redirect',[self::class,'endpoint'],0);
        add_action('wp',[self::class,'compat_legacy'],25);
        add_action('wp_head',[self::class,'head'],30);
        add_action('wp_footer',[self::class,'register_worker'],99);
        add_filter('auth_cookie_expiration',[self::class,'remember_duration'],25,3);
    }
    /** 30-day remembered WP sessions; no second identity or refresh-token store. */
    public static function remember_duration(int $seconds,int $uid,bool $remember): int {
        return $remember?max($seconds,30*DAY_IN_SECONDS):$seconds;
    }
    public static function on_live(): bool {
        if(!ARC_Config::is_app_page())return false;
        $page=get_queried_object();if(!$page instanceof WP_Post)return false;
        $source=(string)$page->post_content.' '.(string)get_post_meta($page->ID,'_elementor_data',true);
        return str_contains($source,'[ar_live]') || str_contains($source,'[ar_community_wall]');
    }
    public static function compat_legacy(): void {
        if(!self::on_live() || !ARC_Config::legacy_site() || !class_exists('ALF_Plugin'))return;
        // Only when /app/ already contains our community shortcode; legacy billing and Stripe hooks stay active.
        $alf=ALF_Plugin::instance();
        remove_action('wp_enqueue_scripts',[$alf,'assets']);
        remove_action('wp_head',[$alf,'head_tags']);
    }
    public static function endpoint(): void {
        if(!self::on_live() || !isset($_GET['arc_pwa']))return;
        $kind=sanitize_key((string)wp_unslash($_GET['arc_pwa']));
        $app=ARC_Config::app_url();$scope=trailingslashit((string)wp_parse_url($app,PHP_URL_PATH));$icon=ARC_URL.'assets/pwa-icon-192.png';$large=ARC_URL.'assets/pwa-icon-512.png';
        nocache_headers();header('X-Content-Type-Options: nosniff');
        if($kind==='manifest') {
            header('Content-Type: application/manifest+json; charset=UTF-8');
            echo wp_json_encode(['id'=>$app,'name'=>ARC_Config::name(),'short_name'=>ARC_Config::short_name(),'description'=>ARC_Config::description(),'lang'=>'cs','start_url'=>$app,'scope'=>$scope,'display'=>'standalone','theme_color'=>'#343434','background_color'=>'#FFFFFF','icons'=>[
              ['src'=>$icon,'sizes'=>'192x192','type'=>'image/png','purpose'=>'any'],
              ['src'=>$large,'sizes'=>'512x512','type'=>'image/png','purpose'=>'any']],
              'shortcuts'=>[['name'=>'Přátelé','url'=>ARC_Navigation::app_link('pratele')],['name'=>'Premium','url'=>home_url('/premium/')]]],JSON_UNESCAPED_SLASHES|JSON_UNESCAPED_UNICODE);exit;
        }
        if($kind==='sw') {
            header('Content-Type: text/javascript; charset=UTF-8');header('Service-Worker-Allowed: '.$scope);
            $offline='<!doctype html><html lang="cs"><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1"><title>'.esc_html(ARC_Config::name()).'</title><body style="font-family:system-ui;padding:2rem"><h1>Jsi offline</h1><p>Komunitní příspěvky a osobní účty jsou dostupné po připojení k internetu.</p></body></html>';
            // A changed service-worker byte stream lets installed PWAs detect a new plugin release.
            echo 'const ARC_BUILD='.wp_json_encode(ARC_VERSION).';';
            echo 'const OFFLINE='.wp_json_encode($offline).';';
            echo 'self.addEventListener("install",()=>self.skipWaiting());';
            echo 'self.addEventListener("activate",e=>e.waitUntil(self.clients.claim()));';
            // Online-first, never cache authenticated HTML, REST APIs, cookies or health data.
            echo 'self.addEventListener("fetch",e=>{if(e.request.method!=="GET"||e.request.mode!=="navigate")return;e.respondWith(fetch(e.request).catch(()=>new Response(OFFLINE,{headers:{"Content-Type":"text/html; charset=utf-8"}})));});';
            echo 'self.addEventListener("push",e=>{let p={};try{p=e.data?e.data.json():{}}catch(_){};e.waitUntil(self.registration.showNotification(p.title||'.wp_json_encode(ARC_Config::name()).',{body:p.body||"Nové upozornění",icon:'.wp_json_encode($icon).',data:{url:p.url||'.wp_json_encode($app).'}}));});';
            echo 'self.addEventListener("notificationclick",e=>{e.notification.close();let u=e.notification.data&&e.notification.data.url||'.wp_json_encode($app).';e.waitUntil(clients.openWindow(u));});';
            exit;
        }
    }
    public static function head(): void {
        if(!self::on_live())return;
        echo '<link rel="manifest" href="'.esc_url(add_query_arg('arc_pwa','manifest',ARC_Config::app_url())).'">';
        echo '<meta name="theme-color" content="#343434"><meta name="application-name" content="'.esc_attr(ARC_Config::name()).'">';
        echo '<link rel="apple-touch-icon" href="'.esc_url(ARC_URL.'assets/pwa-icon-192.png').'">';
    }
    public static function register_worker(): void {
        if(!self::on_live())return;
        $url=add_query_arg('arc_pwa','sw',ARC_Config::app_url());
        $scope=trailingslashit((string)wp_parse_url(ARC_Config::app_url(),PHP_URL_PATH));
        echo '<script>if("serviceWorker" in navigator && window.isSecureContext){window.addEventListener("load",()=>navigator.serviceWorker.register('.wp_json_encode($url).',{scope:'.wp_json_encode($scope).'}).catch(e=>console.warn("Fresh Community PWA:",e.message)));}</script>';
    }
}
