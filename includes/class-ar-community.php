<?php
defined('ABSPATH') || exit;
final class AR_Community {
    public static function activate(): void {
        ARC_Data::register_post_type();
        ARC_Wall::register();
        self::db_create();
        add_rewrite_endpoint('ar-community', EP_ROOT | EP_PAGES);
        flush_rewrite_rules(false);
    }
    public static function deactivate(): void { flush_rewrite_rules(false); }
    public static function boot(): void {
        ARC_Google::boot();
        ARC_Account::boot();
        add_action('template_redirect',[ARC_Invites::class,'capture'],2);
        add_action('woocommerce_before_customer_login_form',[ARC_Invites::class,'notice'],5);
        add_action('user_register',[ARC_Invites::class,'on_register'],90);
        add_action('wp_login',[ARC_Invites::class,'on_login'],40,2);
        ARC_Navigation::boot();
        ARC_Feedback::boot();
        ARC_Onboarding::boot();
        ARC_Notifications::boot();
        ARC_PWA::boot();
        ARC_Management::boot();
        ARC_User_Cleanup::boot();
        add_action('init', [ARC_Data::class, 'register_post_type'], 12);
        add_action('init', [ARC_Wall::class, 'register'], 12);
        add_action('init', [ARC_Live_Shortcodes::class, 'register']);
        add_action('user_register', [ARC_Profile::class, 'initialize'], 20);
        // Checkout and community registrations share the same three visibility states.
        // New accounts start private; no additional 'shop-only' classification.
        add_action('wp_login', static function($user_login, $user) { if ($user instanceof WP_User) ARC_Profile::initialize((int)$user->ID); },20,2);
        add_action('init', [ARC_Profile::class,'initialize_current'], 50);
        add_action('template_redirect', [self::class, 'profile_nocache']);
        add_action('rest_api_init', [ARC_Live_API::class,'routes']);
        ARC_Wall_Private_Media::boot();
        add_action('init', [self::class, 'upgrade'], 8);
        add_action('transition_post_status', [ARC_Notifications::class, 'article_published'], 20, 3);
        add_action('show_user_profile',[ARC_Profile::class,'admin_fields']);
        add_action('edit_user_profile',[ARC_Profile::class,'admin_fields']);
        add_action('personal_options_update',[ARC_Profile::class,'save_admin']);
        add_action('edit_user_profile_update',[ARC_Profile::class,'save_admin']);
        add_action('init', [self::class, 'woo_endpoint'], 20);
        add_filter('woocommerce_account_menu_items', [self::class, 'woo_menu'], 36);
        // The WooCommerce account root is a standalone welcome dashboard; never redirect it.
        add_action('template_redirect',[self::class,'woo_prepare_dashboard'],25);
        // Direct community signup: prove ownership of the address through Woo's
        // one-time password setup link before the first login. Checkout is untouched.
        add_filter('woocommerce_registration_auth_new_customer',[self::class,'community_auto_login'],100,2);
        add_filter('wp_authenticate_user',[self::class,'verify_community_email'],30,2);
        add_filter('woocommerce_registration_redirect',[self::class,'woo_registration_redirect'],100);
        add_action('woocommerce_created_customer',[self::class,'customer_password_created'],30,3);
        add_action('after_password_reset',[self::class,'password_was_reset'],10,2);
        add_action('woocommerce_customer_reset_password',[self::class,'woo_password_was_reset'],10,1);
        add_action('woocommerce_account_ar-community_endpoint', [self::class, 'woo_render']);
        add_action('rest_api_init', [ARC_API::class, 'routes']);
        add_action('init', [ARC_Shortcodes::class, 'register']);
        add_action('init', [ARC_Saved::class, 'ensure_page'], 60);
        add_action('admin_menu', [ARC_Admin::class, 'menu']);
        add_action('admin_post_arc_save_settings', [ARC_Admin::class, 'save']);
        add_action('admin_post_arc_initialize_profiles', [ARC_Admin::class, 'initialize_profiles']);
        add_action('admin_post_arc_move_legacy_app', [ARC_Admin::class, 'move_legacy_app']);
        add_action('wp_enqueue_scripts', [ARC_Shortcodes::class, 'assets_register']);
        add_action('wp_enqueue_scripts', [ARC_Saved::class, 'enqueue_header_counter'], 30);
        // Style enqueue must happen before wp_head, even if a shortcode is rendered later.
        add_action('wp_enqueue_scripts', static function(): void {
            $path=trim((string)wp_parse_url((string)($_SERVER['REQUEST_URI']??''),PHP_URL_PATH),'/');
            if(is_page(['pratele','oznameni']) || preg_match('~(?:^|/)app/(?:pratele|oznameni)(?:/|$)~',$path) || is_singular('post') || is_home() || is_archive())
                wp_enqueue_style('arc-social-pages',ARC_URL.'assets/social-pages.css',[],ARC_VERSION);
            // Elementor may execute a shortcode after wp_head; preload layout CSS here.
            if(ARC_Config::is_app_page()) {
                wp_enqueue_style('arc-live',ARC_URL.'assets/live.css',[],ARC_VERSION);
                wp_enqueue_style('arc-community');
            }
            if((function_exists('is_account_page') && is_account_page()) || is_page(['profil','pratele','premium','oznameni']) || preg_match('~(?:^|/)app/(?:profil|pratele|oznameni)(?:/|$)|(?:^|/)muj-ucet/ar-community(?:/|$)~',$path)) {
                wp_enqueue_style('arc-community');
                wp_enqueue_style('arc-social-pages');
                wp_enqueue_style('arc-onboarding',ARC_URL.'assets/onboarding.css',[],ARC_VERSION);
                wp_enqueue_style('arc-install-pwa',ARC_URL.'assets/install-pwa.css',[],ARC_VERSION);
            }
        }, 20);
        add_filter('the_content', [self::class, 'guard_collection_content'], 20);
    }
    public static function profile_nocache(): void {
        $path=trim((string)wp_parse_url((string)($_SERVER['REQUEST_URI']??''),PHP_URL_PATH),'/');
        if(isset($_GET['ar_member']) || ARC_Config::is_app_page() || is_page(['profil','pratele','premium','oznameni','ulozene']) || (function_exists('is_wc_endpoint_url') && is_wc_endpoint_url('ar-community')) || (is_user_logged_in() && preg_match('~(?:^|/)muj-ucet/ar-community(?:/|$)~',$path))){
            if(!defined('DONOTCACHEPAGE'))define('DONOTCACHEPAGE',true);
            nocache_headers();
        }
    }
    public static function upgrade(): void {
        $installed = (string)get_option('arc_db_version');
        if ($installed === ARC_VERSION) return;
        // 10.3 only changes UI/PWA assets: avoid an unnecessary dbDelta run on every upgrade.
        if (in_array($installed, ['0.2.0-beta.10.2','0.2.0-beta.10.3','0.2.0-beta.10.4'], true)) {
            update_option('arc_db_version', ARC_VERSION, false);
            return;
        }
        self::db_create();
    }
    private static function db_create(): void {
        global $wpdb;require_once ABSPATH.'wp-admin/includes/upgrade.php';
        $charset=$wpdb->get_charset_collate();
        $a=$wpdb->prefix.'arc_relations';$c=$wpdb->prefix.'arc_wall_comments';$r=$wpdb->prefix.'arc_wall_reactions';$n=$wpdb->prefix.'arc_notifications';$cr=$wpdb->prefix.'arc_wall_comment_likes';
        dbDelta("CREATE TABLE $a (
            id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
            user_id bigint(20) unsigned NOT NULL,
            target_id bigint(20) unsigned NOT NULL,
            kind varchar(20) NOT NULL,
            status varchar(20) NOT NULL,
            created_at datetime NOT NULL,
            PRIMARY KEY  (id),
            UNIQUE KEY edge (user_id,target_id,kind),
            KEY inbound (target_id,kind,status)
        ) $charset;");
        dbDelta("CREATE TABLE $c (
            id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
            post_id bigint(20) unsigned NOT NULL,
            user_id bigint(20) unsigned NOT NULL,
            body text NOT NULL,
            parent_id bigint(20) unsigned NOT NULL DEFAULT 0,
            status varchar(20) NOT NULL,
            created_at datetime NOT NULL,
            PRIMARY KEY  (id),
            KEY post_id (post_id,status),
            KEY post_parent (post_id,parent_id)
        ) $charset;");
        dbDelta("CREATE TABLE $cr (
            id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
            comment_id bigint(20) unsigned NOT NULL,
            user_id bigint(20) unsigned NOT NULL,
            created_at datetime NOT NULL,
            PRIMARY KEY  (id),
            UNIQUE KEY comment_reaction (comment_id,user_id),
            KEY user_id (user_id)
        ) $charset;");
        dbDelta("CREATE TABLE $r (
            id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
            post_id bigint(20) unsigned NOT NULL,
            user_id bigint(20) unsigned NOT NULL,
            created_at datetime NOT NULL,
            PRIMARY KEY  (id),
            UNIQUE KEY reaction (post_id,user_id)
        ) $charset;");
        dbDelta("CREATE TABLE $n (
            id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
            user_id bigint(20) unsigned NOT NULL,
            actor_id bigint(20) unsigned NOT NULL,
            event_key varchar(140) NOT NULL,
            kind varchar(30) NOT NULL,
            object_id bigint(20) unsigned NOT NULL DEFAULT 0,
            is_read tinyint(1) NOT NULL DEFAULT 0,
            created_at datetime NOT NULL,
            PRIMARY KEY  (id),
            UNIQUE KEY recipient_event (user_id,event_key),
            KEY inbox (user_id,is_read,created_at)
        ) $charset;");
        update_option('arc_db_version',ARC_VERSION,false);
    }
    public static function woo_endpoint(): void { add_rewrite_endpoint('ar-community', EP_ROOT | EP_PAGES); }
    public static function woo_menu(array $items): array {
        if (!ARC_Config::enabled('woo_dashboard') || !function_exists('wc_get_account_endpoint_url')) return $items;
        // One canonical community home: Woo's default /muj-ucet/ dashboard.
        // The old /muj-ucet/ar-community/ endpoint stays live for existing links.
        $out=['dashboard'=>'Můj účet','ar-community'=>'Moje komunita'];
        foreach($items as $key=>$value) if(!in_array($key,['dashboard','ar-community'],true)) $out[$key]=$value;
        return $out;
    }
    /** Only the exact My Account root is a community dashboard. All endpoints remain Woo-owned. */
    public static function is_woo_dashboard_root(): bool {
        if (!ARC_Config::enabled('woo_dashboard') || !is_user_logged_in() || !function_exists('is_account_page') || !is_account_page()
            || !function_exists('wc_get_page_permalink')) return false;
        $request_path = trailingslashit((string)wp_parse_url((string)($_SERVER['REQUEST_URI'] ?? ''), PHP_URL_PATH));
        $account_path = trailingslashit((string)wp_parse_url((string)wc_get_page_permalink('myaccount'), PHP_URL_PATH));
        if (!$account_path || $request_path !== $account_path) return false;
        if (function_exists('is_wc_endpoint_url') && is_wc_endpoint_url()) return false;
        // Admin-post checkout actions and password-reset URLs must not be replaced.
        if (isset($_GET['action']) || isset($_GET['key']) || isset($_GET['lost-password']) || isset($_GET['reset-key'])) return false;
        return true;
    }
    /** Replace only Woo's default dashboard output (not Woo account routing or navigation). */
    public static function woo_prepare_dashboard(): void {
        if(!self::is_woo_dashboard_root()) return;
        // Woo's normal handler renders a generic Hello/Orders introduction. We
        // preserve the Woo account navigation and every other account endpoint.
        if(remove_action('woocommerce_account_content','woocommerce_account_content',10)) {
            add_action('woocommerce_account_content',[self::class,'woo_dashboard_render'],10);
        } else {
            // Non-standard Woo template: show welcome via the supported dashboard hook.
            add_action('woocommerce_account_dashboard',[self::class,'woo_dashboard_render'],5);
        }
        if (!defined('DONOTCACHEPAGE')) define('DONOTCACHEPAGE',true);
        nocache_headers();
    }
    public static function woo_dashboard_render(): void {
        if(!is_user_logged_in()) return;
        echo ARC_Onboarding::dashboard(get_current_user_id());
        // Keep the dashboard hook for independent integrations (notably Fresh Affiliate).
        // In the unusual fallback mode we're called BY that hook, so avoid recursion.

    }
    /** Prefer the existing community account page when it is published.
     *  The legacy /muj-ucet/ login/registration form remains in place for guests.
     */
    public static function community_account_url(): string {
        $page=ARC_Config::legacy_site() ? get_page_by_path('app/muj-ucet', OBJECT, 'page') : null;
        if($page instanceof WP_Post && $page->post_status==='publish') {
            $url=get_permalink($page);
            if(is_string($url) && $url) return $url;
        }
        if(function_exists('wc_get_page_permalink')) {
            $url=wc_get_page_permalink('myaccount');
            if(is_string($url) && $url) return $url;
        }
        return ARC_Config::app_url();
    }
    /** After registration, an already logged-in user must not remain on the old registration form.
     *  Keep invitation processing (priority 2), checkout and account endpoints untouched.
     */
    public static function legacy_account_redirect(): void {
        if(!is_user_logged_in() || is_admin() || wp_doing_ajax())return;
        if(!in_array(strtoupper((string)($_SERVER['REQUEST_METHOD']??'GET')),['GET','HEAD'],true))return;
        if(function_exists('is_checkout') && is_checkout())return;
        if(isset($_GET['action']) || isset($_GET['key']) || isset($_GET['reset-key']) || isset($_GET['lost-password']) || isset($_GET['show-reset-password']) || isset($_GET['wc-reset-password']) || isset($_GET['wc-ajax']))return;
        $path=trailingslashit((string)wp_parse_url((string)($_SERVER['REQUEST_URI']??''),PHP_URL_PATH));
        $legacy=trailingslashit((string)wp_parse_url((string)home_url('/muj-ucet/'),PHP_URL_PATH));
        if($path!==$legacy)return; // never touch /muj-ucet/fresh-affiliate/ or orders
        $target=self::community_account_url();
        $target_path=trailingslashit((string)wp_parse_url($target,PHP_URL_PATH));
        if(!$target_path || $target_path===$legacy)return;
        // Invitation and affiliate cookies were captured before user creation.
        // Do not carry the token into the personal dashboard URL.
        if(!defined('DONOTCACHEPAGE'))define('DONOTCACHEPAGE',true);
        nocache_headers();
        wp_safe_redirect($target,302,'Fresh Community');exit;
    }
    private static function is_community_registration_request(): bool {
        if (empty($_POST['register']) || !function_exists('is_account_page') || !is_account_page()) return false;
        if (function_exists('is_checkout') && is_checkout()) return false;
        return true;
    }
    public static function community_auto_login(bool $auto, int $uid): bool {
        return ARC_Config::enabled('woo_dashboard') && self::is_community_registration_request() ? false : $auto;
    }
    public static function verify_community_email($user, $password) {
        if ($user instanceof WP_User && get_user_meta($user->ID,'_arc_email_confirmation_pending',true)==='1') {
            return new WP_Error('arc_confirm_email','Nejdříve otevři odkaz v registračním e-mailu a nastav si heslo. Potom se můžeš přihlásit.');
        }
        return $user;
    }
    /** New Woo customers who used generated passwords should see a reassuring notice. */
    public static function customer_password_created(int $user_id, $customer_data, $generated): void {
        if(ARC_Config::enabled('woo_dashboard') && self::is_community_registration_request()) {
            update_user_meta($user_id,'_arc_email_confirmation_pending','1');
            if(function_exists('wc_add_notice')) wc_add_notice('Účet jsme založili. Na zadaný e-mail jsme poslali odkaz pro nastavení hesla. Otevři jej, nastav heslo a pak se přihlas. Zkontroluj také spam.','success');
        }
        if($generated)update_user_meta($user_id,'_arc_password_setup_notice',time());
    }
    public static function password_was_reset($user, $new_password): void {
        if($user instanceof WP_User){delete_user_meta((int)$user->ID,'_arc_password_setup_notice');delete_user_meta((int)$user->ID,'_arc_email_confirmation_pending');}
    }
    public static function woo_password_was_reset($user): void {
        if($user instanceof WP_User){delete_user_meta((int)$user->ID,'_arc_password_setup_notice');delete_user_meta((int)$user->ID,'_arc_email_confirmation_pending');}
        elseif(is_numeric($user)){delete_user_meta((int)$user,'_arc_password_setup_notice');delete_user_meta((int)$user,'_arc_email_confirmation_pending');}
    }
    /** Woo registration success, excluding a user signing up as part of checkout. */
    public static function woo_registration_redirect($url): string {
        if(!ARC_Config::enabled('woo_dashboard') || (function_exists('is_checkout') && is_checkout()))return (string)$url;
        return function_exists('wc_get_page_permalink') ? (string)wc_get_page_permalink('myaccount') : ARC_Config::app_url();
    }
    public static function woo_render(): void { echo do_shortcode('[ar_community_account]'); }
    public static function guard_collection_content($content) { return $content; }
}
