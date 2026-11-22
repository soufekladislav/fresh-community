<?php
/** Private social-photo storage. Never creates public WordPress media URLs. */
defined('ABSPATH') || exit;
final class ARC_Wall_Private_Media {
    private const PREFIX = 'arc_private_wall_photo_';

    public static function boot(): void {
        add_action('wp_ajax_arc_wall_private_image', [self::class,'serve']);
        add_action('wp_ajax_nopriv_arc_wall_private_image', [self::class,'serve']);
        add_action('before_delete_post', [self::class,'delete_for_post']);
        add_action('arc_private_media_cleanup', [self::class,'cleanup']);
        add_action('init', static function(): void {
            if (!wp_next_scheduled('arc_private_media_cleanup')) wp_schedule_event(time()+HOUR_IN_SECONDS,'daily','arc_private_media_cleanup');
        }, 25);
    }

    /** Refuse uploads unless the filesystem target can be placed outside the web's document root. */
    private static function directory(bool $create=true): string {
        $wp = realpath(untrailingslashit(ABSPATH));
        $doc = isset($_SERVER['DOCUMENT_ROOT']) ? realpath((string)$_SERVER['DOCUMENT_ROOT']) : false;
        if (!$wp || !$doc || $doc===DIRECTORY_SEPARATOR) return '';
        $parent=dirname($wp);
        if ($parent===$doc || str_starts_with($parent,$doc.DIRECTORY_SEPARATOR)) $parent=dirname($doc);
        if ($parent===$doc || str_starts_with($parent,$doc.DIRECTORY_SEPARATOR)) return '';
        $dir=$parent.DIRECTORY_SEPARATOR.'.fresh-community-private-'.substr(hash('sha256',home_url('/')),0,16);
        if ($create && !is_dir($dir) && !wp_mkdir_p($dir)) return '';
        if (!is_dir($dir) || !is_writable($dir)) return '';
        if ($create) {
            @chmod($dir,0700);
            if (!is_file($dir.'/.htaccess')) @file_put_contents($dir.'/.htaccess',"Require all denied\nDeny from all\n");
        }
        $actual=realpath($dir);
        return ($actual && $actual!==$doc && !str_starts_with($actual,$doc.DIRECTORY_SEPARATOR))?$actual:'';
    }

    public static function upload(array $file,int $uid,string $scope): array|WP_Error {
        if (!in_array($scope,['private','network'],true)) return new WP_Error('arc_private_scope','Neplatný rozsah fotografie.',['status'=>400]);
        if (($file['error']??UPLOAD_ERR_NO_FILE)!==UPLOAD_ERR_OK || empty($file['size']) || (int)$file['size']>5*1024*1024) {
            return new WP_Error('arc_private_size','Fotografie musí mít nejvýše 5 MB.',['status'=>400]);
        }
        $key='arc_photo_rate_'.$uid;
        $count=(int)get_transient($key);
        if($count>=10)return new WP_Error('arc_private_rate','Limit fotografií za hodinu.',['status'=>429]);
        $types=['image/jpeg'=>'jpg','image/png'=>'png','image/webp'=>'webp'];
        $actual=wp_check_filetype_and_ext((string)$file['tmp_name'],(string)$file['name']);
        $mime=(string)($actual['type']??'');
        if (!isset($types[$mime]) || !in_array((string)($actual['ext']??''),['jpg','jpeg','png','webp'],true) || !is_uploaded_file((string)$file['tmp_name'])) {
            return new WP_Error('arc_private_file','Podporujeme jen platné JPG, PNG nebo WebP fotografie.',['status'=>400]);
        }
        $image=@getimagesize((string)$file['tmp_name']);
        if (!$image || (string)($image['mime']??'')!==$mime) return new WP_Error('arc_private_file','Soubor není platná fotografie.',['status'=>400]);
        $dir=self::directory();
        if (!$dir) return new WP_Error('arc_private_storage','Soukromé fotky nelze bezpečně uložit mimo veřejný web. Požádej správce hostingu o kontrolu cesty k souborům.',['status'=>500]);
        $token=bin2hex(random_bytes(24));
        $filename='pending-'.$uid.'-'.$token.'.'.$types[$mime];
        $target=$dir.DIRECTORY_SEPARATOR.$filename;
        if (!move_uploaded_file((string)$file['tmp_name'],$target)) return new WP_Error('arc_private_upload','Soukromou fotografii nelze uložit.',['status'=>500]);
        @chmod($target,0600);
        set_transient(self::PREFIX.$token,['user'=>$uid,'scope'=>$scope,'file'=>$filename],HOUR_IN_SECONDS);
        set_transient($key,$count+1,HOUR_IN_SECONDS);
        return ['id'=>0,'private_photo'=>$token,'message'=>'Fotografie nahrána. Dokonči publikování příspěvku.'];
    }
    private static function pending(string $token,int $uid,string $scope): array {
        if (!preg_match('/^[a-f0-9]{48}$/D',$token)) return [];
        $pending=get_transient(self::PREFIX.$token);
        if (!is_array($pending) || (int)($pending['user']??0)!==$uid || ($pending['scope']??'')!==$scope) return [];
        if (!preg_match('/^pending-\d+-[a-f0-9]{48}\.(jpg|png|webp)$/D',(string)($pending['file']??''))) return [];
        return $pending;
    }
    public static function valid(string $token,int $uid,string $scope): bool {
        $entry=self::pending($token,$uid,$scope);
        $dir=self::directory(false);
        return $entry && $dir && is_file($dir.DIRECTORY_SEPARATOR.$entry['file']);
    }
    public static function attach(string $token,int $uid,string $scope,int $post_id): bool|WP_Error {
        if (!self::valid($token,$uid,$scope)) return new WP_Error('arc_private_missing','Fotografie vypršela. Vyber ji prosím znovu.',['status'=>400]);
        $entry=self::pending($token,$uid,$scope);
        $dir=self::directory(false);
        $source=$dir.DIRECTORY_SEPARATOR.$entry['file'];
        $ext=strtolower((string)pathinfo($source,PATHINFO_EXTENSION));
        $name='post-'.$post_id.'-'.bin2hex(random_bytes(12)).'.'.$ext;
        if (!@rename($source,$dir.DIRECTORY_SEPARATOR.$name)) return new WP_Error('arc_private_rename','Nahranou fotografii nelze přiřadit k příspěvku.',['status'=>500]);
        @chmod($dir.DIRECTORY_SEPARATOR.$name,0600);
        if (!update_post_meta($post_id,'_arc_private_image_file',$name)) {
            @unlink($dir.DIRECTORY_SEPARATOR.$name);
            return new WP_Error('arc_private_db','Soukromou fotografii se nepodařilo zapsat k příspěvku.',['status'=>500]);
        }
        delete_transient(self::PREFIX.$token);
        return true;
    }
    public static function has(int $post_id): bool {
        return (bool)self::file_name($post_id);
    }
    private static function file_name(int $post_id): string {
        $name=(string)get_post_meta($post_id,'_arc_private_image_file',true);
        return preg_match('/^post-\d+-[a-f0-9]{24}\.(jpg|png|webp)$/D',$name) ? $name : '';
    }
    public static function url(int $post_id): string {
        return add_query_arg(['action'=>'arc_wall_private_image','post_id'=>$post_id],admin_url('admin-ajax.php'));
    }
    public static function serve(): void {
        $pid=isset($_GET['post_id'])?absint($_GET['post_id']):0;
        $post=$pid?get_post($pid):null;
        $viewer=get_current_user_id();
        if (!$post || !ARC_Wall::can_read($post,$viewer)) {
            status_header(403);header('Cache-Control: no-store');exit;
        }
        $filename=self::file_name($pid);
        $dir=self::directory(false);
        $path=$dir && $filename?$dir.DIRECTORY_SEPARATOR.$filename:'';
        if (!$path || !is_file($path)) {status_header(404);exit;}
        $extension=strtolower((string)pathinfo($filename,PATHINFO_EXTENSION));
        $mimes=['jpg'=>'image/jpeg','png'=>'image/png','webp'=>'image/webp'];
        header('Content-Type: '.$mimes[$extension]);
        header('Content-Length: '.filesize($path));
        header('Cache-Control: private, no-store, max-age=0');
        header('X-Content-Type-Options: nosniff');
        header('Referrer-Policy: no-referrer');
        readfile($path);exit;
    }
    public static function delete_for_post(int $post_id): void {
        $name=self::file_name($post_id);$dir=self::directory(false);
        if ($name && $dir && is_file($dir.DIRECTORY_SEPARATOR.$name)) @unlink($dir.DIRECTORY_SEPARATOR.$name);
    }
    public static function cleanup(): void {
        $dir=self::directory(false);if(!$dir)return;
        foreach(glob($dir.DIRECTORY_SEPARATOR.'pending-*.{jpg,png,webp}',GLOB_BRACE)?:[] as $file) {
            if(is_file($file) && filemtime($file)<time()-2*HOUR_IN_SECONDS)@unlink($file);
        }
    }
}
