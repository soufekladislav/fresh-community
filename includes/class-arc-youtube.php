<?php
/**
 * Fresh Community YouTube channel submissions (beta).
 * - Separate OAuth identity from ARC_Google WordPress login.
 * - User uploads are private on disk and require explicit consent.
 * - Only an administrator can approve publication to the public channel.
 * - YouTube resumable uploads run in bounded chunks via WP-Cron.
 */
defined('ABSPATH') || exit;
final class ARC_YouTube {
    private const TYPE = 'arc_youtube_queue';
    private const SCOPE = 'https://www.googleapis.com/auth/youtube.upload';
    private const CLIENT = 'arc_yt_client_id';
    private const SECRET = 'arc_yt_client_secret_enc';
    private const TOKENS = 'arc_yt_tokens_enc';
    private const CHUNK = 2097152; // 2 MiB per WP-Cron request.

    public static function boot(): void {
        add_action('init', [self::class,'register_type'], 18);
        add_action('admin_menu', [self::class,'menu'], 35);
        add_action('admin_post_arc_yt_save', [self::class,'save']);
        add_action('admin_post_arc_yt_connect', [self::class,'connect']);
        add_action('admin_post_arc_yt_callback', [self::class,'callback']);
        add_action('admin_post_arc_yt_disconnect', [self::class,'disconnect']);
        add_action('admin_post_arc_yt_moderate', [self::class,'moderate']);
        add_action('admin_post_arc_yt_tick', [self::class,'tick_now']);
        add_action('admin_post_arc_yt_preview', [self::class,'preview']);
        add_action('admin_post_arc_yt_submit', [self::class,'submit']);
        add_action('arc_yt_upload_tick', [self::class,'process'], 10, 1);
        add_action('before_delete_user', [self::class,'cleanup_deleted_member'], 18, 1);
        add_action('init', static function(): void { add_shortcode('arc_youtube_submit', [self::class,'shortcode']); });
        add_action('wp_enqueue_scripts', static function(): void {
            if (is_user_logged_in()) {
                wp_enqueue_script('arc-youtube-ui', ARC_URL.'assets/youtube-ui.js', [], ARC_VERSION, true);
                wp_enqueue_style('arc-youtube-ui', ARC_URL.'assets/youtube.css', [], ARC_VERSION);
            }
        }, 29);
    }
    public static function register_type(): void {
        register_post_type(self::TYPE, [
            'label'=>'YouTube videa ke schválení', 'public'=>false, 'publicly_queryable'=>false,
            'show_ui'=>false, 'show_in_rest'=>false, 'exclude_from_search'=>true,
            'rewrite'=>false, 'query_var'=>false, 'supports'=>['title','editor','author'],
        ]);
    }
    public static function redirect_uri(): string {
        return admin_url('admin-post.php?action=arc_yt_callback');
    }
    private static function page_url(array $args=[]): string {
        return add_query_arg($args,admin_url('admin.php?page=arc-youtube'));
    }
    private static function go(string $code): void {
        wp_safe_redirect(self::page_url(['arc_yt_note'=>$code])); exit;
    }
    private static function require_admin(string $nonce): void {
        if (!current_user_can('manage_options')) wp_die('Pouze správce může spravovat kanál.', '', ['response'=>403]);
        check_admin_referer($nonce);
    }
    private static function text_length(string $s): int {return function_exists('mb_strlen')?mb_strlen($s):strlen($s);}
    private static function cut(string $s,int $length): string {return function_exists('mb_substr')?mb_substr($s,0,$length):substr($s,0,$length);}
    private static function key(): string {
        return hash('sha256', wp_salt('auth').'|'.wp_salt('secure_auth').'|arc-youtube-v1',true);
    }
    private static function encrypt(string $value): string|WP_Error {
        if (!function_exists('openssl_encrypt')) return new WP_Error('arc_yt_crypto','Na serveru není dostupné šifrování OpenSSL.');
        $iv=random_bytes(12); $tag='';
        $cipher=openssl_encrypt($value,'aes-256-gcm',self::key(),OPENSSL_RAW_DATA,$iv,$tag);
        if ($cipher===false || strlen($tag)!==16) return new WP_Error('arc_yt_crypto','Nepodařilo se bezpečně uložit tajné údaje.');
        return base64_encode($iv.$tag.$cipher);
    }
    private static function decrypt(string $value): string {
        if (!$value || !function_exists('openssl_decrypt'))return '';
        $data=base64_decode($value,true);
        if ($data===false || strlen($data)<29)return '';
        $plain=openssl_decrypt(substr($data,28),'aes-256-gcm',self::key(),OPENSSL_RAW_DATA,substr($data,0,12),substr($data,12,16));
        return is_string($plain)?$plain:'';
    }
    private static function client_id(): string {return (string)get_option(self::CLIENT,'');}
    private static function client_secret(): string {return self::decrypt((string)get_option(self::SECRET,''));}
    private static function token_data(): array {
        $json=self::decrypt((string)get_option(self::TOKENS,''));
        $data=$json?json_decode($json,true):null;
        return is_array($data)?$data:[];
    }
    private static function save_tokens(array $data): bool {
        $json=wp_json_encode($data);
        $enc=self::encrypt((string)$json);
        if (is_wp_error($enc)) return false;
        update_option(self::TOKENS,$enc,false);
        return true;
    }
    private static function connected(): bool {
        $data=self::token_data();return !empty($data['refresh_token']);
    }
    public static function save(): void {
        self::require_admin('arc_yt_save');
        $id=trim(sanitize_text_field((string)wp_unslash($_POST['arc_yt_client_id']??'')));
        if (!preg_match('/^[0-9]+-[a-z0-9-]+\.apps\.googleusercontent\.com$/i',$id))self::go('invalid_client');
        $old=self::client_id();
        $secret=trim((string)wp_unslash($_POST['arc_yt_client_secret']??''));
        if ($secret!=='') {
            $enc=self::encrypt($secret);
            if (is_wp_error($enc))self::go('crypto');
            update_option(self::SECRET,$enc,false);
        }
        update_option(self::CLIENT,$id,false);
        if ($old && $old!==$id)delete_option(self::TOKENS);
        self::go('settings_saved');
    }
    public static function connect(): void {
        self::require_admin('arc_yt_connect');
        if (!self::client_id() || !self::client_secret())self::go('settings_missing');
        $state=bin2hex(random_bytes(24));
        set_transient('arc_yt_oauth_'.hash('sha256',$state),['uid'=>get_current_user_id(),'time'=>time()],10*MINUTE_IN_SECONDS);
        $params=[
            'client_id'=>self::client_id(), 'redirect_uri'=>self::redirect_uri(), 'response_type'=>'code',
            'scope'=>self::SCOPE, 'access_type'=>'offline', 'prompt'=>'consent select_account',
            'include_granted_scopes'=>'false', 'state'=>$state,
        ];
        wp_redirect('https://accounts.google.com/o/oauth2/v2/auth?'.http_build_query($params,'','&',PHP_QUERY_RFC3986)); exit;
    }
    public static function callback(): void {
        if (!current_user_can('manage_options'))wp_die('Přihlášení správce vypršelo. Zkus propojení znovu.', '', ['response'=>403]);
        $state=sanitize_text_field((string)wp_unslash($_GET['state']??''));
        if (!preg_match('/^[a-f0-9]{48}$/',$state))self::go('invalid_state');
        $key='arc_yt_oauth_'.hash('sha256',$state);
        $record=get_transient($key);delete_transient($key); // One-use state, not reusable.
        if (!is_array($record) || (int)($record['uid']??0)!==get_current_user_id() || time()-(int)($record['time']??0)>600)
            self::go('invalid_state');
        if (isset($_GET['error']))self::go('oauth_denied');
        $code=(string)wp_unslash($_GET['code']??'');
        if (!$code || strlen($code)>2048)self::go('oauth_denied');
        $r=wp_remote_post('https://oauth2.googleapis.com/token',[
            'timeout'=>25, 'redirection'=>0,
            'body'=>['code'=>$code,'client_id'=>self::client_id(),'client_secret'=>self::client_secret(),
                'redirect_uri'=>self::redirect_uri(),'grant_type'=>'authorization_code'],
        ]);
        if (is_wp_error($r) || wp_remote_retrieve_response_code($r)!==200)self::go('oauth_failed');
        $data=json_decode(wp_remote_retrieve_body($r),true);
        if (!is_array($data) || empty($data['access_token']) || empty($data['refresh_token']))self::go('refresh_missing');
        if (!empty($data['scope']) && !str_contains((string)$data['scope'],self::SCOPE))self::go('missing_scope');
        $stored=['access_token'=>(string)$data['access_token'],'refresh_token'=>(string)$data['refresh_token'],
            'expires_at'=>time()+max(60,(int)($data['expires_in']??3600))];
        if (!self::save_tokens($stored))self::go('crypto');
        self::go('connected');
    }
    public static function disconnect(): void {
        self::require_admin('arc_yt_disconnect');
        delete_option(self::TOKENS); self::go('disconnected');
    }
    /** Retrieve access token, refresh only from the server; token never leaves admin. */
    private static function access_token(): string|WP_Error {
        $data=self::token_data();
        if (empty($data['refresh_token']))return new WP_Error('arc_yt_not_connected','YouTube kanál není připojen.');
        if (!empty($data['access_token']) && (int)($data['expires_at']??0)>time()+120)
            return (string)$data['access_token'];
        $r=wp_remote_post('https://oauth2.googleapis.com/token',[
            'timeout'=>25,'redirection'=>0,
            'body'=>['client_id'=>self::client_id(),'client_secret'=>self::client_secret(),
                'refresh_token'=>$data['refresh_token'],'grant_type'=>'refresh_token'],
        ]);
        if (is_wp_error($r) || wp_remote_retrieve_response_code($r)!==200)
            return new WP_Error('arc_yt_expired','Přístup k YouTube vypršel nebo byl odvolán. Připoj kanál znovu.');
        $json=json_decode(wp_remote_retrieve_body($r),true);
        if (!is_array($json) || empty($json['access_token']))return new WP_Error('arc_yt_token','YouTube nevrátil nový přístupový token.');
        $data['access_token']=(string)$json['access_token'];
        $data['expires_at']=time()+max(60,(int)($json['expires_in']??3600));
        if (!self::save_tokens($data))return new WP_Error('arc_yt_crypto','Nelze zabezpečeně uložit token.');
        return $data['access_token'];
    }
    /** Keep videos outside document root; refuse to store files if not possible. */
    private static function private_dir(): string|WP_Error {
        $root=realpath(rtrim(ABSPATH,'/\\'));
        $doc=isset($_SERVER['DOCUMENT_ROOT'])?realpath((string)$_SERVER['DOCUMENT_ROOT']):false;
        if (!$doc) $doc=$root;
        if (!$root)return new WP_Error('arc_yt_storage','Kořen WordPressu není dostupný.');
        $candidate=dirname($root);
        for ($i=0;$i<4;$i++) {
            $real=realpath($candidate);
            if ($real && $real!== '/' && $real!==$doc && !str_starts_with($real,$doc.'/') && is_writable($real)) {
                $folder=$real.'/.fresh-community-youtube';
                if (!is_dir($folder) && !wp_mkdir_p($folder))return new WP_Error('arc_yt_storage','Soukromý adresář nejde vytvořit.');
                @chmod($folder,0700);
                if (!is_writable($folder))return new WP_Error('arc_yt_storage','Soukromý adresář není zapisovatelný.');
                return $folder;
            }
            $candidate=dirname($candidate);
        }
        return new WP_Error('arc_yt_storage','Hosting neumožňuje bezpečné ukládání videí mimo veřejný adresář.');
    }
    private static function file_for(int $id): string|WP_Error {
        $dir=self::private_dir(); if (is_wp_error($dir))return $dir;
        $name=(string)get_post_meta($id,'_arc_yt_filename',true);
        if (!$name || !preg_match('/^[a-f0-9]{32}\.(mp4|mov|webm|mkv|m4v)$/',$name))return new WP_Error('arc_yt_file','Neplatná cesta k videu.');
        $path=$dir.'/'.$name;
        if (!is_file($path) || !is_readable($path))return new WP_Error('arc_yt_file','Video už není dostupné na serveru.');
        return $path;
    }
    private static function allowed_mime(string $path,string $ext): bool {
        $mime=class_exists('finfo')?(new finfo(FILEINFO_MIME_TYPE))->file($path):'';
        $allowed=['mp4'=>['video/mp4','application/mp4'],'mov'=>['video/quicktime'],
            'webm'=>['video/webm'], 'mkv'=>['video/x-matroska'],'m4v'=>['video/x-m4v','video/mp4']];
        return isset($allowed[$ext]) && in_array((string)$mime,$allowed[$ext],true);
    }
    public static function submit(): void {
        if (!is_user_logged_in())wp_die('Nejdříve se přihlas.', '', ['response'=>403]);
        check_admin_referer('arc_yt_submit');
        $uid=get_current_user_id();
        $title=trim(sanitize_text_field((string)wp_unslash($_POST['arc_yt_title']??'')));
        $desc=trim(sanitize_textarea_field((string)wp_unslash($_POST['arc_yt_description']??'')));
        $consent=!empty($_POST['arc_yt_consent']);
        $file=$_FILES['arc_yt_video']??null;
        if (!$consent || self::text_length($title)<4 || self::text_length($title)>100 || self::text_length($desc)>4500)
            self::member_go('form');
        if (!is_array($file) || !isset($file['error']) || (int)$file['error']!==UPLOAD_ERR_OK || !isset($file['tmp_name']) || !is_uploaded_file((string)$file['tmp_name']))
            self::member_go('upload');
        $limit=min(256*1024*1024,(int)wp_max_upload_size());
        $size=(int)($file['size']??0);
        if ($size<1024 || $size>$limit)self::member_go('size');
        $ext=strtolower((string)pathinfo((string)($file['name']??''),PATHINFO_EXTENSION));
        if (!self::allowed_mime((string)$file['tmp_name'],$ext))self::member_go('format');
        // Rate limit and prevent public uploads from unchecked accounts. No affiliate changes.
        $key='arc_yt_submissions_'.$uid;
        $counter=(int)get_transient($key);
        if ($counter>=3)self::member_go('limit');
        $dir=self::private_dir();if (is_wp_error($dir))self::member_go('storage');
        $name=bin2hex(random_bytes(16)).'.'.$ext;
        $dest=$dir.'/'.$name;
        if (!move_uploaded_file((string)$file['tmp_name'],$dest))self::member_go('storage');
        @chmod($dest,0600);
        $post=wp_insert_post(['post_type'=>self::TYPE,'post_status'=>'private','post_author'=>$uid,
            'post_title'=>$title,'post_content'=>$desc],true);
        if (is_wp_error($post)) {@unlink($dest); self::member_go('storage');}
        update_post_meta($post,'_arc_yt_filename',$name);
        update_post_meta($post,'_arc_yt_status','pending');
        update_post_meta($post,'_arc_yt_mime',function_exists('mime_content_type')?(mime_content_type($dest)?:'video/mp4'):'video/mp4');
        update_post_meta($post,'_arc_yt_size',$size);
        set_transient($key,$counter+1,DAY_IN_SECONDS);
        self::member_go('sent');
    }
    private static function member_go(string $note): void {
        $referer=wp_get_referer();
        $url=$referer && wp_validate_redirect($referer,false)?$referer:ARC_Config::app_url();
        wp_safe_redirect(add_query_arg('arc_youtube',$note,remove_query_arg('arc_youtube',$url)));exit;
    }
    public static function shortcode(): string {
        if (!is_user_logged_in())return '<p>Pro nahrání videa se přihlas.</p>';
        ob_start();self::form_contents();return (string)ob_get_clean();
    }
    public static function form_contents(): void {
        $limit=min(256*1024*1024,(int)wp_max_upload_size());
        $note=sanitize_key((string)($_GET['arc_youtube']??''));
        $messages=['sent'=>'Video bylo přijato ke schválení. Zatím není veřejné.','form'=>'Vyplň název a potvrď souhlas se zveřejněním.',
            'upload'=>'Video se nepodařilo nahrát. Zkontroluj nastavení hostingu.','size'=>'Video překračuje povolenou velikost.',
            'format'=>'Nepodporovaný formát. Použij MP4, MOV, WEBM, MKV nebo M4V.','storage'=>'Hosting nepovolil bezpečné uložení videa. Kontaktuj správce.',
            'limit'=>'Limit jsou 3 videa za 24 hodin.'];
        echo '<div class="arc-yt-form-box">';
        if (isset($messages[$note]))echo '<p role="status" class="arc-yt-status">'.esc_html($messages[$note]).'</p>';
        echo '<p><strong>Navrhni video na komunitní YouTube kanál</strong></p>';
        echo '<p>Video nejprve schválí správce. Po schválení se zveřejní veřejně na YouTube a může se objevit na komunitní zdi.</p>';
        echo '<form action="'.esc_url(admin_url('admin-post.php')).'" method="post" enctype="multipart/form-data" class="arc-yt-form">';
        echo '<input type="hidden" name="action" value="arc_yt_submit">';wp_nonce_field('arc_yt_submit');
        echo '<label>Název videa <input name="arc_yt_title" type="text" maxlength="100" minlength="4" required></label>';
        echo '<label>Popis <textarea name="arc_yt_description" maxlength="4500" rows="3"></textarea></label>';
        echo '<label>Video <input name="arc_yt_video" type="file" accept="video/mp4,video/quicktime,video/webm,video/x-matroska,video/x-m4v,.mp4,.mov,.webm,.mkv,.m4v" required></label>';
        echo '<small>Max. '.esc_html(size_format($limit)).'. Mobil může nabídnout fotoaparát nebo galerii. Nahrávání vyžaduje dostatečné limity PHP.</small>';
        echo '<label class="arc-yt-consent"><input type="checkbox" name="arc_yt_consent" value="1" required> Potvrzuji, že jsem autorem videa nebo mám práva k jeho zveřejnění, mám souhlas zachycených osob a souhlasím s veřejným zveřejněním na YouTube kanálu komunity.</label>';
        echo '<button type="submit" class="arc-live-button">Odeslat video ke schválení</button></form></div>';
    }
    public static function menu(): void {
        add_submenu_page('ar-community','YouTube kanál','YouTube','manage_options','arc-youtube',[self::class,'admin_page']);
    }
    public static function admin_page(): void {
        if (!current_user_can('manage_options'))return;
        $notes=['settings_saved'=>'Nastavení uloženo.','connected'=>'YouTube OAuth úspěšně propojen.','disconnected'=>'YouTube kanál odpojen.',
            'invalid_client'=>'Chybný Client ID.','settings_missing'=>'Nejprve ulož Client ID a Client Secret.','crypto'=>'Bezpečné šifrování na serveru selhalo.',
            'invalid_state'=>'Neplatný nebo expirovaný stav OAuth. Opakuj propojení.','oauth_failed'=>'Výměna autorizačního kódu selhala.',
            'oauth_denied'=>'Přístup nebyl povolen.','refresh_missing'=>'Google neposlal refresh token. Zkus propojení znovu.',
            'missing_scope'=>'Google nepotvrdil youtube.upload.','approved'=>'Video schváleno a zařazeno k nahrávání.',
            'rejected'=>'Video odmítnuto.','invalid_job'=>'Video nebylo nalezeno nebo už bylo zpracováno.',
            'not_connected'=>'Nejdříve propojte kanál.','retry'=>'Pokračování nahrávání naplánováno.',
            'tick'=>'Zpracován jeden krok nahrávání.'];
        $code=sanitize_key((string)($_GET['arc_yt_note']??''));
        echo '<div class="wrap"><h1>Fresh Community → YouTube</h1>';
        if(isset($notes[$code]))echo '<div class="notice notice-info"><p>'.esc_html($notes[$code]).'</p></div>';
        echo '<p>Oddělené OAuth propojení s oficiálním kanálem. Registrace Google a Fresh Affiliate jsou beze změn.</p>';
        echo '<h2>OAuth – konfigurace kanálu</h2><p><strong>Redirect URI pro Google Cloud:</strong><br><code>'.esc_html(self::redirect_uri()).'</code></p>';
        echo '<form method="post" action="'.esc_url(admin_url('admin-post.php')).'"><input type="hidden" name="action" value="arc_yt_save">';
        wp_nonce_field('arc_yt_save');
        echo '<p><label>Client ID (YouTube OAuth klient): <input type="text" name="arc_yt_client_id" size="72" value="'.esc_attr(self::client_id()).'" autocomplete="off" required></label></p>';
        echo '<p><label>Client Secret (ponech prázdné, pokud nechceš změnit): <input type="password" name="arc_yt_client_secret" size="45" value="" autocomplete="new-password"></label> '.(self::client_secret()?'Uloženo zašifrovaně.':'Zatím není uložen.').'</p>';
        echo '<p><button class="button button-primary">Uložit OAuth nastavení</button></p></form>';
        echo '<p><strong>Stav:</strong> '.(self::connected()?'<span style="color:green">OAuth propojeno</span>':'Nepřipojeno').'</p>';
        if(self::connected())echo self::action_form('arc_yt_disconnect','arc_yt_disconnect','Odpojit kanál','button');
        echo self::action_form('arc_yt_connect','arc_yt_connect','Propojit / obnovit YouTube kanál','button button-primary');
        $folder=self::private_dir();
        if(is_wp_error($folder))echo '<div class="notice notice-error"><p><strong>Soukromé úložiště:</strong> '.esc_html($folder->get_error_message()).' Nahrávání členů nebude fungovat, dokud hosting nezajistí zapisovatelnou cestu mimo veřejný web.</p></div>';
        else echo '<p>Soukromé úložiště: dostupné mimo veřejný web.</p>';
        echo '<p><strong>Poznámka k testování:</strong> Pokud je Google OAuth v režimu Testing, refresh token může po 7 dnech vypršet. Pro produkci bude potřeba dokončit Google OAuth konfiguraci.</p>';
        echo '<h2>Videa členů – schvalovací fronta</h2>';
        $items=get_posts(['post_type'=>self::TYPE,'post_status'=>'private','numberposts'=>100,'orderby'=>'date','order'=>'DESC']);
        if (!$items) echo '<p>Žádné video nečeká na schválení.</p>';
        else {
            echo '<table class="widefat striped"><thead><tr><th>Člen</th><th>Video</th><th>Stav</th><th>Akce</th></tr></thead><tbody>';
            foreach($items as $item) {
                $st=(string)get_post_meta($item->ID,'_arc_yt_status',true);
                $user=get_userdata((int)$item->post_author);
                $vid=(string)get_post_meta($item->ID,'_arc_yt_video_id',true);
                $error=(string)get_post_meta($item->ID,'_arc_yt_last_error',true);
                echo '<tr><td>'.esc_html($user?$user->display_name:'#'.$item->post_author).'</td><td><strong>'.esc_html($item->post_title).'</strong><br>'.esc_html(wp_trim_words($item->post_content,20));
                if ($vid)echo '<br><a href="'.esc_url('https://www.youtube.com/watch?v='.$vid).'" target="_blank" rel="noopener">Zobrazit YouTube video</a>';
                if ($st!=='published' && $st!=='rejected') {
                    $preview=add_query_arg(['action'=>'arc_yt_preview','id'=>$item->ID,
                        '_wpnonce'=>wp_create_nonce('arc_yt_preview_'.$item->ID)],admin_url('admin-post.php'));
                    echo '<br><video controls preload="metadata" style="max-width:260px;width:100%;margin-top:8px" src="'.esc_url($preview).'"></video>';
                    echo '<br><a href="'.esc_url(add_query_arg('download','1',$preview)).'">Stáhnout ke kontrole</a>';
                }
                echo '</td><td><strong>'.esc_html($st).'</strong>';
                if ($error)echo '<p style="color:#b32d2e">'.esc_html($error).'</p>';
                echo '</td><td>';
                if ($st==='pending'){
                    echo self::moderation_form((int)$item->ID,'approve','Schválit a nahrát');
                    echo self::moderation_form((int)$item->ID,'reject','Odmítnout');
                } elseif ($st==='error'){
                    echo self::moderation_form((int)$item->ID,'retry','Zkusit znovu');
                } elseif (in_array($st,['queued','uploading'],true)){
                    echo self::moderation_form((int)$item->ID,'tick','Zpracovat další část');
                }
                echo '</td></tr>';
            }
            echo '</tbody></table>';
        }
        echo '<p><strong>Bezpečnost:</strong> video je zveřejněno na YouTube až po schválení administrátorem. Po úspěšném nahrání se místní kopie odstraní. Nepodporujeme přímé publikování bez moderace.</p>';
        echo '<p><strong>Shortcode pro samostatnou stránku:</strong> <code>[arc_youtube_submit]</code>. Ve zdi se formulář zobrazuje také pod volbou Video.</p></div>';
    }
    private static function action_form(string $action,string $nonce,string $label,string $class): string {
        ob_start();echo '<form method="post" action="'.esc_url(admin_url('admin-post.php')).'" style="display:inline-block;margin:4px">';
        echo '<input type="hidden" name="action" value="'.esc_attr($action).'">';wp_nonce_field($nonce);
        echo '<button type="submit" class="'.esc_attr($class).'">'.esc_html($label).'</button></form>';
        return (string)ob_get_clean();
    }
    /** Authenticated admin-only review stream. Never publish the private file URL. */
    public static function preview(): void {
        if(!current_user_can('manage_options'))wp_die('Neoprávněný přístup.','',['response'=>403]);
        $id=absint($_GET['id']??0);
        check_admin_referer('arc_yt_preview_'.$id);
        $p=get_post($id);
        if(!$p || $p->post_type!==self::TYPE)wp_die('Video nebylo nalezeno.','',['response'=>404]);
        $path=self::file_for($id);
        if(is_wp_error($path))wp_die('Soubor už není dostupný.','',['response'=>404]);
        $total=(int)filesize($path);
        if($total<1)wp_die('Soubor je prázdný.','',['response'=>404]);
        $start=0;$end=$total-1;$partial=false;
        $range=(string)($_SERVER['HTTP_RANGE']??'');
        if($range && preg_match('/^bytes=(\d+)-(\d*)$/',$range,$m)){
            $start=(int)$m[1];$end=$m[2]!==''?min($total-1,(int)$m[2]):$total-1;
            if($start>$end || $start>=$total){status_header(416);header('Content-Range: bytes */'.$total);exit;}
            $partial=true;
        }
        if(function_exists('nocache_headers'))nocache_headers();
        header('X-Content-Type-Options: nosniff');
        header("Content-Security-Policy: default-src 'none'");
        header('Accept-Ranges: bytes');
        header('Content-Type: '.(string)get_post_meta($id,'_arc_yt_mime',true));
        $ext=(string)pathinfo($path,PATHINFO_EXTENSION);
        $disposition=isset($_GET['download'])?'attachment':'inline';
        header('Content-Disposition: '.$disposition.'; filename="video-ke-kontrole.'.preg_replace('/[^a-z0-9]/','',$ext).'"');
        if($partial){status_header(206);header('Content-Range: bytes '.$start.'-'.$end.'/'.$total);}
        header('Content-Length: '.($end-$start+1));
        while(ob_get_level()>0){ob_end_clean();}
        $handle=fopen($path,'rb');if(!$handle)exit;
        fseek($handle,$start);$left=$end-$start+1;
        while($left>0 && !feof($handle)){
            $chunk=fread($handle,min(1048576,$left));
            if($chunk===false || $chunk==='')break;
            echo $chunk;$left-=strlen($chunk);
            if(connection_aborted())break;
            flush();
        }
        fclose($handle);exit;
    }
    private static function moderation_form(int $id,string $decision,string $label): string {
        ob_start();echo '<form method="post" action="'.esc_url(admin_url('admin-post.php')).'" style="display:inline-block;margin:3px">';
        echo '<input type="hidden" name="action" value="arc_yt_moderate"><input type="hidden" name="id" value="'.(int)$id.'"><input type="hidden" name="decision" value="'.esc_attr($decision).'">';
        wp_nonce_field('arc_yt_moderate_'.$id);
        echo '<button class="button" type="submit">'.esc_html($label).'</button></form>';
        return (string)ob_get_clean();
    }
    private static function schedule(int $id): void {
        if(!wp_next_scheduled('arc_yt_upload_tick',[$id]))wp_schedule_single_event(time()+8,'arc_yt_upload_tick',[$id]);
    }
    public static function moderate(): void {
        if(!current_user_can('manage_options'))wp_die('Neoprávněný přístup.','',['response'=>403]);
        $id=absint($_POST['id']??0);check_admin_referer('arc_yt_moderate_'.$id);
        $post=get_post($id);
        if(!$post || $post->post_type!==self::TYPE)self::go('invalid_job');
        $decision=sanitize_key((string)wp_unslash($_POST['decision']??''));
        $status=(string)get_post_meta($id,'_arc_yt_status',true);
        if($decision==='reject' && $status==='pending'){
            $path=self::file_for($id);if(!is_wp_error($path))@unlink($path);
            update_post_meta($id,'_arc_yt_status','rejected');self::go('rejected');
        }
        if($decision==='approve' && $status==='pending'){
            if(!self::connected())self::go('not_connected');
            update_post_meta($id,'_arc_yt_status','queued');self::schedule($id);self::go('approved');
        }
        if($decision==='retry' && $status==='error'){
            if(!self::connected())self::go('not_connected');
            update_post_meta($id,'_arc_yt_status','queued');delete_post_meta($id,'_arc_yt_last_error');
            self::schedule($id);self::go('retry');
        }
        if($decision==='tick' && in_array($status,['queued','uploading'],true)){
            self::process($id);self::go('tick');
        }
        self::go('invalid_job');
    }
    public static function tick_now(): void {
        if(!current_user_can('manage_options'))wp_die('Neoprávněný přístup.','',['response'=>403]);
        self::go('invalid_job');
    }
    /** On deleting a test member, do not orphan unapproved recordings. Published YouTube
     *  media needs a separate removal request; the upload scope does not delete videos. */
    public static function cleanup_deleted_member(int $uid): void {
        $items=get_posts(['post_type'=>self::TYPE,'post_status'=>'private','author'=>$uid,
            'numberposts'=>-1,'fields'=>'ids']);
        foreach($items as $id){
            $id=(int)$id;$status=(string)get_post_meta($id,'_arc_yt_status',true);
            if($status==='published')continue;
            wp_clear_scheduled_hook('arc_yt_upload_tick',[$id]);
            $path=self::file_for($id);
            if(!is_wp_error($path))@unlink($path);
            delete_post_meta($id,'_arc_yt_session');
            update_post_meta($id,'_arc_yt_status','rejected');
            update_post_meta($id,'_arc_yt_last_error','Účet autora byl odstraněn.');
        }
    }
    private static function error(int $id,string $reason): void {
        update_post_meta($id,'_arc_yt_status','error');
        update_post_meta($id,'_arc_yt_last_error',self::cut(wp_strip_all_tags($reason),400));
    }
    private static function valid_session_url(string $url): bool {
        if(!preg_match('~^https://~i',$url))return false;
        $host=strtolower((string)wp_parse_url($url,PHP_URL_HOST));
        return in_array($host,['www.googleapis.com','youtube.googleapis.com','upload.youtube.com'],true);
    }
    private static function init_session(int $id,string $token,string $path,int $size): string|WP_Error {
        $p=get_post($id);if(!$p)return new WP_Error('arc_yt_missing','Video nebylo nalezeno.');
        $mime=(string)get_post_meta($id,'_arc_yt_mime',true);
        $body=['snippet'=>['title'=>self::cut($p->post_title,100),'description'=>self::cut($p->post_content,4500),'categoryId'=>'17'],
            'status'=>['privacyStatus'=>'public','embeddable'=>true]];
        $r=wp_remote_post('https://www.googleapis.com/upload/youtube/v3/videos?uploadType=resumable&part=snippet,status',[
            'timeout'=>30, 'redirection'=>0,
            'headers'=>['Authorization'=>'Bearer '.$token,'Content-Type'=>'application/json; charset=UTF-8',
                'X-Upload-Content-Type'=>$mime?:'video/mp4','X-Upload-Content-Length'=>(string)$size],
            'body'=>wp_json_encode($body),
        ]);
        if(is_wp_error($r))return new WP_Error('arc_yt_session','YouTube zahájení selhalo: '.$r->get_error_message());
        if(wp_remote_retrieve_response_code($r)!==200) {
            $json=json_decode(wp_remote_retrieve_body($r),true);
            $reason=is_array($json)?(string)($json['error']['message']??'Chyba YouTube API'):'Chyba YouTube API';
            return new WP_Error('arc_yt_session','YouTube API ('.wp_remote_retrieve_response_code($r).'): '.$reason);
        }
        $url=(string)wp_remote_retrieve_header($r,'location');
        if(!self::valid_session_url($url))return new WP_Error('arc_yt_session','YouTube nevrátil platnou adresu pro nahrání.');
        update_post_meta($id,'_arc_yt_session',$url);
        update_post_meta($id,'_arc_yt_offset',0);
        return $url;
    }
    private static function probe(string $url,string $token,int $total): int|array|WP_Error {
        $r=wp_remote_request($url,['method'=>'PUT','timeout'=>30,'redirection'=>0,
            'headers'=>['Authorization'=>'Bearer '.$token,'Content-Length'=>'0','Content-Range'=>'bytes */'.$total],
            'body'=>'']);
        if(is_wp_error($r))return new WP_Error('arc_yt_resume','Ověření stavu přenosu selhalo.');
        $code=wp_remote_retrieve_response_code($r);
        if(in_array($code,[200,201],true)){
            $json=json_decode(wp_remote_retrieve_body($r),true);
            return is_array($json)?$json:new WP_Error('arc_yt_resume','YouTube nevrátil ID videa.');
        }
        if($code!==308)return new WP_Error('arc_yt_resume','YouTube odmítl obnovit přenos (HTTP '.$code.').');
        $range=(string)wp_remote_retrieve_header($r,'range');
        if($range==='')return 0;
        return preg_match('/bytes=0-(\d+)/',$range,$m)?((int)$m[1]+1):0;
    }
    private static function finish(int $id,array $json,string $path): void {
        $ytid=(string)($json['id']??'');
        if(!preg_match('/^[A-Za-z0-9_-]{11}$/',$ytid)){
            self::error($id,'YouTube nevrátil platné ID videa.'); return;
        }
        update_post_meta($id,'_arc_yt_video_id',$ytid);
        update_post_meta($id,'_arc_yt_status','published');
        delete_post_meta($id,'_arc_yt_session');delete_post_meta($id,'_arc_yt_offset');
        delete_post_meta($id,'_arc_yt_last_error');
        @unlink($path);
        $p=get_post($id);if(!$p)return;
        $uid=(int)$p->post_author;
        $visibility=ARC_Profile::visibility($uid);
        if(!in_array($visibility,['public','network'],true))return; // Never override a private profile.
        $wall=wp_insert_post(['post_type'=>ARC_Wall::TYPE,'post_title'=>'Video: '.$p->post_title,
            'post_author'=>$uid,'post_status'=>'private',
            'post_content'=>self::cut($p->post_title.'\n'.$p->post_content,3000)],true);
        if(!is_wp_error($wall)){
            update_post_meta($wall,'_arc_audience',$visibility);
            update_post_meta($wall,'_arc_video','https://www.youtube.com/watch?v='.$ytid);
            update_post_meta($wall,'_arc_comments_enabled','1');
            update_post_meta($id,'_arc_yt_wall_post_id',(int)$wall);
        }
    }
    /** One YouTube chunk per invocation: bounded CPU, RAM, and request duration. */
    public static function process(int $id): void {
        $post=get_post($id);
        if(!$post || $post->post_type!==self::TYPE)return;
        $st=(string)get_post_meta($id,'_arc_yt_status',true);
        if(!in_array($st,['queued','uploading'],true))return;
        $lock='arc_yt_lock_'.$id;
        if(get_transient($lock))return;
        set_transient($lock,1,MINUTE_IN_SECONDS);
        try {
            $path=self::file_for($id);
            if(is_wp_error($path)){self::error($id,$path->get_error_message());return;}
            $total=filesize($path);
            if(!$total || $total<1024){self::error($id,'Soubor videa je prázdný.');return;}
            $token=self::access_token();
            if(is_wp_error($token)){self::error($id,$token->get_error_message());return;}
            $session=(string)get_post_meta($id,'_arc_yt_session',true);
            $resumed=(bool)$session;
            if($session && !self::valid_session_url($session)){
                self::error($id,'Neplatná adresa pro nahrávání.');return;
            }
            if(!$session){
                $session=self::init_session($id,$token,$path,(int)$total);
                if(is_wp_error($session)){self::error($id,$session->get_error_message());return;}
            }
            $offset=(int)get_post_meta($id,'_arc_yt_offset',true);
            if($offset<0 || $offset>$total){self::error($id,'Neplatný stav přenosu.');return;}
            // A prior interrupted response may have reached YouTube; check before retry.
            if($resumed || $offset >= $total){
                $remote=self::probe($session,$token,(int)$total);
                if(is_wp_error($remote)){self::error($id,$remote->get_error_message());return;}
                if(is_array($remote)){self::finish($id,$remote,$path);return;}
                $offset=$remote;update_post_meta($id,'_arc_yt_offset',$offset);
            }
            $length=min(self::CHUNK,$total-$offset);
            if($length<1){self::error($id,'YouTube nemá potvrzený dokončený přenos.');return;}
            $fp=fopen($path,'rb');
            if(!$fp){self::error($id,'Nelze číst video.');return;}
            fseek($fp,$offset);$chunk=fread($fp,$length);fclose($fp);
            if(!is_string($chunk) || strlen($chunk)!==$length){self::error($id,'Nelze načíst blok videa.');return;}
            update_post_meta($id,'_arc_yt_status','uploading');
            $r=wp_remote_request($session,['method'=>'PUT','timeout'=>30,'redirection'=>0,
                'headers'=>['Authorization'=>'Bearer '.$token,
                    'Content-Length'=>(string)$length,
                    'Content-Type'=>(string)get_post_meta($id,'_arc_yt_mime',true),
                    'Content-Range'=>'bytes '.$offset.'-'.($offset+$length-1).'/'.$total],
                'body'=>$chunk]);
            if(is_wp_error($r)){
                self::error($id,'Spojení při nahrávání selhalo. Ověř stav a zkus pokračovat.');return;
            }
            $status=wp_remote_retrieve_response_code($r);
            if(in_array($status,[200,201],true)){
                $data=json_decode(wp_remote_retrieve_body($r),true);
                if(is_array($data))self::finish($id,$data,$path);
                else self::error($id,'YouTube dokončilo přenos, ale nevrátilo platnou odpověď.');
                return;
            }
            if($status===308){
                $range=(string)wp_remote_retrieve_header($r,'range');
                $next=preg_match('/bytes=0-(\d+)/',$range,$m)?(int)$m[1]+1:$offset+$length;
                update_post_meta($id,'_arc_yt_offset',min((int)$total,max($offset,$next)));
                self::schedule($id);return;
            }
            $json=json_decode(wp_remote_retrieve_body($r),true);
            $message=is_array($json)?(string)($json['error']['message']??'') : '';
            self::error($id,'YouTube vrátilo HTTP '.$status.'. '.($message?:'Přenos přerušen.'));
        } finally {delete_transient($lock);}
    }
}
