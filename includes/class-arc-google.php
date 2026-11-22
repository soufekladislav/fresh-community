<?php
/**
 * Sign in with Google for the existing WordPress identity.
 * Google Identity Services posts a signed ID token to admin-post.php.
 * No OAuth access tokens, Google passwords, new account store or affiliate rewrites.
 */
defined('ABSPATH') || exit;
final class ARC_Google {
    private const SUB_META = '_arc_google_sub';
    private const JWKS_CACHE = 'arc_google_jwks_v1';

    public static function boot(): void {
        add_shortcode('arc_google_login', [self::class, 'button']);
        add_action('admin_post_nopriv_arc_google_login', [self::class, 'callback']);
        add_action('admin_post_arc_google_login', [self::class, 'callback']);
        add_action('wp_ajax_arc_google_link', [self::class, 'link_ajax']);
        add_action('woocommerce_login_form_end', [self::class, 'woo_button']);
        // One Google sign-in button under login, also usable for new registration.
        // No second duplicate in the WooCommerce registration column.
        add_action('woocommerce_edit_account_form_end', [self::class, 'woo_link']);
    }

    public static function client_id(): string {
        $v = defined('ARC_GOOGLE_CLIENT_ID') ? (string)constant('ARC_GOOGLE_CLIENT_ID') : (string)get_option('arc_google_client_id', '');
        return preg_match('/^[0-9]+-[a-z0-9-]+\.apps\.googleusercontent\.com$/i', $v) ? $v : '';
    }
    public static function callback_uri(): string {
        return admin_url('admin-post.php?action=arc_google_login');
    }

    public static function button(): string {
        if (!self::client_id()) return '';
        if (is_user_logged_in() && get_user_meta(get_current_user_id(), self::SUB_META, true)) {
            return '<p class="arc-google-linked">Google účet je propojený s tvým WP účtem.</p>';
        }
        wp_enqueue_script('arc-google-gsi', 'https://accounts.google.com/gsi/client?hl=cs', [], null, true);
        if (is_user_logged_in()) {
            wp_enqueue_script('arc-google-link', ARC_URL.'assets/google-link.js', ['arc-google-gsi'], ARC_VERSION, true);
            static $link_js = false;
            if (!$link_js) {
                wp_add_inline_script('arc-google-link', 'window.ARC_GOOGLE_LINK='.wp_json_encode([
                    'clientId'=>self::client_id(),
                    'ajaxUrl'=>admin_url('admin-ajax.php'),
                    'nonce'=>wp_create_nonce('arc_google_link')
                ]).';', 'before');
                $link_js = true;
            }
        }
        $destination = is_admin() ? (function_exists('wc_get_page_permalink') ? wc_get_page_permalink('myaccount') : home_url('/')) : home_url((string)wp_unslash($_SERVER['REQUEST_URI'] ?? '/'));
        $destination = wp_validate_redirect($destination, home_url('/'));
        static $rendered = false;
        $html = '';
        if (isset($_GET['arc_google_error'])) {
            $messages = [
                'already_exists'=>'Tento e-mail už má účet Adrenaline Race. Přihlas se původním způsobem a v profilu si Google propoj.',
                'email_mismatch'=>'Google účet má jiný e-mail než přihlášený účet Adrenaline Race. Zvol Google účet se stejným e-mailem.',
                'registrations_closed'=>'Registrace je aktuálně vypnutá. Přihlášení existujících účtů je dostupné.',
                'already_linked'=>'Google účet už je propojený s jiným účtem, nebo je propojení nejednoznačné.',
                'rate'=>'Příliš mnoho pokusů. Opakuj přihlášení později.',
            ];
            $key = sanitize_key((string)wp_unslash($_GET['arc_google_error']));
            $html .= '<p role="alert" class="arc-google-error">'.esc_html($messages[$key]??'Přihlášení přes Google se nepodařilo. Zkus to znovu.').'</p>';
        }
        if (isset($_GET['arc_google_success'])) $html .= '<p role="status">Google účet je připojený.</p>';
        if (is_user_logged_in()) {
            $html .= '<div class="arc-google-auth" data-arc-google-link><p>Propojit Google účet s tímto WP účtem. Google může nabídnout naposledy použitý účet z prohlížeče; musí mít stejný e-mail jako aktuální WP účet.</p><div data-arc-google-target></div><p role="status" data-arc-google-link-msg></p></div>';
            return $html;
        }
        if (!$rendered) {
            $html .= '<div id="g_id_onload" data-client_id="'.esc_attr(self::client_id()).'" data-ux_mode="redirect" data-login_uri="'.esc_url(self::callback_uri()).'" data-auto_prompt="false"></div>';
            $rendered = true;
        }
        $html .= '<div class="arc-google-auth">';
        if (is_user_logged_in()) $html .= '<p><strong>Propojit Google účet</strong> (stejný ověřený e-mail jako u současného WP účtu).</p>';
        $html .= '<div class="g_id_signin" data-type="standard" data-size="large" data-theme="outline" data-text="continue_with" data-shape="rectangular" data-locale="cs" data-state="'.esc_attr($destination).'"></div>';
        $html .= '</div>';
        return $html;
    }
    public static function woo_button(): void { if (!is_user_logged_in()) echo '<div class="arc-google-login-entry"><p><strong>Přihlášení / registrace s Google účtem</strong></p>'.self::button().'</div>'; }
    public static function woo_link(): void { if (is_user_logged_in()) echo '<div class="arc-google-woo"><h3>Propojení s Google</h3>'.self::button().'</div>'; }

    public static function link_ajax(): void {
        if (!is_user_logged_in() || !check_ajax_referer('arc_google_link', 'nonce', false))
            wp_send_json_error(['message'=>'Přihlas se znovu a opakuj propojení.'], 403);
        $jwt = isset($_POST['credential']) ? (string)wp_unslash($_POST['credential']) : '';
        if (!$jwt || strlen($jwt)>12000) wp_send_json_error(['message'=>'Chybí platné potvrzení od Google.'], 400);
        $claims = self::verify_id_token($jwt);
        if (is_wp_error($claims) || empty($claims['email_verified']) || !in_array($claims['email_verified'], [true, 'true', '1', 1], true))
            wp_send_json_error(['message'=>'Google účet se nepodařilo ověřit.'], 403);
        $email=sanitize_email((string)($claims['email']??''));
        if (!is_email($email) || strcasecmp($email,(string)wp_get_current_user()->user_email)!==0)
            wp_send_json_error(['message'=>'Pro propojení musí mít Google účet stejný e-mail jako účet Adrenaline Race.'], 409);
        $sub=(string)($claims['sub']??'');
        $matches=get_users(['meta_key'=>self::SUB_META, 'meta_value'=>$sub, 'fields'=>'ID', 'number'=>2]);
        foreach ($matches as $linked) {
            if ((int)$linked!==get_current_user_id()) wp_send_json_error(['message'=>'Google účet už je propojený s jiným uživatelem.'], 409);
        }
        $current_sub=(string)get_user_meta(get_current_user_id(), self::SUB_META, true);
        if ($current_sub && !hash_equals($current_sub,$sub))
            wp_send_json_error(['message'=>'Tento WP účet už má jiné Google propojení.'], 409);
        update_user_meta(get_current_user_id(), self::SUB_META, $sub);
        wp_send_json_success(['message'=>'Účty byly propojeny.']);
    }

    private static function failure(string $code, string $url): void {
        $allowed = ['csrf','credential','config','token','email','already_exists','already_linked','email_mismatch','registrations_closed','create','rate','method'];
        $code = in_array($code, $allowed, true) ? $code : 'token';
        $url = wp_validate_redirect($url, home_url('/'));
        wp_safe_redirect(add_query_arg('arc_google_error', $code, $url));
        exit;
    }

    public static function callback(): void {
        nocache_headers();
        $url = wp_validate_redirect(esc_url_raw((string)wp_unslash($_POST['state'] ?? '')), function_exists('wc_get_page_permalink') ? wc_get_page_permalink('myaccount') : home_url('/'));
        if (($_SERVER['REQUEST_METHOD'] ?? '') !== 'POST') self::failure('method', $url);
        if (!self::client_id()) self::failure('config', $url);
        $cookie = isset($_COOKIE['g_csrf_token']) ? (string)$_COOKIE['g_csrf_token'] : '';
        $posted = isset($_POST['g_csrf_token']) ? (string)wp_unslash($_POST['g_csrf_token']) : '';
        if (!$cookie || !$posted || strlen($cookie)>256 || !hash_equals($cookie, $posted)) self::failure('csrf', $url);
        $token = isset($_POST['credential']) ? (string)wp_unslash($_POST['credential']) : '';
        if (!$token || strlen($token) > 12000) self::failure('credential', $url);
        $ip = (string)($_SERVER['REMOTE_ADDR'] ?? '');
        $rate_key = 'arc_g_limit_'.substr(hash('sha256', $ip), 0, 24);
        $n = (int)get_transient($rate_key);
        if ($n >= 30) self::failure('rate', $url);
        set_transient($rate_key, $n+1, 10*MINUTE_IN_SECONDS);
        $claims = self::verify_id_token($token);
        if (is_wp_error($claims)) self::failure('token', $url);
        $sub = (string)$claims['sub'];
        $email = sanitize_email((string)($claims['email'] ?? ''));
        if (!is_email($email) || empty($claims['email_verified']) || !in_array($claims['email_verified'], [true,'true','1',1], true)) self::failure('email', $url);
        $matches = get_users(['meta_key' => self::SUB_META, 'meta_value' => $sub, 'fields' => 'ID', 'number' => 2]);
        if (count($matches) > 1) self::failure('already_linked', $url);
        $linked_uid = $matches ? (int)$matches[0] : 0;
        if (is_user_logged_in()) {
            $current_id = get_current_user_id();
            if ($linked_uid && $linked_uid !== $current_id) self::failure('already_linked', $url);
            if (strcasecmp((string)wp_get_current_user()->user_email, $email) !== 0) self::failure('email_mismatch', $url);
            $old_sub = (string)get_user_meta($current_id, self::SUB_META, true);
            if ($old_sub && !hash_equals($old_sub, $sub)) self::failure('already_linked', $url);
            update_user_meta($current_id, self::SUB_META, $sub);
            wp_safe_redirect(add_query_arg('arc_google_success', 'linked', $url));
            exit;
        }
        $arc_created_here = false;
        if ($linked_uid) {
            $uid = $linked_uid;
        } else {
            // Never silently merge an email-only match: require existing WP login to link accounts.
            if (email_exists($email)) self::failure('already_exists', $url);
            $registration_allowed = (bool)get_option('users_can_register') || get_option('woocommerce_enable_myaccount_registration') === 'yes';
            if (!$registration_allowed) self::failure('registrations_closed', $url);
            $prefix = 'ar_google_'.substr(hash('sha256', $sub), 0, 14);
            $username = $prefix;
            if (username_exists($username)) $username .= '_'.wp_rand(10000,99999);
            $uid = wp_insert_user([
                'user_login'=>$username,
                'user_pass'=>wp_generate_password(48, true, true),
                'user_email'=>$email,
                'display_name'=>trim(sanitize_text_field((string)($claims['name']??''))) ?: 'Člen Adrenaline Race',
                'role'=>class_exists('WooCommerce') ? 'customer' : 'subscriber',
            ]);
            if (is_wp_error($uid) || !$uid) self::failure('create', $url);
            $arc_created_here = true;
            update_user_meta((int)$uid, self::SUB_META, $sub);
            if (class_exists('ARC_Profile')) ARC_Profile::initialize((int)$uid); // Private by default.
        }
        $user = get_user_by('id', (int)$uid);
        if (!$user) self::failure('create', $url);
        wp_set_current_user((int)$uid);
        wp_set_auth_cookie((int)$uid, true, is_ssl());
        do_action('wp_login', $user->user_login, $user);
        if($arc_created_here && function_exists('wc_get_page_permalink')) {
            $url=(string)wc_get_page_permalink('myaccount');
        }
        wp_safe_redirect(add_query_arg('arc_google_success', 'login', $url));
        exit;
    }

    private static function base64url(string $v): string|false {
        return base64_decode(strtr($v, '-_', '+/'), true);
    }
    public static function verify_id_token(string $jwt): array|WP_Error {
        if (!function_exists('openssl_verify')) return new WP_Error('arc_g_ssl', 'OpenSSL není dostupné.');
        $parts = explode('.', $jwt);
        if (count($parts) !== 3) return new WP_Error('arc_g_format', 'Neplatný formát tokenu.');
        $header = json_decode(self::base64url($parts[0]) ?: '', true);
        $data = json_decode(self::base64url($parts[1]) ?: '', true);
        $sig = self::base64url($parts[2]);
        if (!is_array($header) || !is_array($data) || !is_string($sig)) return new WP_Error('arc_g_format', 'Neplatná data tokenu.');
        if (($header['alg']??'') !== 'RS256' || empty($header['kid']) || !is_string($header['kid'])) return new WP_Error('arc_g_alg', 'Nepodporovaný podpis.');
        $kid = $header['kid'];
        if (!preg_match('/^[a-zA-Z0-9_-]{1,128}$/', $kid)) return new WP_Error('arc_g_alg','Neplatný identifikátor klíče.');
        $key = self::public_key($kid);
        if (!$key) return new WP_Error('arc_g_key', 'Google klíč není dostupný.');
        if (openssl_verify($parts[0].'.'.$parts[1], $sig, $key, OPENSSL_ALGO_SHA256) !== 1) return new WP_Error('arc_g_signature', 'Nesouhlasí podpis tokenu.');
        $now = time();
        if (!in_array(($data['iss']??''), ['accounts.google.com','https://accounts.google.com'], true)) return new WP_Error('arc_g_issuer', 'Neplatný vydavatel.');
        if (($data['aud']??'') !== self::client_id()) return new WP_Error('arc_g_aud', 'Neplatný příjemce tokenu.');
        if (isset($data['azp']) && $data['azp'] !== self::client_id()) return new WP_Error('arc_g_azp', 'Nesprávný oprávněný klient.');
        if (empty($data['exp']) || !is_numeric($data['exp']) || (int)$data['exp'] <= $now) return new WP_Error('arc_g_exp', 'Platnost tokenu skončila.');
        if (isset($data['nbf']) && (int)$data['nbf'] > $now+60) return new WP_Error('arc_g_nbf','Token ještě není platný.');
        if (isset($data['iat']) && (int)$data['iat'] > $now+60) return new WP_Error('arc_g_iat','Chybný čas tokenu.');
        if (empty($data['sub']) || !is_string($data['sub']) || strlen($data['sub'])>255) return new WP_Error('arc_g_sub','Chybí identita Google účtu.');
        return $data;
    }
    private static function public_key(string $kid) {
        $certs = get_transient(self::JWKS_CACHE);
        if (!is_array($certs) || !isset($certs[$kid])) $certs = self::retrieve_keys();
        if (!is_array($certs) || !isset($certs[$kid])) return false;
        $entry = $certs[$kid];
        if (!is_array($entry) || empty($entry['n']) || empty($entry['e'])) return false;
        $pem = self::jwk_public_key_pem((string)$entry['n'], (string)$entry['e']);
        return $pem ? openssl_pkey_get_public($pem) : false;
    }
    /** Convert Google's RSA JWK (n/e) to ASN.1 DER SubjectPublicKeyInfo PEM. */
    private static function der_length(int $n): string {
        if ($n < 128) return chr($n);
        $bytes='';
        do { $bytes=chr($n & 255).$bytes; $n >>= 8; } while ($n>0);
        return chr(0x80 | strlen($bytes)).$bytes;
    }
    private static function der_integer(string $v): string {
        $v=ltrim($v, "\x00");
        if ($v==='') $v="\x00";
        if ((ord($v[0]) & 0x80)!==0) $v="\x00".$v;
        return "\x02".self::der_length(strlen($v)).$v;
    }
    private static function jwk_public_key_pem(string $n, string $e): string|false {
        $mod=self::base64url($n); $exp=self::base64url($e);
        if (!is_string($mod) || !is_string($exp) || strlen($mod)<128 || strlen($exp)>8) return false;
        $int=self::der_integer($mod).self::der_integer($exp);
        $rsa="\x30".self::der_length(strlen($int)).$int;
        // rsaEncryption OID 1.2.840.113549.1.1.1 with NULL parameters.
        $algorithm=hex2bin('300d06092a864886f70d0101010500');
        $bitstring="\x03".self::der_length(strlen($rsa)+1)."\x00".$rsa;
        $body=$algorithm.$bitstring;
        $der="\x30".self::der_length(strlen($body)).$body;
        return "-----BEGIN PUBLIC KEY-----\n".chunk_split(base64_encode($der),64,"\n")."-----END PUBLIC KEY-----\n";
    }
    private static function retrieve_keys(): array {
        $r = wp_safe_remote_get('https://www.googleapis.com/oauth2/v3/certs', ['timeout'=>8,'redirection'=>0]);
        if (is_wp_error($r) || (int)wp_remote_retrieve_response_code($r)!==200) return [];
        $json = json_decode(wp_remote_retrieve_body($r), true);
        if (!is_array($json) || !is_array($json['keys']??null)) return [];
        $certs=[];
        foreach ($json['keys'] as $entry) {
            if (!is_array($entry) || ($entry['kty']??'')!=='RSA' || ($entry['alg']??'')!=='RS256') continue;
            $kid=(string)($entry['kid']??'');
            $n=(string)($entry['n']??''); $e=(string)($entry['e']??'');
            if ($kid!=='' && $n!=='' && $e!=='' && strlen($n)<5000 && strlen($e)<100) $certs[$kid]=['n'=>$n,'e'=>$e];
        }
        if ($certs) {
            $cache = (string)wp_remote_retrieve_header($r, 'cache-control');
            $ttl = preg_match('/max-age=(\d+)/', $cache, $m) ? (int)$m[1] : 3600;
            set_transient(self::JWKS_CACHE, $certs, max(300,min($ttl, 86400)));
        }
        return $certs;
    }
}
