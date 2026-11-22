<?php
/** First-release membership gate: WordPress accounts remain unchanged. */
defined('ABSPATH') || exit;
final class ARC_Access {
    public const APPROVED = '_arc_community_approved';
    public static function boot(): void {
        add_filter('registration_errors',[self::class,'wp_registration_errors'],10,3);
        add_filter('woocommerce_registration_errors',[self::class,'woo_registration_errors'],10,4);
        add_action('user_register',[self::class,'register_member'],80);
        add_filter('rest_pre_dispatch',[self::class,'rest_gate'],10,3);
        add_action('show_user_profile',[self::class,'admin_field']);
        add_action('edit_user_profile',[self::class,'admin_field']);
        add_action('personal_options_update',[self::class,'save_admin']);
        add_action('edit_user_profile_update',[self::class,'save_admin']);
    }
    public static function invite_only(): bool {
        return ARC_Config::get('registration_mode')==='invite';
    }
    public static function member(int $uid=0): bool {
        if(!$uid)$uid=get_current_user_id();
        if($uid<1 || !get_userdata($uid)) return false;
        if(user_can($uid,'manage_options'))return true;
        if(get_user_meta($uid,'_arc_community_suspended',true))return false;
        return !self::invite_only() || (string)get_user_meta($uid,self::APPROVED,true)==='1';
    }
    public static function can_register(): bool {
        return !self::invite_only() || ARC_Invites::has_valid_cookie();
    }
    public static function wp_registration_errors(WP_Error $errors,string $username='',string $email=''): WP_Error {
        if(!self::can_register())$errors->add('arc_invite_required','Tato komunita je přístupná pouze na platnou pozvánku. Otevři odkaz na pozvánku a pokračuj registrací.');
        return $errors;
    }
    public static function woo_registration_errors(WP_Error $errors,$username='',$password='',$email=''): WP_Error {
        // Shop checkout may create a Woo customer, but never silently grants community membership.
        if(function_exists('is_checkout') && is_checkout())return $errors;
        if(!self::can_register())$errors->add('arc_invite_required','Pro vstup do komunity potřebuješ platnou pozvánku.');
        return $errors;
    }
    public static function register_member(int $uid): void {
        if(self::invite_only() && ARC_Invites::has_valid_cookie())
            update_user_meta($uid,self::APPROVED,'1');
    }
    public static function rest_gate($result,WP_REST_Server $server,WP_REST_Request $request) {
        if(!self::invite_only() || is_wp_error($result))return $result;
        $route=(string)$request->get_route();
        if(strpos($route,'/ar-community/v1/')!==0)return $result;
        if(self::member())return $result;
        if($route==='/ar-community/v1/live/invite/accept-link' && $request->get_method()==='POST' && is_user_logged_in())
            return $result; // Existing registered user accepts explicit invitation.
        return new WP_Error('arc_members_only','Tato komunita je přístupná pouze pozvaným členům.',['status'=>is_user_logged_in()?403:401]);
    }
    public static function denied(): string {
        if(is_user_logged_in()) {
            $token=isset($_GET['arc_invite']) ? sanitize_text_field((string)wp_unslash($_GET['arc_invite'])) : '';
            if($token && ARC_Invites::inviter_for_token($token) && !self::member()) {
                wp_enqueue_script('arc-social-pages');
                return '<div class="arc-social-shell" data-arc-social><div class="arc-panel" data-arc-invite-link-banner><p>Byl/a jsi pozván/a do komunity '.esc_html(ARC_Config::name()).'.</p><button type="button" data-arc-accept-invite="'.esc_attr($token).'">Přijmout pozvánku a vstoupit</button></div><p data-arc-social-status role="status"></p></div>';
            }
            return '<div class="arc-wrap"><p>Tvůj účet zatím nemá přístup do uzavřené komunity. Požádej správce o pozvánku.</p></div>';
        }
        $register = get_option('users_can_register') ? wp_registration_url() : (function_exists('wc_get_page_permalink') ? wc_get_page_permalink('myaccount') : wp_login_url());
        return '<div class="arc-wrap"><h2>'.esc_html(ARC_Config::name()).'</h2><p>'.esc_html(ARC_Config::description()).'</p><p>Komunita je nyní pouze pro pozvané členy. Pokud máš pozvánku, otevři její odkaz a dokonči registraci.</p><p><a href="'.esc_url(wp_login_url()).'">Přihlásit se</a> · <a href="'.esc_url($register).'">Registrace</a></p></div>';
    }
    public static function admin_field(WP_User $user): void {
        if(!current_user_can('manage_options'))return;
        echo '<h2>Fresh Community – členství</h2><table class="form-table"><tr><th>Uzavřená komunita</th><td><label><input type="checkbox" name="arc_member_approved" value="1" '.checked((string)get_user_meta($user->ID,self::APPROVED,true),'1',false).'> Povolit členství v komunitě (pro režim pouze na pozvánku)</label>';
        wp_nonce_field('arc_member_approved_'.$user->ID,'arc_member_approved_nonce');
        echo '</td></tr></table>';
    }
    public static function save_admin(int $uid): void {
        if(!current_user_can('manage_options') || !current_user_can('edit_user',$uid))return;
        $nonce=isset($_POST['arc_member_approved_nonce'])?sanitize_text_field((string)wp_unslash($_POST['arc_member_approved_nonce'])):'';
        if(!$nonce || !wp_verify_nonce($nonce,'arc_member_approved_'.$uid))return;
        update_user_meta($uid,self::APPROVED,!empty($_POST['arc_member_approved'])?'1':'0');
    }
}
