<?php
defined('ABSPATH') || exit;
final class ARC_API {
    private const NS = 'ar-community/v1';
    public static function routes(): void {
        $member = static fn()=>is_user_logged_in();
        $community_write = static fn()=>is_user_logged_in() && !get_user_meta(get_current_user_id(),'_arc_community_suspended',true);
        $public = '__return_true';
        $admin = static fn()=>current_user_can('manage_options');
        register_rest_route(self::NS,'/me',[
            ['methods'=>'GET','permission_callback'=>$member,'callback'=>[self::class,'me']],
            ['methods'=>WP_REST_Server::EDITABLE,'permission_callback'=>$community_write,'callback'=>[self::class,'save_me']],
        ]);
        register_rest_route(self::NS,'/collections',[
            ['methods'=>'GET','permission_callback'=>$member,'callback'=>[self::class,'collections']],
            ['methods'=>'POST','permission_callback'=>$member,'callback'=>[self::class,'create']],
        ]);
        register_rest_route(self::NS,'/collections/(?P<id>\d+)',[
            ['methods'=>'GET','permission_callback'=>$public,'callback'=>[self::class,'one']],
            ['methods'=>WP_REST_Server::EDITABLE,'permission_callback'=>$member,'callback'=>[self::class,'update']],
            ['methods'=>'DELETE','permission_callback'=>$member,'callback'=>[self::class,'delete']],
        ]);
        register_rest_route(self::NS,'/collections/(?P<id>\d+)/share',[
            'methods'=>'POST','permission_callback'=>$member,'callback'=>[self::class,'share_kit'],
        ]);
        register_rest_route(self::NS,'/collections/(?P<id>\d+)/items',[
            'methods'=>'POST','permission_callback'=>$member,'callback'=>[self::class,'add_item'],
        ]);
        register_rest_route(self::NS,'/collections/(?P<id>\d+)/items/(?P<product_id>\d+)',[
            'methods'=>'DELETE','permission_callback'=>$member,'callback'=>[self::class,'remove_item'],
        ]);
        register_rest_route(self::NS,'/favorites',[
            'methods'=>'GET','permission_callback'=>$member,'callback'=>[self::class,'favorites'],
        ]);
        register_rest_route(self::NS,'/favorites/toggle',[
            'methods'=>'POST','permission_callback'=>$member,'callback'=>[self::class,'favorite_toggle'],
        ]);
        register_rest_route(self::NS,'/favorites/merge',[
            'methods'=>'POST','permission_callback'=>$member,'callback'=>[self::class,'merge'],
        ]);
        register_rest_route(self::NS,'/library',[
            'methods'=>'GET','permission_callback'=>$public,'callback'=>[self::class,'library'],
        ]);
        register_rest_route(self::NS,'/people',[
            'methods'=>'GET','permission_callback'=>$public,'callback'=>[self::class,'people'],
        ]);
        register_rest_route(self::NS,'/products',[
            'methods'=>'GET','permission_callback'=>$public,'callback'=>[self::class,'products'],
        ]);
        register_rest_route(self::NS,'/advisor',[
            'methods'=>'POST','permission_callback'=>$public,'callback'=>[self::class,'advisor'],
        ]);
        register_rest_route(self::NS,'/migration-preview',[
            'methods'=>'GET','permission_callback'=>$admin,'callback'=>[self::class,'migration_preview'],
        ]);
    }
    private static function fail(string $code, string $message, int $status=400): WP_Error {
        return new WP_Error($code,$message,['status'=>$status]);
    }
    private static function owned(int $id): WP_Post|WP_Error {
        $p=get_post($id);
        if (!$p || $p->post_type!==ARC_Data::TYPE) return self::fail('arc_missing','Seznam nebyl nalezen.',404);
        if ((int)$p->post_author!==get_current_user_id()) return self::fail('arc_forbidden','Seznam není tvůj.',403);
        return $p;
    }
    private static function public_data(WP_User $u): array {
        $p=ARC_Profile::public_card((int)$u->ID,get_current_user_id());
        return $p?['id'=>$p['id'],'name'=>$p['alias'],'bio'=>$p['bio'],'interests'=>$p['interests'],'url'=>$p['url']]:[];
    }
    public static function me(): array {
        $uid=get_current_user_id();$p=ARC_Profile::own_card($uid);
        return ['user_id'=>$uid,'name'=>$p['alias'],'alias'=>$p['alias'],
            'visibility'=>$p['visibility'],'public'=>$p['visibility']==='public',
            'bio'=>$p['bio'],'interests'=>$p['interests'],'url'=>$p['url'],
            'sport_relation'=>$p['sport_relation']??'',
            'sport_profession'=>$p['sport_profession']??'',
            'sport_experience'=>$p['sport_experience']??'',
            'avatar'=>$p['avatar']??'',
            'marketing_optin'=>!empty($p['marketing_optin']),
            'affiliate_active'=>$p['affiliate_active']];
    }
    public static function save_me(WP_REST_Request $r): array|WP_Error {
        if(!$r->has_param('visibility') && $r->has_param('public')) {
            $r->set_param('visibility',rest_sanitize_boolean($r->get_param('public'))?'public':'private');
        }
        $result=ARC_Profile::save(get_current_user_id(),$r);
        return is_wp_error($result)?$result:self::me();
    }
    public static function collections(): array {
        ARC_Data::defaults(get_current_user_id());
        $out=[];
        foreach(ARC_Data::my_collections(get_current_user_id()) as $p) $out[]=ARC_Data::payload($p);
        return ['items'=>$out];
    }
    public static function create(WP_REST_Request $r): WP_REST_Response|WP_Error {
        $uid=get_current_user_id();
        $title=trim(sanitize_text_field((string)$r->get_param('title')));
        if(!$title) return self::fail('arc_title','Zadej název seznamu.');
        if(mb_strlen($title)>120) return self::fail('arc_title','Název je příliš dlouhý.');
        if(count(ARC_Data::my_collections($uid))>=40) return self::fail('arc_limit','Maximálně 40 seznamů na uživatele.',429);
        $copy=absint($r->get_param('copy_from'));
        if($copy && is_wp_error(self::owned($copy))) return self::fail('arc_copy','Výchozí seznam není tvůj.',403);
        $vis=$r->get_param('visibility')==='public';
        if($vis && !ARC_Data::public_profile($uid)) return self::fail('arc_profile_private','Nejdříve zveřejni svůj profil.',409);
        $id=wp_insert_post(['post_type'=>ARC_Data::TYPE,'post_title'=>$title,
            'post_status'=>$vis?'publish':'private','post_author'=>$uid],true);
        if(is_wp_error($id)) return $id;
        update_post_meta($id,ARC_Data::META_KIND,'kit');
        $source=absint($r->get_param('copy_from'));
        $previous=$source?self::owned($source):null;
        update_post_meta($id,ARC_Data::META_ITEMS, $previous instanceof WP_Post ? ARC_Data::items($source) : []);
        return new WP_REST_Response(ARC_Data::payload(get_post($id)),201);
    }
    public static function one(WP_REST_Request $r): array|WP_Error {
        $p=get_post(absint($r['id']));
        if (!$p || $p->post_type!==ARC_Data::TYPE || !ARC_Data::can_view($p,get_current_user_id()))
            return self::fail('arc_missing','Seznam není veřejný nebo neexistuje.',404);
        return ARC_Data::payload($p);
    }
    public static function update(WP_REST_Request $r): array|WP_Error {
        $p=self::owned(absint($r['id']));if(is_wp_error($p)) return $p;
        $kind=get_post_meta($p->ID,ARC_Data::META_KIND,true);
        $args=['ID'=>$p->ID];
        if($r->has_param('title') && $kind!=='favorites') {
            $title=trim(sanitize_text_field((string)$r->get_param('title')));
            if(!$title || mb_strlen($title)>120) return self::fail('arc_title','Neplatný název.');
            $args['post_title']=$title;
        }
        if($r->has_param('visibility')) {
            $vis=(string)$r->get_param('visibility');
            if(!in_array($vis,['private','public'],true)) return self::fail('arc_visibility','Neplatná viditelnost.');
            if($vis==='public' && ($kind==='favorites' || !ARC_Data::public_profile((int)$p->post_author)))
                return self::fail('arc_private','Oblíbené zůstávají soukromé. Pro veřejný balíček je nutný veřejný profil.',409);
            $args['post_status']=$vis==='public'?'publish':'private';
        }
        if(count($args)>1) {
            $result=wp_update_post($args,true);
            if(is_wp_error($result)) return $result;
        }
        return ARC_Data::payload(get_post($p->ID));
    }
    public static function delete(WP_REST_Request $r): array|WP_Error {
        $p=self::owned(absint($r['id']));if(is_wp_error($p)) return $p;
        if(get_post_meta($p->ID,ARC_Data::META_KIND,true)==='favorites')
            return self::fail('arc_default','Základní oblíbené nelze smazat.',409);
        wp_trash_post($p->ID);
        return ['ok'=>true,'id'=>$p->ID];
    }
    public static function add_item(WP_REST_Request $r): array|WP_Error {
        $p=self::owned(absint($r['id']));if(is_wp_error($p)) return $p;
        $id=absint($r->get_param('product_id'));
        if(!ARC_Data::valid_product($id)) return self::fail('arc_product','Produkt není dostupný v katalogu.',400);
        $done=ARC_Data::add_item($p->ID,$id,absint($r->get_param('qty')??1),
            rest_sanitize_boolean($r->get_param('owned')??false),(string)($r->get_param('note')??''));
        if(!$done) return self::fail('arc_limit','Maximálně 60 produktů v seznamu.',409);
        return ARC_Data::payload(get_post($p->ID));
    }
    public static function remove_item(WP_REST_Request $r): array|WP_Error {
        $p=self::owned(absint($r['id']));if(is_wp_error($p)) return $p;
        ARC_Data::remove_item($p->ID,absint($r['product_id']));
        return ARC_Data::payload(get_post($p->ID));
    }
    /** Reuse the existing wall-share ACL and notification delivery; never share private kits. */
    public static function share_kit(WP_REST_Request $r): array|WP_Error {
        $p=self::owned(absint($r['id']));if(is_wp_error($p))return $p;
        $uid=get_current_user_id();
        if(get_user_meta($uid,'_arc_community_suspended',true))return self::fail('arc_disabled','Účet nemůže publikovat.',403);
        if(!ARC_Data::can_view($p,0))return self::fail('arc_kit_private','Pro sdílení musí být balíček veřejný a tvůj profil veřejný.',403);
        $mode=sanitize_key((string)$r->get_param('mode'));
        if($mode==='wall'){
            // Reuse a living public post rather than create one on each WhatsApp/email click.
            $previous=absint(get_post_meta($p->ID,'_arc_kit_wall_post',true));
            $existing=$previous?get_post($previous):null;
            if($existing instanceof WP_Post && $existing->post_type===ARC_Wall::TYPE && (int)$existing->post_author===$uid && ARC_Wall::can_read($existing,0))
                return ['ok'=>true,'url'=>add_query_arg('arc_post',$previous,ARC_Config::app_url()),'post_id'=>$previous];
            $post=ARC_Wall::share($uid,'kit',$p->ID,0,'Sdílím balíček výbavy: '.$p->post_title);
            if(is_wp_error($post))return $post;
            $newId=(int)$post['id'];update_post_meta($p->ID,'_arc_kit_wall_post',$newId);
            return ['ok'=>true,'url'=>add_query_arg('arc_post',$newId,ARC_Config::app_url()),'post_id'=>$newId];
        }
        if($mode!=='friends')return self::fail('arc_kit_share','Neznámý způsob sdílení.');
        $raw=$r->get_param('target_ids');
        if(!is_array($raw))return self::fail('arc_kit_share','Vyber přátele.');
        $targets=array_values(array_unique(array_map('absint',$raw)));
        if(!$targets || count($targets)>5)return self::fail('arc_kit_share','Vyber 1 až 5 přátel.');
        foreach($targets as $target) {
            if(!$target || $target===$uid || !ARC_Relations::connected($uid,$target))return self::fail('arc_kit_share','Sdílení je možné pouze potvrzeným přátelům.',403);
        }
        $ids=[];
        foreach($targets as $target) {
            $post=ARC_Wall::share($uid,'kit',$p->ID,$target,'Podívej se na můj balíček výbavy: '.$p->post_title);
            if(is_wp_error($post))return ['ok'=>false,'sent'=>count($ids),'post_ids'=>$ids,'message'=>'Část balíčků byla odeslána. '.$post->get_error_message()];
            $ids[]=(int)$post['id'];
        }
        return ['ok'=>true,'sent'=>count($ids),'post_ids'=>$ids];
    }
    public static function favorites(): array|WP_Error {
        $id=ARC_Data::defaults(get_current_user_id());
        if(!$id) return self::fail('arc_store','Oblíbené se nepodařilo založit.',500);
        return ARC_Data::payload(get_post($id));
    }
    public static function favorite_toggle(WP_REST_Request $r): array|WP_Error {
        $pid=absint($r->get_param('product_id'));
        if(!ARC_Data::valid_product($pid)) return self::fail('arc_product','Neplatný produkt.');
        $id=ARC_Data::defaults(get_current_user_id());
        if(!$id) return self::fail('arc_store','Oblíbené se nepodařilo otevřít.',500);
        $found=false;
        foreach(ARC_Data::items($id) as $row) if($row['product_id']===$pid) $found=true;
        if($found) ARC_Data::remove_item($id,$pid);
        elseif(!ARC_Data::add_item($id,$pid)) return self::fail('arc_limit','Limit 60 oblíbených produktů.',409);
        return ['added'=>!$found,'collection'=>ARC_Data::payload(get_post($id))];
    }
    public static function merge(WP_REST_Request $r): array|WP_Error {
        $raw=$r->get_param('ids');
        if(!is_array($raw) || count($raw)>60) return self::fail('arc_ids','Neplatný seznam produktů.');
        $id=ARC_Data::defaults(get_current_user_id());
        if(!$id) return self::fail('arc_store','Oblíbené se nepodařilo otevřít.',500);
        $added=0;
        foreach(array_unique(array_map('absint',$raw)) as $pid) {
            if(!ARC_Data::valid_product($pid)) continue;
            $current=ARC_Data::items($id);
            if(count($current)>=60) break;
            if(in_array($pid,array_column($current,'product_id'),true)) continue;
            if(ARC_Data::add_item($id,$pid)) $added++;
        }
        return ['added'=>$added,'collection'=>ARC_Data::payload(get_post($id))];
    }
    public static function library(WP_REST_Request $r): array {
        $page=max(1,min(100,absint($r->get_param('page')?:1)));
        $posts=get_posts(['post_type'=>ARC_Data::TYPE,'post_status'=>'publish','numberposts'=>40,
            'offset'=>($page-1)*20,'orderby'=>'date','order'=>'DESC']);
        $out=[];
        foreach($posts as $p) if(ARC_Data::can_view($p,0)) $out[]=ARC_Data::payload($p);
        return ['items'=>$out,'page'=>$page];
    }
    public static function people(WP_REST_Request $r): array {
        $page=max(1,min(100,absint($r->get_param('page')?:1)));
        $users=get_users(['meta_key'=>ARC_Data::META_PUBLIC,'meta_value'=>'1','number'=>20,'offset'=>($page-1)*20,'orderby'=>'registered','order'=>'DESC']);
        return ['items'=>array_values(array_filter(array_map([self::class,'public_data'],$users))),'page'=>$page];
    }
    public static function products(WP_REST_Request $r): array {
        $ids=$r->get_param('ids');
        if(is_array($ids)) $ids=array_slice(array_map('absint',$ids),0,60);
        else $ids=array_slice(array_map('absint',explode(',',(string)$ids)),0,60);
        $search=sanitize_text_field((string)($r->get_param('search')??''));
        if(!function_exists('wc_get_product')) return ['items'=>[]];
        $args=['post_type'=>'product','post_status'=>'publish','posts_per_page'=>$ids?min(60,count($ids)):20];
        if($ids) { $args['post__in']=$ids; $args['orderby']='post__in'; }
        elseif($search) {$args['s']=mb_substr($search,0,100);$args['posts_per_page']=12;}
        else return ['items'=>[]];
        $rows=[];
        foreach(get_posts($args) as $p) {
            $product=wc_get_product($p->ID);if(!$product) continue;
            $rows[]=['id'=>$p->ID,'title'=>$p->post_title,'url'=>get_permalink($p->ID),
                'image'=>get_the_post_thumbnail_url($p->ID,'woocommerce_thumbnail')?:'',
                'price_html'=>ARC_Price::label($product),
                'in_stock'=>$product->is_in_stock(),'purchasable'=>$product->is_purchasable()];
        }
        return ['items'=>$rows];
    }
    public static function advisor(WP_REST_Request $r): WP_REST_Response|WP_Error {
        // Integration point only. No outgoing AI call and no credentials stored by AR Community.
        // Same-origin nonce + small per-visitor ceiling before any future paid provider call.
        $nonce=(string)$r->get_header('X-WP-Nonce');
        if(!wp_verify_nonce($nonce,'wp_rest')) return self::fail('arc_nonce','Zabezpečení požadavku vypršelo. Obnov stránku.',403);
        $visitor=get_current_user_id()?'user:'.get_current_user_id():'guest:'.sanitize_text_field((string)($_SERVER['REMOTE_ADDR']??'unknown'));
        $key='arc_advisor_ratelimit_'.substr(wp_hash($visitor),0,30);
        $count=(int)get_transient($key);
        if($count>=8) return self::fail('arc_ai_limit','Limit dotazů. Zkus to později.',429);
        $provider=apply_filters('arc_advisor_provider',null);
        if(!is_callable($provider)) return self::fail('arc_advisor_not_ready','Osobní poradce zatím není zapojen.',501);
        $text=mb_substr(sanitize_textarea_field((string)$r->get_param('message')),0,1200);
        if(!$text) return self::fail('arc_message','Napiš dotaz.');
        set_transient($key,$count+1,10*MINUTE_IN_SECONDS);
        try { $result=call_user_func($provider,['message'=>$text,'user_id'=>get_current_user_id()]); }
        catch(Throwable $e) { error_log('Fresh Community advisor failure: '.$e->getMessage());return self::fail('arc_provider','Poradce nyní není dostupný.',502); }
        if(is_wp_error($result)) return $result;
        if(!is_array($result)) return self::fail('arc_provider','Neplatná odpověď poradce.',502);
        return new WP_REST_Response($result,200);
    }
    public static function migration_preview(WP_REST_Request $r): array {
        $type=sanitize_key((string)get_option('arc_legacy_type','post'));
        $obj=get_post_type_object($type);
        if(!$obj) return ['post_type'=>$type,'error'=>'Typ příspěvku neexistuje.'];
        $posts=get_posts(['post_type'=>$type,'post_status'=>['publish','draft','private'],'posts_per_page'=>30]);
        $rows=[];
        foreach($posts as $p) {
            $u=get_userdata((int)$p->post_author);
            $rows[]=['id'=>$p->ID,'title'=>$p->post_title,'author_id'=>(int)$p->post_author,
                'author'=>$u?$u->display_name:'', 'warning'=>'Autor příspěvku není automaticky osoba na profilu.'];
        }
        return ['post_type'=>$type,'total'=>wp_count_posts($type),'sample'=>$rows,'automatic_migration'=>false];
    }
}
