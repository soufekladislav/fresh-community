<?php
defined('ABSPATH') || exit;
final class ARC_Live_Shortcodes {
    public static function register(): void {
        add_shortcode('ar_live',[self::class,'app']);
        add_shortcode('ar_community_wall',[self::class,'app']);
        add_shortcode('ar_live_premium',[ARC_Premium::class,'render']);
    }
    public static function app(): string {
        if(isset($_GET['ar_member'])) return ARC_Shortcodes::profile();
        ARC_Shortcodes::assets(); // One existing favorite handler shared with WooCommerce and Saved.
        wp_enqueue_style('arc-live',ARC_URL.'assets/live.css',[],ARC_VERSION);
        wp_enqueue_script('arc-live',ARC_URL.'assets/live.js',[],ARC_VERSION,true);
        wp_add_inline_script('arc-live','window.ARCLIVE='.wp_json_encode([
            'root'=>esc_url_raw(rest_url('ar-community/v1/live/')),
            'nonce'=>wp_create_nonce('wp_rest'),'loggedIn'=>is_user_logged_in(),'user'=>get_current_user_id(),
            'login'=>function_exists('wc_get_page_permalink')?wc_get_page_permalink('myaccount'):wp_login_url(),
            'commerce'=>function_exists('wc_get_page_permalink')?wc_get_page_permalink('myaccount'):wp_login_url(),
            'shop'=>ARC_Config::shop_url(),
            'name'=>ARC_Config::name(),
            'showShop'=>ARC_Config::enabled('show_shop') && function_exists('wc_get_products'),
            'showYouTube'=>ARC_Config::enabled('show_youtube'),
            'savedUrl'=>ARC_Saved::url('posts'),
            'friendsUrl'=>ARC_Navigation::app_link('pratele'),
            'notificationsUrl'=>ARC_Navigation::app_link('oznameni'),
        ]).';','before');
        ARC_Navigation::live_rendered();
        wp_enqueue_style('arc-navigation',ARC_URL.'assets/navigation.css',[],ARC_VERSION);
        ob_start();include ARC_PATH.'templates/live.php';return (string)ob_get_clean();
    }
}
