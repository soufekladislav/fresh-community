<?php
defined('ABSPATH') || exit;
final class ARC_Live_API {
    private const NS='ar-community/v1';
    public static function routes(): void {
        $logged=static fn()=>is_user_logged_in();$canwrite=static fn()=>is_user_logged_in() && !get_user_meta(get_current_user_id(),'_arc_community_suspended',true);$public='__return_true';
        foreach([
          ['GET','/live/me',$logged,'me'],['POST','/live/me',$canwrite,'update_me'],
          ['GET','/live/people',$public,'people'],['GET','/live/profile/(?P<id>\d+)',$public,'profile'],
          ['GET','/live/notifications',$logged,'notifications'],['POST','/live/notifications/read',$canwrite,'notifications_read'],['POST','/live/notifications/delete',$canwrite,'notifications_delete'],
          ['GET','/live/requests',$logged,'requests'],['GET','/live/sent',$logged,'sent'],['GET','/live/friends',$logged,'friends'],['GET','/live/search',$logged,'search'],['GET','/live/directory',$logged,'directory'],['POST','/live/invite',$canwrite,'invite'],['POST','/live/invite-link',$canwrite,'invite_link'],['POST','/live/invite/accept-link',$canwrite,'invite_accept_link'],['POST','/live/avatar',$canwrite,'avatar'],['POST','/live/relations',$canwrite,'relations'],
          ['GET','/live/feed',$public,'feed'],['GET','/live/post/(?P<id>\d+)',$public,'single_post'],['POST','/live/posts',$canwrite,'create'],
          ['DELETE','/live/posts/(?P<id>\d+)',$canwrite,'delete'],
          ['POST','/live/posts/(?P<id>\d+)/edit',$canwrite,'edit'],
          ['POST','/live/items/(?P<type>community|article)/(?P<id>\d+)/save',$canwrite,'save'],
          ['POST','/live/items/(?P<type>community|article)/(?P<id>\d+)/share',$canwrite,'share'],
          ['POST','/live/items/(?P<type>community|article)/(?P<id>\d+)/react',$canwrite,'item_react'],
          ['POST','/live/posts/(?P<id>\d+)/comment',$canwrite,'comment'],
          ['POST','/live/comments/(?P<id>\d+)/like',$canwrite,'comment_like'],
          ['POST','/live/comments/(?P<id>\d+)/edit',$canwrite,'comment_edit'],
          ['DELETE','/live/comments/(?P<id>\d+)',$canwrite,'comment_delete'],
          ['POST','/live/posts/(?P<id>\d+)/react',$canwrite,'react'],
          ['POST','/live/photo',$canwrite,'photo'],['GET','/live/gear',$public,'gear'],['GET','/live/premium',$logged,'premium'],
        ] as $d) register_rest_route(self::NS,$d[1],['methods'=>$d[0],'permission_callback'=>$d[2],'callback'=>[self::class,$d[3]]]);
    }
    private static function fresh(): void {nocache_headers();}
    private static function err(string $message,int $status=400): WP_Error {return new WP_Error('arc_live_error',$message,['status'=>$status]);}
    public static function me(): array {self::fresh();return ARC_Profile::own_card(get_current_user_id());}
    public static function update_me(WP_REST_Request $r): array|WP_Error {self::fresh();return ARC_Profile::save(get_current_user_id(),$r);}
    public static function people(WP_REST_Request $r): array {self::fresh();return ['items'=>ARC_Profile::directory(get_current_user_id(),max(1,min(20,absint($r['page']?:1))))];}
    public static function profile(WP_REST_Request $r): array|WP_Error {
        self::fresh();$profile=ARC_Profile::public_card(absint($r['id']),get_current_user_id());
        return $profile?:self::err('Profil není dostupný.',404);
    }
    public static function notifications(): array {self::fresh();return ARC_Notifications::inbox(get_current_user_id());}
    public static function notifications_read(WP_REST_Request $r): array {self::fresh();return ARC_Notifications::mark_read(get_current_user_id(),absint($r->get_param('id')));}
    public static function notifications_delete(WP_REST_Request $r): array|WP_Error {self::fresh();return ARC_Notifications::delete_one(get_current_user_id(),absint($r->get_param('id')));}
    public static function friends(): array { self::fresh(); return ARC_Account::friends(get_current_user_id()); }
    public static function search(WP_REST_Request $r): array|WP_Error { self::fresh(); return ARC_Account::search(get_current_user_id(),(string)$r->get_param('q')); }
    public static function directory(WP_REST_Request $r): array|WP_Error {self::fresh();return ARC_Account::directory(get_current_user_id(),(string)($r->get_param('q')??''),max(1,absint($r->get_param('page')?:1)));}
    public static function invite(WP_REST_Request $r): array|WP_Error { self::fresh(); return ARC_Account::invite(get_current_user_id(),$r); }
    public static function invite_link(): array|WP_Error {
        self::fresh();$uid=get_current_user_id();
        if(ARC_Profile::visibility($uid)==='private')return self::err('Pro zasílání pozvánek nastav profil na Viditelný v síti nebo Veřejný.',403);
        $key='arc_invite_link_rate_'.$uid;$n=(int)get_transient($key);
        if($n>=30)return self::err('Limit pozvánek byl dosažen.',429);
        set_transient($key,$n+1,HOUR_IN_SECONDS);
        return ['url'=>ARC_Invites::issue($uid)];
    }
    public static function invite_accept_link(WP_REST_Request $r): array|WP_Error {
        self::fresh();
        return ARC_Invites::accept_link(get_current_user_id(),sanitize_text_field((string)$r->get_param('token')));
    }
    public static function avatar(WP_REST_Request $r): array|WP_Error { self::fresh(); return ARC_Account::avatar(get_current_user_id()); }
    public static function requests(): array {self::fresh();return ['items'=>ARC_Relations::pending(get_current_user_id())];}
    public static function sent(): array {self::fresh();return ['items'=>ARC_Relations::sent(get_current_user_id())];}
    public static function relations(WP_REST_Request $r): array|WP_Error {
        self::fresh();return ARC_Relations::change(get_current_user_id(),absint($r['target_id']),sanitize_key((string)$r['action']));
    }
    public static function feed(WP_REST_Request $r): array {
        self::fresh();$p=max(1,min(50,absint($r['page']?:1)));
        return ARC_Wall::feed(get_current_user_id(),$p,sanitize_key((string)($r['filter']?:'all')));
    }
    /** Canonical deep link for saved private/community posts; no generic WP permalink exposure. */
    public static function single_post(WP_REST_Request $r): array|WP_Error {
        self::fresh();
        $p=get_post(absint($r['id']));
        if(!$p instanceof WP_Post || !ARC_Wall::can_read($p,get_current_user_id()))return self::err('Příspěvek není dostupný.',404);
        return ARC_Wall::post_payload($p,get_current_user_id());
    }
    public static function create(WP_REST_Request $r): array|WP_Error {self::fresh();return ARC_Wall::create(get_current_user_id(),$r);}
    public static function delete(WP_REST_Request $r): array|WP_Error {
        self::fresh();$id=absint($r['id']);$p=get_post($id);
        if(!$p||$p->post_type!==ARC_Wall::TYPE) return self::err('Příspěvek nenalezen.',404);
        if((int)$p->post_author!==get_current_user_id()&&!current_user_can('manage_options')) return self::err('Nemáš oprávnění.',403);
        if(!wp_trash_post($id))return self::err('Nelze smazat příspěvek.',500);
        return ['ok'=>true];
    }
    public static function edit(WP_REST_Request $r): array|WP_Error {
        self::fresh();$p=get_post(absint($r['id']));$uid=get_current_user_id();
        if(!$p||$p->post_type!==ARC_Wall::TYPE)return self::err('Příspěvek nenalezen.',404);
        if((int)$p->post_author!==$uid&&!current_user_can('manage_options'))return self::err('Nemáš oprávnění.',403);
        return ARC_Wall::update((int)$p->post_author,$p,$r);
    }
    private static function visible_item(WP_REST_Request $r): ?WP_Post {
        $p=get_post(absint($r['id']));
        if(!$p)return null;
        if($r['type']==='community')return ARC_Wall::can_read($p,get_current_user_id())?$p:null;
        if($r['type']==='article' && $p->post_type==='post' && $p->post_status==='publish' && $p->post_password==='')return $p;
        return null;
    }
    public static function save(WP_REST_Request $r): array|WP_Error {
        self::fresh();$p=self::visible_item($r);if(!$p)return self::err('Obsah není dostupný.',404);
        $uid=get_current_user_id();$key=$r['type'].':'.$p->ID;
        $arr=get_user_meta($uid,'_arc_saved_wall_items',true);$arr=is_array($arr)?array_values(array_filter($arr,'is_string')):[];
        if(in_array($key,$arr,true)){$arr=array_values(array_diff($arr,[$key]));$saved=false;}
        else{$arr[]=$key;$arr=array_slice(array_unique($arr),-300);$saved=true;}
        update_user_meta($uid,'_arc_saved_wall_items',$arr);return ['saved'=>$saved];
    }
    public static function share(WP_REST_Request $r): array|WP_Error {
        self::fresh();if(!self::visible_item($r))return self::err('Obsah není dostupný.',404);
        return ARC_Wall::share(get_current_user_id(),sanitize_key((string)$r['type']),absint($r['id']),absint($r->get_param('target_id')),(string)$r->get_param('message'));
    }
    public static function item_react(WP_REST_Request $r): array|WP_Error {
        self::fresh();if(!self::visible_item($r))return self::err('Obsah není dostupný.',404);
        return self::react($r);
    }
    /** Shared currency formatting for favorites, collections and the shop strip. */
    private static function product_price_label($product): string {return ARC_Price::label($product);}
    /** Shop recommendations: match opted-in interests, then top up to 20 with publicly visible stock. */
    public static function gear(): array {
        self::fresh();
        if(!function_exists('wc_get_products')) return ['items'=>[],'personalized'=>false];
        $uid=get_current_user_id();
        $raw=$uid?(string)get_user_meta($uid,ARC_Data::META_INTERESTS,true):'';
        $keywords=array_values(array_filter(array_map('trim',preg_split('/[,;\n]+/u',mb_strtolower($raw)))));
        $matching=[];
        if($keywords){
            $terms=get_terms(['taxonomy'=>'product_cat','hide_empty'=>true,'number'=>0]);
            if(!is_wp_error($terms))foreach($terms as $term){
                $name=mb_strtolower($term->name.' '.$term->slug);
                foreach($keywords as $keyword){
                    if(mb_strlen($keyword)<3)continue;
                    if(mb_stripos($name,$keyword)!==false || mb_stripos($keyword,mb_strtolower($term->name))!==false){$matching[]=$term->slug;break;}
                }
                if(count($matching)>=12)break;
            }
        }
        $result=[];$seen=[];
        $collect=static function(array $query) use (&$result,&$seen): void {
            foreach(wc_get_products($query) as $product){
                $id=(int)$product->get_id();
                if(isset($seen[$id]) || !$product->is_visible() || !$product->is_in_stock() || !$product->is_purchasable())continue;
                $image=wp_get_attachment_image_url($product->get_image_id(),'woocommerce_single');
                $result[]=['id'=>$id,'name'=>$product->get_name(),'url'=>get_permalink($id),
                    'image'=>$image?:'','price'=>self::product_price_label($product)];
                $seen[$id]=true;
                if(count($result)>=20)break;
            }
        };
        if($matching)$collect(['status'=>'publish','category'=>$matching,'limit'=>60,'orderby'=>'date','order'=>'DESC']);
        if(count($result)<20)$collect(['status'=>'publish','limit'=>65,'orderby'=>'date','order'=>'DESC']);
        return ['items'=>array_slice($result,0,20),'personalized'=>(bool)$matching];
    }
    public static function comment(WP_REST_Request $r): array|WP_Error {
        self::fresh();$pid=absint($r['id']);$p=get_post($pid);$uid=get_current_user_id();
        if(!$p||!ARC_Wall::can_read($p,$uid))return self::err('Příspěvek není dostupný.',404);
        if(metadata_exists('post',$pid,'_arc_comments_enabled') && get_post_meta($pid,'_arc_comments_enabled',true)==='0')return self::err('Autor vypnul komentáře.',403);
        $body=trim(mb_substr(sanitize_textarea_field((string)$r['body']),0,800));
        if(mb_strlen($body)<2)return self::err('Komentář je příliš krátký.');
        $key='arc_comment_rate_'.$uid;$rate=(int)get_transient($key);
        if($rate>=30)return self::err('Limit komentářů za hodinu.',429);
        global $wpdb;$table=ARC_Wall::comment_table();
        $parent=absint($r->get_param('parent_id'));$level=0;$notify=(int)$p->post_author;
        if($parent){
            $ancestor=$wpdb->get_row($wpdb->prepare("SELECT id,user_id,parent_id,post_id FROM $table WHERE id=%d AND status='approved'",$parent));
            if(!$ancestor||(int)$ancestor->post_id!==$pid)return self::err('Nadřazený komentář neexistuje.',404);
            $notify=(int)$ancestor->user_id;$walk=$ancestor;
            while($walk && (int)$walk->parent_id>0){
                if(++$level>=2)return self::err('Vlákno umožňuje nejvýše tři úrovně komentářů.',400);
                $walk=$wpdb->get_row($wpdb->prepare("SELECT id,parent_id FROM $table WHERE id=%d",(int)$walk->parent_id));
            }
        }
        if(!$wpdb->insert($table,['post_id'=>$pid,'user_id'=>$uid,'parent_id'=>$parent,'body'=>$body,'status'=>'approved','created_at'=>current_time('mysql',true)],['%d','%d','%d','%s','%s','%s']))return self::err('Komentář nelze uložit.',500);
        $commentId=(int)$wpdb->insert_id;
        ARC_Notifications::comment_added($uid,$notify,$pid,$commentId);
        set_transient($key,$rate+1,HOUR_IN_SECONDS);
        return ['items'=>ARC_Wall::comments($pid,$uid)];
    }
    public static function comment_like(WP_REST_Request $r): array|WP_Error {
        self::fresh();$uid=get_current_user_id();$id=absint($r['id']);
        global $wpdb;$comments=ARC_Wall::comment_table();$likes=ARC_Wall::comment_like_table();
        $row=$wpdb->get_row($wpdb->prepare("SELECT post_id FROM $comments WHERE id=%d AND status='approved'",$id));
        $p=$row?get_post((int)$row->post_id):null;
        if(!$p||!ARC_Wall::can_read($p,$uid))return self::err('Komentář není dostupný.',404);
        $lim=(int)get_transient('arc_comment_like_min_'.$uid);
        if($lim>=40)return self::err('Příliš mnoho reakcí během jedné minuty.',429);
        set_transient('arc_comment_like_min_'.$uid,$lim+1,MINUTE_IN_SECONDS);
        $existing=(int)$wpdb->get_var($wpdb->prepare("SELECT id FROM $likes WHERE comment_id=%d AND user_id=%d",$id,$uid));
        if($existing)$wpdb->delete($likes,['id'=>$existing],['%d']);
        else $wpdb->insert($likes,['comment_id'=>$id,'user_id'=>$uid,'created_at'=>current_time('mysql',true)],['%d','%d','%s']);
        return ['items'=>ARC_Wall::comments((int)$row->post_id,$uid)];
    }
    /** Moderation is restricted to WP administrators. Every mutation checks the parent post. */
    private static function comment_admin_row(WP_REST_Request $r): array|WP_Error {
        if(!current_user_can('manage_options'))return self::err('Komentáře může spravovat pouze administrátor.',403);
        global $wpdb;$table=ARC_Wall::comment_table();$id=absint($r['id']);
        $row=$wpdb->get_row($wpdb->prepare("SELECT id,post_id FROM $table WHERE id=%d",$id),ARRAY_A);
        if(!$row)return self::err('Komentář neexistuje.',404);
        $p=get_post((int)$row['post_id']);
        if(!$p||!ARC_Wall::can_read($p,get_current_user_id()))return self::err('Příspěvek není dostupný.',404);
        return $row;
    }
    public static function comment_edit(WP_REST_Request $r): array|WP_Error {
        self::fresh();$row=self::comment_admin_row($r);if(is_wp_error($row))return $row;
        $body=trim(mb_substr(sanitize_textarea_field((string)$r->get_param('body')),0,800));
        if(mb_strlen($body)<2)return self::err('Komentář je příliš krátký.');
        global $wpdb;$table=ARC_Wall::comment_table();
        $ok=$wpdb->update($table,['body'=>$body],['id'=>(int)$row['id']],['%s'],['%d']);
        if($ok===false)return self::err('Komentář se nepodařilo upravit.',500);
        return ['items'=>ARC_Wall::comments((int)$row['post_id'],get_current_user_id())];
    }
    public static function comment_delete(WP_REST_Request $r): array|WP_Error {
        self::fresh();$row=self::comment_admin_row($r);if(is_wp_error($row))return $row;
        global $wpdb;$table=ARC_Wall::comment_table();
        $wpdb->delete(ARC_Wall::comment_like_table(),['comment_id'=>(int)$row['id']],['%d']);
        $ok=$wpdb->delete($table,['id'=>(int)$row['id']],['%d']);
        if(!$ok)return self::err('Komentář se nepodařilo odstranit.',500);
        return ['items'=>ARC_Wall::comments((int)$row['post_id'],get_current_user_id())];
    }
    public static function react(WP_REST_Request $r): array|WP_Error {
        self::fresh();$pid=absint($r['id']);$uid=get_current_user_id();$p=get_post($pid);
        if(!$p||!(($r['type']??'community')==='article'
          ?($p->post_type==='post'&&$p->post_status==='publish'&&$p->post_password==='')
          :ARC_Wall::can_read($p,$uid)))return self::err('Příspěvek není dostupný.',404);
        global $wpdb;$t=ARC_Wall::reaction_table();
        if(ARC_Wall::reacted($pid,$uid))$wpdb->delete($t,['post_id'=>$pid,'user_id'=>$uid],['%d','%d']);
        else $wpdb->insert($t,['post_id'=>$pid,'user_id'=>$uid,'created_at'=>current_time('mysql',true)],['%d','%d','%s']);
        return ['count'=>ARC_Wall::reaction_count($pid),'reacted'=>ARC_Wall::reacted($pid,$uid)];
    }
    public static function photo(WP_REST_Request $r): array|WP_Error {
        self::fresh();$uid=get_current_user_id();
        if(empty($_FILES['image'])||!is_array($_FILES['image']))return self::err('Vyber fotografii.');
        $file=$_FILES['image'];if(($file['error']??UPLOAD_ERR_NO_FILE)!==UPLOAD_ERR_OK || ($file['size']??0)>5*1024*1024)return self::err('Fotografie musí být menší než 5 MB.');
        $scope=sanitize_key((string)$r->get_param('visibility'));
        if(in_array($scope,['private','network'],true))return ARC_Wall_Private_Media::upload($file,$uid,$scope);
        if($scope!=='public')return self::err('Vyber viditelnost příspěvku.');
        // The URL is public! Never associate these attachments with private/network wall posts.
        $type=wp_check_filetype_and_ext($file['tmp_name'],(string)$file['name']);
        if(!in_array($type['ext']??'', ['jpg','jpeg','png','webp'],true) || !in_array($type['type']??'', ['image/jpeg','image/png','image/webp'],true)) return self::err('Povoleny jsou JPG, PNG a WebP.');
        $key='arc_photo_rate_'.$uid;$count=(int)get_transient($key);if($count>=10)return self::err('Limit fotografií za hodinu.',429);
        require_once ABSPATH.'wp-admin/includes/file.php';require_once ABSPATH.'wp-admin/includes/image.php';require_once ABSPATH.'wp-admin/includes/media.php';
        $id=media_handle_upload('image',0,[],['test_form'=>false]);
        if(is_wp_error($id))return self::err('Fotografii se nepodařilo nahrát: '.sanitize_text_field($id->get_error_message()));
        wp_update_post(['ID'=>$id,'post_author'=>$uid]);
        set_transient($key,$count+1,HOUR_IN_SECONDS);
        return ['id'=>(int)$id,'url'=>wp_get_attachment_image_url($id,'medium')];
    }
    public static function premium(): array {self::fresh();return ARC_Premium::access(get_current_user_id());}
}
