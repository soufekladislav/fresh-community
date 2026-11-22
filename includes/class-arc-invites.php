<?php
/** Invitations into a social community. Keeps Fresh Affiliate's first-touch attribution intact. */
defined('ABSPATH') || exit;
final class ARC_Invites {
    private const COOKIE='arc_community_invite';
    private const TTL=2592000; // 30 days
    private static function key(string $token): string { return 'arc_join_'.hash('sha256',$token); }
    public static function issue(int $inviter): string {
        if(ARC_Profile::visibility($inviter)==='private')return '';
        $token=bin2hex(random_bytes(24));
        set_transient(self::key($token),$inviter,self::TTL);
        $url=add_query_arg('arc_invite',$token,home_url('/muj-ucet/'));
        if(class_exists('Fresh_Affiliate_DB') && class_exists('Fresh_Affiliate_Tracker')) {
            $affiliate=Fresh_Affiliate_DB::get_affiliate_by_user($inviter);
            if($affiliate && (string)$affiliate->status==='active') {
                $url=Fresh_Affiliate_Tracker::referral_url($affiliate,$url);
            }
        }
        return $url;
    }
    private static function token(): string {
        $raw=$_COOKIE[self::COOKIE]??'';
        return is_string($raw) && preg_match('/^[a-f0-9]{48}$/D',$raw)?$raw:'';
    }
    public static function inviter_for_token(string $token): int {
        if(!preg_match('/^[a-f0-9]{48}$/D',$token))return 0;
        return self::inviter($token);
    }
    private static function clear_cookie(): void {
        if(!headers_sent())setcookie(self::COOKIE,'',['expires'=>time()-3600,'path'=>COOKIEPATH?:'/','secure'=>is_ssl(),'httponly'=>true,'samesite'=>'Lax']);
        unset($_COOKIE[self::COOKIE]);
    }
    private static function inviter(string $token): int {
        return $token?(int)get_transient(self::key($token)):0;
    }
    public static function capture(): void {
        if(is_admin() || wp_doing_ajax())return;
        $token=isset($_GET['arc_invite']) && is_scalar($_GET['arc_invite']) ? sanitize_text_field(wp_unslash($_GET['arc_invite'])) : '';
        if(!preg_match('/^[a-f0-9]{48}$/D',$token) || !self::inviter($token))return;
        if(is_user_logged_in()){
            // Never accept a friendship on a GET/link preview: require an explicit REST POST.
            $path=(string)wp_parse_url((string)($_SERVER['REQUEST_URI']??''),PHP_URL_PATH);
            $friendsPath=(string)wp_parse_url(ARC_Navigation::app_link('pratele'),PHP_URL_PATH);
            if(untrailingslashit($path)!==untrailingslashit($friendsPath)){
                nocache_headers();header('Referrer-Policy: no-referrer');
                wp_safe_redirect(add_query_arg('arc_invite',$token,ARC_Navigation::app_link('pratele')),302);exit;
            }
            return;
        }
        if(headers_sent())return;
        setcookie(self::COOKIE,$token,[
            'expires'=>time()+self::TTL,'path'=>COOKIEPATH?:'/',
            'secure'=>is_ssl(),'httponly'=>true,'samesite'=>'Lax'
        ]);
        $_COOKIE[self::COOKIE]=$token;
    }
    public static function notice(): void {
        $inviter=self::inviter(self::token());
        if(!$inviter || is_user_logged_in())return;
        echo '<div class="woocommerce-info">Pozvánka do Adrenaline Live od '.esc_html(ARC_Profile::display_alias($inviter)).'. Dokončením registrace se stanete přáteli v jeho komunitě. Pokud je zvoucí také referenčním partnerem, uplatní se pravidla referenčního programu Adrenaline Race.</div>';
    }
    public static function on_register(int $uid): void {
        $token=self::token();$inviter=self::inviter($token);
        if(!$inviter || $inviter===$uid || !get_userdata($inviter))return;
        // Explicit invitation link: create the connection only for the newly-created account.
        global $wpdb;$table=ARC_Relations::table();
        $result=$wpdb->query($wpdb->prepare("INSERT INTO $table (user_id,target_id,kind,status,created_at) VALUES (%d,%d,'connection','accepted',%s) ON DUPLICATE KEY UPDATE status='accepted'",$inviter,$uid,current_time('mysql',true)));
        if($result===false || !ARC_Relations::connected($inviter,$uid))return;
        ARC_Notifications::accepted($uid,$inviter);
        // Fresh Affiliate is the only attribution authority; never rewrite an existing lifetime assignment.
        if(class_exists('Fresh_Affiliate_Lifetime') && class_exists('Fresh_Affiliate_DB')){
            $person=get_userdata($uid);
            if($person && !Fresh_Affiliate_Lifetime::get($uid,$person->user_email)){
                // An explicit invitation is an affirmative choice of inviter.
                // Existing lifetime attribution is protected by the get() guard above.
                $chosen=Fresh_Affiliate_DB::get_affiliate_by_user($inviter);
                if(!$chosen || empty($chosen->is_partner) || $chosen->status!=='active')$chosen=null;
                if($chosen && (int)$chosen->user_id!==$uid){
                    Fresh_Affiliate_Lifetime::assign($uid,$person->user_email,(int)$chosen->id,'arc_invite',0);
                }
            }
        }
        delete_transient(self::key($token));
        self::clear_cookie();
    }
    /** An invited existing member must confirm after clicking the link. */
    public static function accept_link(int $uid,string $token): array|WP_Error {
        $inviter=self::inviter_for_token($token);
        if(!$inviter || $inviter===$uid || !get_userdata($inviter) || !get_userdata($uid))return new WP_Error('arc_invite_expired','Pozvánka vypršela nebo je neplatná.',['status'=>400]);
        if(!ARC_Relations::connected($inviter,$uid)){
            $request=ARC_Relations::invite_known_email($inviter,$uid);
            if(is_wp_error($request))return $request;
            $accepted=ARC_Relations::change($uid,$inviter,'accept');
            if(is_wp_error($accepted)){
                // The invited user might already have sent a reverse invitation.
                $accepted=ARC_Relations::change($inviter,$uid,'accept');
            }
            if(is_wp_error($accepted) || !ARC_Relations::connected($inviter,$uid))return new WP_Error('arc_invite_confirm','Pozvánku se nepodařilo potvrdit.',['status'=>409]);
        }
        $affiliateResult=ARC_Integrations::attribute_accepted_invite($inviter,$uid);
        if($affiliateResult==='other')ARC_Notifications::affiliate_conflict($uid,$inviter);
        delete_transient(self::key($token));self::clear_cookie();
        return ['ok'=>true,'message'=>'Pozvánka přijata, nyní jste přátelé.'];
    }
    /** On sign-in of an *existing* WP user, create a request, not a friendship. */
    public static function on_login(string $username,WP_User $user): void {
        $token=self::token();$inviter=self::inviter($token);$uid=(int)$user->ID;
        if(!$inviter || !$uid || $inviter===$uid || !get_userdata($inviter))return;
        $r=ARC_Relations::invite_known_email($inviter,$uid);
        if(!is_wp_error($r)){delete_transient(self::key($token));self::clear_cookie();}
    }
    public static function mail(int $uid,string $email): bool {
        $url=self::issue($uid);
        if($url==='')return false;
        $name=ARC_Profile::display_alias($uid);
        return wp_mail($email,'Pozvánka do Adrenaline Live',
          "Ahoj,\n\n".$name." tě zve do sportovní komunity Adrenaline Live.\n".
          "Otevři pozvánku a vytvoř účet: ".$url."\n\n".
          "Při registraci se automaticky připojíš ke zvoucímu jako přítel.\n".
          "Pokud je zvoucí referenční partner, mohou platit pravidla referenčního programu.\n\n".
          "Adrenaline Race\n");
    }
}
