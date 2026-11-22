<?php
defined('ABSPATH') || exit;
/** Configurable navigation using the shared WordPress identity. */
final class ARC_Navigation {
    private static bool $floating_printed=false;
    private static bool $live_rendered=false;
    public static function live_rendered(): void { self::$live_rendered=true; }
    /** Respect the installed WP page hierarchy instead of hardcoding /pratele vs /app/pratele. */
    public static function app_link(string $slug): string {
        if($slug==='zpravy')return ARC_Setup::url('messages');
        if($slug==='pratele' && ARC_Setup::id('friends'))return ARC_Setup::url('friends');
        if($slug==='oznameni' && ARC_Setup::id('notifications'))return ARC_Setup::url('notifications');
        if(!in_array($slug,['pratele','oznameni'],true))return ARC_Config::app_url();
        $base=trim((string)wp_parse_url(ARC_Config::app_url(),PHP_URL_PATH),'/');
        foreach([($base ? $base.'/' : '').$slug,$slug] as $path){
            $page=get_page_by_path($path,OBJECT,'page');
            if($page instanceof WP_Post && $page->post_status==='publish')return get_permalink($page);
        }
        return trailingslashit(ARC_Config::app_url()).$slug.'/';
    }
    public static function desktop_bar_enabled(): bool {return is_user_logged_in() && ARC_Access::member() && !is_admin() && !wp_doing_ajax() && (ARC_Config::enabled('global_navigation') || self::community_route());}
    public static function topbar_enabled(): bool {return !is_admin() && !wp_doing_ajax() && (is_user_logged_in() || self::community_route());}
    public static function boot(): void {
        add_action('wp_enqueue_scripts',[self::class,'assets']);
        add_action('wp_head',[self::class,'loading_head'],0);
        // Recovery: render once at the beginning of the body. Do not render
        // the global bar from inside Elementor's header template.
        // Top bar is explicitly placed through the Elementor shortcode, never injected into wp_body_open.
        // Compatibility: the shortcode widget left in Elementor is inert.
        // Remove that widget when editing the global header.
        add_shortcode('ar_live_topbar',[self::class,'topbar']);
        add_action('wp_footer',[self::class,'automatic_bottom'],90);
        add_action('wp_footer',[self::class,'mobile_community_return'],95);
        add_filter('body_class',[self::class,'classes']);
        add_shortcode('ar_live_navigation',[self::class,'bottom']);
    }
    private static function community_route(): bool {
        if(is_admin())return false;
        if(ARC_Config::is_app_page())return class_exists('ARC_PWA') && ARC_PWA::on_live();
        foreach(['wall','account','profile','people','friends','messages','notifications'] as $key){$id=ARC_Setup::id($key);if($id && is_page($id))return true;}
        if(is_page(['pratele','profil','premium','oznameni','ulozene']))return true;
        if(function_exists('is_wc_endpoint_url') && is_wc_endpoint_url('ar-community'))return true;
        // Site's account endpoint may be nested under /app/muj-ucet/ in a custom permalink setup.
        $path=trim((string)wp_parse_url((string)($_SERVER['REQUEST_URI']??''),PHP_URL_PATH),'/');
        if(is_user_logged_in() && preg_match('~(?:^|/)muj-ucet/ar-community(?:/|$)~',$path))return true;
        $page=get_queried_object();
        return $page instanceof WP_Post && (
            has_shortcode($page->post_content,'ar_live') || has_shortcode($page->post_content,'ar_community_profile') ||
            has_shortcode($page->post_content,'ar_community_friends') || has_shortcode($page->post_content,'ar_live_premium') || has_shortcode($page->post_content,'ar_live_notifications'));
    }
    public static function classes(array $classes): array {
        // No global body padding: Elementor controls placement of the optional top bar.
        if(self::community_route()){$classes[]='arc-community-route';}
        if(self::desktop_bar_enabled()){$classes[]='arc-bottom-nav-visible';}
        return $classes;
    }
    public static function assets(): void {
        // Contains only a mobile-only fixed return link; no cache-sensitive data.
        wp_enqueue_style('arc-navigation',ARC_URL.'assets/navigation.css',[],ARC_VERSION);
        if(self::topbar_enabled() || self::community_route())
            wp_enqueue_style('arc-navigation',ARC_URL.'assets/navigation.css',[],ARC_VERSION);
        // Notifications are fetched privately over REST on every front-end page for logged-in members.
        // Public HTML pages remain cacheable; never embed notification contents or an initial badge count.
        if(is_user_logged_in() && !is_admin()) {
            wp_enqueue_style('arc-navigation',ARC_URL.'assets/navigation.css',[],ARC_VERSION);
            wp_enqueue_script('arc-nav-alerts', ARC_URL.'assets/nav-alerts.js', [], ARC_VERSION, true);
            wp_add_inline_script('arc-nav-alerts','window.ARCNav='.wp_json_encode([
                'endpoint'=>esc_url_raw(rest_url('ar-community/v1/live/notifications')),
                'nonce'=>wp_create_nonce('wp_rest'),
                'user'=>get_current_user_id(),
                'inbox'=>self::app_link('oznameni'),
                'interval'=>12000
            ]).';','before');
        }
    }
    public static function loading_head(): void {
        // No full-page overlay: Elementor/theme layout and CSS render normally.
        // Dynamic shortcode components contain their own scoped content loader.
    }
    public static function mobile_community_return(): void {
        if(!ARC_Config::enabled('global_navigation') || is_admin() || is_user_logged_in() || self::community_route() || self::$live_rendered)return;
        if(function_exists('is_checkout') && is_checkout())return;
        echo '<a class="arc-mobile-community-return" href="'.esc_url(ARC_Config::app_url()).'" aria-label="Do komunity '.esc_attr(ARC_Config::name()).'"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M16 21v-2a4 4 0 0 0-4-4H6a4 4 0 0 0-4 4v2"/><circle cx="9" cy="7" r="4"/><path d="M22 21v-2a4 4 0 0 0-3-3.87M16 3.13a4 4 0 0 1 0 7.75"/></svg><span>Do komunity</span></a>';
    }
    public static function topbar_action(): void {
        if(!self::topbar_enabled())return;
        echo self::topbar();
    }
    public static function topbar(): string {
        if(!self::topbar_enabled())return ''; 
        if(!is_user_logged_in()){
            $url=function_exists('wc_get_page_permalink')?wc_get_page_permalink('myaccount'):wp_login_url();
            return '<nav class="arc-userbar arc-userbar-guest" aria-label="Přihlášení do komunity"><div class="arc-userbar-inner"><span class="arc-userbar-mobile-label">'.esc_html(ARC_Config::name()).'</span><div class="arc-userbar-account"><a href="'.esc_url($url).'">Přihlásit se / Registrace</a></div></div></nav>';
        }
        $me=wp_get_current_user();
        $account=class_exists('AR_Community')?AR_Community::community_account_url():home_url('/muj-ucet/');
        $links=[[ARC_Config::name(),ARC_Config::app_url()],['Přátelé',self::app_link('pratele')],['Zprávy',self::app_link('zpravy')],['Oznámení',self::app_link('oznameni')]];
        if(ARC_Config::enabled('show_affiliate') && function_exists('wc_get_account_endpoint_url'))$links[]=['Referenční program',wc_get_account_endpoint_url('fresh-affiliate')];
        $html='<nav class="arc-userbar" aria-label="Rychlá navigace člena"><div class="arc-userbar-inner"><span class="arc-userbar-mobile-label">Přihlášený účet</span><div class="arc-userbar-links">';
        foreach($links as $l)$html.='<a href="'.esc_url($l[1]).'">'.esc_html($l[0]).'</a>';
        $html.='</div><div class="arc-userbar-account"><a href="'.esc_url($account).'" class="arc-userbar-identity">'.esc_html($me->display_name).'</a><a class="arc-userbar-logout" href="'.esc_url(wp_logout_url(home_url('/'))).'" aria-label="Odhlásit se">Odhlásit ↗</a></div></div></nav>';
        return $html;
    }
    public static function automatic_bottom(): void {
        if(self::$floating_printed || !self::desktop_bar_enabled())return;
        echo self::bottom();
    }
    public static function bottom(): string {
        if(!self::desktop_bar_enabled() || self::$floating_printed)return '';
        self::$floating_printed=true;
        wp_enqueue_style('arc-navigation',ARC_URL.'assets/navigation.css',[],ARC_VERSION);
        $profile=ARC_Data::member_url(get_current_user_id());
        $items=[['home','Zeď',ARC_Config::app_url()],['users','Přátelé',self::app_link('pratele')],['message-circle','Zprávy',self::app_link('zpravy')],['bell','Oznámení',self::app_link('oznameni')],['user','Profil',$profile]];
        if(ARC_Config::enabled('show_premium'))$items[]=['star','Premium',home_url('/premium/')];
        $icons=[
          'home'=>'<path d="m3 10 9-7 9 7v10a1 1 0 0 1-1 1h-5v-7H9v7H4a1 1 0 0 1-1-1z"/>',
          'users'=>'<path d="M16 21v-2a4 4 0 0 0-4-4H6a4 4 0 0 0-4 4v2"/><circle cx="9" cy="7" r="4"/><path d="M22 21v-2a4 4 0 0 0-3-3.87M16 3.13a4 4 0 0 1 0 7.75"/>',
          'user'=>'<circle cx="12" cy="8" r="4"/><path d="M4 21v-2a8 8 0 0 1 16 0v2"/>',
          'bell'=>'<path d="M18 8a6 6 0 0 0-12 0c0 7-3 7-3 9h18c0-2-3-2-3-9"/><path d="M10 21h4"/>',
          'star'=>'<path d="m12 2 3 6.4 7 1-5 4.9 1.2 7-6.2-3.3-6.2 3.3 1.2-7-5-4.9 7-1z"/>',
          'message-circle'=>'<path d="M21 11.5a8.38 8.38 0 0 1-.9 3.8 8.5 8.5 0 0 1-7.6 4.7 8.4 8.4 0 0 1-3.8-.9L3 21l1.9-5.7a8.4 8.4 0 0 1-.9-3.8A8.5 8.5 0 0 1 12.5 3H13a8.5 8.5 0 0 1 8 8v.5z"/>',
        ];
        $route=ARC_Config::is_app_page()?'home':(is_page('pratele')?'users':(is_page('premium')?'star':(is_page('oznameni')?'bell':(is_page('profil')?'user':''))));
        if(ARC_Setup::id('messages') && is_page(ARC_Setup::id('messages')))$route='message-circle';
        if(function_exists('is_wc_endpoint_url')&&is_wc_endpoint_url('ar-community'))$route='user';
        $currentPath=trim((string)wp_parse_url((string)($_SERVER['REQUEST_URI']??''),PHP_URL_PATH),'/');
        if(is_user_logged_in() && preg_match('~(?:^|/)muj-ucet/ar-community(?:/|$)~',$currentPath))$route='user';
        $html='<nav class="arc-floating-nav" aria-label="Navigace '.esc_attr(ARC_Config::name()).'">';
        foreach($items as $item){
            [$icon,$title,$url]=$item;
            $active=$route===$icon;
            $html.='<a href="'.esc_url($url).'" class="'.($active?'is-active':'').'"'.($active?' aria-current="page"':'').'>' .
              '<svg class="arc-nav-icon" width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.9" stroke-linejoin="round" stroke-linecap="round" aria-hidden="true">'.$icons[$icon].'</svg>'.
              '<span class="arc-nav-label">'.esc_html($title).'</span>'.($icon==='bell'?'<span class="arc-nav-alert-count" data-arc-nav-count hidden></span>':'').'</a>';
        }
        return $html.'</nav>';
    }
}
