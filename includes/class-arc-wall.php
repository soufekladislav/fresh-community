<?php
defined('ABSPATH') || exit;
/** A private CPT + explicit server ACL: no network content in public WP REST/RSS. */
final class ARC_Wall {
    public const TYPE='arc_social_post';
    public static function register(): void {
        register_post_type(self::TYPE,['label'=>'Komunitní příspěvky','public'=>false,'publicly_queryable'=>false,
            'exclude_from_search'=>true,'show_in_rest'=>false,'show_ui'=>true,'show_in_menu'=>'ar-community',
            'rewrite'=>false,'query_var'=>false,'supports'=>['editor','author'],'capability_type'=>'post','map_meta_cap'=>true]);
    }
    /** Audience cannot exceed the author's currently selected profile visibility. */
    public static function audience_allowed(int $uid,string $scope): bool {
        $v=ARC_Profile::visibility($uid);
        return $scope==='private'||($scope==='friend_wall'&&$v==='public')||($scope==='network'&&in_array($v,['network','public'],true))||($scope==='public'&&$v==='public');
    }
    public static function can_read(WP_Post $p,int $viewer): bool {
        if($p->post_type!==self::TYPE || $p->post_status!=='private') return false;
        $author=(int)$p->post_author;
        if($viewer && ($viewer===$author||user_can($viewer,'manage_options'))) return true;
        if(get_user_meta($author,'_arc_community_suspended',true))return false;
        $scope=(string)get_post_meta($p->ID,'_arc_audience',true);
        if(!self::audience_allowed($author,$scope))return false;
        if(get_post_meta($p->ID,'_arc_shared_id',true) && !self::shared_source_valid($p))return false;
        if($scope==='public') return true;
        if($scope==='network' && $viewer>0) return ARC_Relations::connected($author,$viewer);
        if($scope==='friend_wall' && $viewer>0){
            $target=absint(get_post_meta($p->ID,'_arc_wall_target_id',true));
            return $target>0 && ($viewer===$target || ARC_Relations::connected($target,$viewer)) && self::shared_source_valid($p);
        }
        return false;
    }
    /** A re-share never makes a restricted original public. Only publicly viewable originals are accepted. */
    private static function shared_source_valid(WP_Post $p): bool {
        $type=(string)get_post_meta($p->ID,'_arc_shared_type',true);
        $id=absint(get_post_meta($p->ID,'_arc_shared_id',true));
        if(!$type || !$id)return false;
        $orig=get_post($id);if(!$orig)return false;
        if($type==='kit')return $orig->post_type===ARC_Data::TYPE && ARC_Data::can_view($orig,0);
        if($type==='article')return $orig->post_type==='post' && $orig->post_status==='publish' && $orig->post_password==='';
        if($type==='community')return $orig->post_type===self::TYPE && $orig->post_status==='private'
          && get_post_meta($id,'_arc_audience',true)==='public'
          && self::audience_allowed((int)$orig->post_author,'public')
          && !get_user_meta((int)$orig->post_author,'_arc_community_suspended',true)
          && !get_post_meta($id,'_arc_shared_id',true);
        return false;
    }
    public static function article_payload(WP_Post $p,int $viewer): array {
        $verified=absint(get_post_meta($p->ID,'_arc_verified_author_user_id',true));
        $profile=$verified?ARC_Profile::public_card($verified,$viewer):null;
        $isAuthor=$viewer>0 && $viewer===(int)$p->post_author && user_can($viewer,'edit_post',$p->ID);
        return ['type'=>'article','id'=>(int)$p->ID,'title'=>get_the_title($p),
          'excerpt'=>wp_trim_words(wp_strip_all_tags(get_the_excerpt($p)),28),
          'url'=>get_permalink($p),'image'=>get_the_post_thumbnail_url($p->ID,'large')?:'',
          'author_alias'=>$profile?$profile['alias']:'AR Magazín',
          'author_profile'=>$profile?$profile['url']:'',
          'date'=>get_post_time('c',true,$p),'visibility'=>'public',
          'reactions'=>self::reaction_count((int)$p->ID),
          'reacted'=>$viewer>0?self::reacted((int)$p->ID,$viewer):false,
          'saved'=>$viewer>0?self::saved($viewer,'article',(int)$p->ID):false,
          'can_edit'=>$isAuthor,'edit_url'=>$isAuthor?get_edit_post_link($p->ID,'raw'):'',
          'comments_enabled'=>comments_open($p->ID),
          'comment_count'=>(int)get_comments_number($p->ID)];
    }
    public static function saved(int $viewer,string $type,int $id): bool {
        $items=get_user_meta($viewer,'_arc_saved_wall_items',true);
        return is_array($items) && in_array($type.':'.$id,$items,true);
    }
    public static function photo(int $id,int $owner): string {
        if(!$id || get_post_type($id)!=='attachment' || (int)get_post_field('post_author',$id)!==$owner || !wp_attachment_is_image($id)) return '';
        $url=wp_get_attachment_image_url($id,'medium_large');return $url?:'';
    }
    public static function payload(WP_Post $p,int $viewer): array {
        $author=(int)$p->post_author;
        $profileVisible=ARC_Profile::can_view($author,$viewer);
        $scope=(string)get_post_meta($p->ID,'_arc_audience',true);
        $imageId=absint(get_post_meta($p->ID,'_arc_image_id',true));
        $privateMedia=ARC_Wall_Private_Media::has($p->ID);
        $video=(string)get_post_meta($p->ID,'_arc_video',true);
        return ['type'=>'community','id'=>(int)$p->ID,'author_id'=>$profileVisible?$author:0,
          'author_alias'=>ARC_Profile::display_alias($author),
          'profile_url'=>$profileVisible?ARC_Data::member_url($author):'',
          'avatar'=>$profileVisible?ARC_Profile::avatar_url($author):'',
          'body'=>wp_strip_all_tags($p->post_content),'visibility'=>$scope,
          'url'=>$scope==='public'?add_query_arg('arc_post',(int)$p->ID,ARC_Config::app_url()):'',
          'image'=> $privateMedia?ARC_Wall_Private_Media::url((int)$p->ID):($scope==='public'?self::photo($imageId,$author):''),
          'video'=>$video,'date'=>get_post_time('c',true,$p),
          'can_edit'=>$viewer===$author || ($viewer && user_can($viewer,'manage_options')),
          'comments_enabled'=>!metadata_exists('post',$p->ID,'_arc_comments_enabled') || get_post_meta($p->ID,'_arc_comments_enabled',true)!=='0',
          'saved'=>$viewer>0?self::saved($viewer,'community',(int)$p->ID):false,
          'shared'=>null, 'shared_to'=>'',
          'reactions'=>self::reaction_count((int)$p->ID), 'reacted'=>$viewer>0?self::reacted((int)$p->ID,$viewer):false,
          'comment_count'=>(int)$GLOBALS['wpdb']->get_var($GLOBALS['wpdb']->prepare('SELECT COUNT(*) FROM '.self::comment_table()." WHERE post_id=%d AND status='approved'",(int)$p->ID)),
          'comments'=>self::comments((int)$p->ID,$viewer)];
    }
    public static function post_payload(WP_Post $p,int $viewer): array {
        $payload=self::payload($p,$viewer);
        $type=(string)get_post_meta($p->ID,'_arc_shared_type',true);
        $id=absint(get_post_meta($p->ID,'_arc_shared_id',true));
        if(!$type || !$id)return $payload;
        if(!self::shared_source_valid($p))return $payload;
        $source=get_post($id);
        if($type==='kit'){
            $kit=ARC_Data::payload($source);
            $images=[];
            foreach(array_slice($kit['items'],0,4) as $entry){
                if(!ARC_Data::valid_product((int)$entry['product_id']))continue;
                $product=wc_get_product((int)$entry['product_id']);
                $image=wp_get_attachment_image_url($product->get_image_id(),'woocommerce_thumbnail');
                if($image)$images[]=$image;
            }
            $payload['shared']=['type'=>'kit','id'=>$kit['id'],'title'=>$kit['title'],'author_alias'=>$kit['author_name'],
                'url'=>add_query_arg('ar_kit',$kit['id'],home_url('/ulozene/')),'images'=>$images,'count'=>count($kit['items'])];
        } else $payload['shared']=$type==='article'?self::article_payload($source,$viewer):self::payload($source,$viewer);
        $target=absint(get_post_meta($p->ID,'_arc_wall_target_id',true));
        if($target && ARC_Profile::can_view($target,$viewer))$payload['shared_to']=ARC_Profile::display_alias($target);
        return $payload;
    }
    public static function reaction_table(): string {global $wpdb;return $wpdb->prefix.'arc_wall_reactions';}
    public static function comment_table(): string {global $wpdb;return $wpdb->prefix.'arc_wall_comments';}
    public static function comment_like_table(): string {global $wpdb;return $wpdb->prefix.'arc_wall_comment_likes';}
    public static function reaction_count(int $pid): int {global $wpdb;$t=self::reaction_table();return (int)$wpdb->get_var($wpdb->prepare("SELECT COUNT(*) FROM $t WHERE post_id=%d",$pid));}
    public static function reacted(int $pid,int $uid): bool {global $wpdb;$t=self::reaction_table();return (bool)$wpdb->get_var($wpdb->prepare("SELECT id FROM $t WHERE post_id=%d AND user_id=%d",$pid,$uid));}
    public static function comments(int $pid,int $viewer): array {
        global $wpdb;$t=self::comment_table();$lt=self::comment_like_table();
        $rows=$wpdb->get_results($wpdb->prepare("SELECT id,user_id,body,parent_id,created_at FROM $t WHERE post_id=%d AND status='approved' ORDER BY id ASC LIMIT 150",$pid));
        if(!$rows)return [];
        $ids=array_map('intval',array_column($rows,'id'));
        // One aggregate query instead of a query for every comment in the feed.
        $values=implode(',',array_map('absint',$ids));
        $liked=$wpdb->get_results("SELECT comment_id,user_id FROM $lt WHERE comment_id IN ($values) LIMIT 10000");
        $counts=[];$my=[];
        foreach($liked as $reaction){$rid=(int)$reaction->comment_id;$counts[$rid]=($counts[$rid]??0)+1;if($viewer>0&&(int)$reaction->user_id===$viewer)$my[$rid]=true;}
        $out=[];foreach($rows as $c){$uid=(int)$c->user_id;$visible=ARC_Profile::can_view($uid,$viewer);$cid=(int)$c->id;
            $out[]=['id'=>$cid,'parent_id'=>(int)$c->parent_id,'alias'=>ARC_Profile::display_alias($uid),'user_id'=>$visible?$uid:0,'avatar'=>$visible?ARC_Profile::avatar_url($uid):'','profile_url'=>$visible?ARC_Data::member_url($uid):'','body'=>$c->body,'date'=>$c->created_at,'likes'=>$counts[$cid]??0,'liked'=>isset($my[$cid]),'can_manage'=>($viewer>0 && user_can($viewer,'manage_options'))];}
        return $out;
    }
    public static function create(int $uid,WP_REST_Request $r): array|WP_Error {
        $body=mb_substr(trim(sanitize_textarea_field((string)$r->get_param('body'))),0,3000);
        $scope=sanitize_key((string)$r->get_param('visibility'));
        if(!in_array($scope,['public','network','private'],true)) return new WP_Error('arc_visibility','Vyber viditelnost příspěvku.',['status'=>400]);
        if(!self::audience_allowed($uid,$scope))return new WP_Error('arc_profile_visibility','Nejdříve změň viditelnost profilu, aby odpovídala publiku příspěvku.',['status'=>403]);
        $video=esc_url_raw(trim((string)$r->get_param('video')));
        if($video && !self::allowed_video($video)) return new WP_Error('arc_video','Podporujeme pouze odkazy na YouTube.',['status'=>400]);
        $image=absint($r->get_param('image_id'));
        $privateToken=sanitize_text_field((string)$r->get_param('private_photo'));
        if($image && ($scope!=='public'||!self::photo($image,$uid))) return new WP_Error('arc_image','Veřejná fotografie musí patřit autorovi a příspěvek musí být veřejný.',['status'=>400]);
        if($privateToken && ($scope==='public' || $image || !ARC_Wall_Private_Media::valid($privateToken,$uid,$scope))) return new WP_Error('arc_private_image','Soukromou fotografii se nepodařilo ověřit. Nahraj ji prosím znovu.',['status'=>400]);
        if(mb_strlen($body)<2 && !$video && !$image && !$privateToken) return new WP_Error('arc_body','Přidej text, obrázek nebo video.',['status'=>400]);
        $key='arc_wall_rate_'.$uid;$history=get_transient($key);$history=is_array($history)?$history:[];
        $history=array_values(array_filter($history,static fn($t)=>$t>time()-3600));
        if(count($history)>=8) return new WP_Error('arc_rate','Limit 8 příspěvků za hodinu.',['status'=>429]);
        $id=wp_insert_post(['post_type'=>self::TYPE,'post_title'=>'Příspěvek','post_content'=>$body,'post_status'=>'private','post_author'=>$uid],true);
        if(is_wp_error($id)) return $id;
        update_post_meta($id,'_arc_audience',$scope);
        if($video) update_post_meta($id,'_arc_video',$video);
        update_post_meta($id,'_arc_comments_enabled','1');
        if($image) update_post_meta($id,'_arc_image_id',$image);
        if($privateToken) {
            $result=ARC_Wall_Private_Media::attach($privateToken,$uid,$scope,(int)$id);
            if(is_wp_error($result)) {wp_delete_post((int)$id,true);return $result;}
        }
        $history[]=time();set_transient($key,$history,HOUR_IN_SECONDS);
        ARC_Notifications::social_post($uid,(int)$id,$scope);
        return self::post_payload(get_post($id),$uid);
    }
    public static function allowed_video(string $url): bool {
        if(!wp_http_validate_url($url)) return false;
        $host=strtolower((string)wp_parse_url($url,PHP_URL_HOST));
        return in_array($host,['youtube.com','www.youtube.com','youtu.be','www.youtube-nocookie.com','m.youtube.com'],true);
    }
    public static function feed(int $viewer,int $page=1,string $filter='all'): array {
        global $wpdb;$page=max(1,min(70,$page));
         $filter=in_array($filter,['all','following','articles','saved'],true)?$filter:'all';
        $savedKeys=$filter==='saved'&&$viewer?get_user_meta($viewer,'_arc_saved_wall_items',true):[];
        $savedKeys=is_array($savedKeys)?array_flip(array_filter($savedKeys,'is_string')):[];
        $per=15;$descriptors=[];$limit=1100;
        // Guests see the public magazine; direct links to intentionally public member posts still work.
        if($viewer>0 && $filter!=='articles') {
            $rows=$wpdb->get_results($wpdb->prepare("SELECT ID,post_author,post_date_gmt FROM {$wpdb->posts} WHERE post_type=%s AND post_status='private' ORDER BY post_date_gmt DESC,ID DESC LIMIT %d",self::TYPE,$limit));
            $contacts=$viewer?array_flip(ARC_Relations::connected_ids($viewer)):[];
            $following=$viewer?array_flip(array_merge(ARC_Relations::following_ids($viewer),array_keys($contacts),[$viewer])):[];
            foreach($rows as $entry) {
                $author=(int)$entry->post_author;
                if(get_user_meta($author,'_arc_community_suspended',true) && $viewer!==$author && !user_can($viewer,'manage_options'))continue;
                $target=absint(get_post_meta((int)$entry->ID,'_arc_wall_target_id',true));
                if($filter==='following' && !isset($following[$author]) && !($target && isset($following[$target])))continue;
                if($viewer===$author || ($viewer && user_can($viewer,'manage_options'))) $allowed=true;
                else {
                    $visibility=(string)get_post_meta((int)$entry->ID,'_arc_audience',true);
                    $allowed=$visibility==='public'||($visibility==='network'&&isset($contacts[$author])) || ($visibility==='friend_wall' && $viewer && $target && ($viewer===$target || isset($contacts[$target])));
                }
                if(!self::audience_allowed($author,(string)get_post_meta((int)$entry->ID,'_arc_audience',true)) && $viewer!==$author && !($viewer && user_can($viewer,'manage_options')))continue;
                if(!$allowed || ($filter==='saved' && !isset($savedKeys['community:'.(int)$entry->ID])))continue;
                $descriptors[]=['type'=>'community','id'=>(int)$entry->ID,'date'=>(string)$entry->post_date_gmt];
            }
        }
        if($filter!=='following') {
            $articles=$wpdb->get_results("SELECT ID,post_date_gmt FROM {$wpdb->posts} WHERE post_type='post' AND post_status='publish' AND post_password='' ORDER BY post_date_gmt DESC,ID DESC LIMIT 900");
            foreach($articles as $entry) {
                if($filter==='saved' && !isset($savedKeys['article:'.(int)$entry->ID]))continue;
                $descriptors[]=['type'=>'article','id'=>(int)$entry->ID,'date'=>(string)$entry->post_date_gmt];
            }
        }
        usort($descriptors,static fn($a,$b)=>strcmp($b['date'],$a['date'])?:($b['id']<=>$a['id']));
        $offset=($page-1)*$per;$portion=array_slice($descriptors,$offset,$per);$items=[];
        foreach($portion as $d) {
            $p=get_post($d['id']);if(!$p)continue;
            if($d['type']==='community') {
                if(self::can_read($p,$viewer))$items[]=self::post_payload($p,$viewer);
            } else {
                $items[]=self::article_payload($p,$viewer);
            }
        }
        return ['items'=>$items,'page'=>$page,'has_more'=>count($descriptors)>($offset+$per),
          'note'=>'Články jsou původní publikace magazínu. Nejde o import ani automatické přiřazení osob.'];
    }
    public static function update(int $uid,WP_Post $p,WP_REST_Request $r): array|WP_Error {
        $body=mb_substr(trim(sanitize_textarea_field((string)$r->get_param('body'))),0,3000);
        $scope=sanitize_key((string)$r->get_param('visibility'));
        $target=absint(get_post_meta($p->ID,'_arc_wall_target_id',true));
        if($target){$scope='friend_wall';if(!self::audience_allowed($uid,$scope))return new WP_Error('arc_profile_visibility','Uprav viditelnost profilu před úpravou sdíleného příspěvku.',['status'=>403]);}
        elseif(!in_array($scope,['public','network','private'],true))return new WP_Error('arc_visibility','Neplatná viditelnost.',['status'=>400]);
        if(!$target && !self::audience_allowed($uid,$scope))return new WP_Error('arc_profile_visibility','Publikum příspěvku nesmí být širší než viditelnost profilu.',['status'=>403]);
        $video=esc_url_raw(trim((string)$r->get_param('video')));
        if($video&&!self::allowed_video($video))return new WP_Error('arc_video','Podporujeme pouze YouTube.',['status'=>400]);
        $image=absint($r->get_param('image_id'));
        if(!$image)$image=absint(get_post_meta($p->ID,'_arc_image_id',true));
        if($image&&($scope!=='public'||!self::photo($image,$uid)))return new WP_Error('arc_image','Soukromý příspěvek nesmí obsahovat veřejně přístupnou fotografii.',['status'=>400]);
        if(mb_strlen($body)<2 && !$video && !$image && !ARC_Wall_Private_Media::has((int)$p->ID) && !get_post_meta($p->ID,'_arc_shared_id',true))return new WP_Error('arc_body','Příspěvek nesmí být prázdný.',['status'=>400]);
        $res=wp_update_post(['ID'=>$p->ID,'post_content'=>$body],true);
        if(is_wp_error($res))return $res;
        update_post_meta($p->ID,'_arc_audience',$scope);
        if($video)update_post_meta($p->ID,'_arc_video',$video);else delete_post_meta($p->ID,'_arc_video');
        if($image)update_post_meta($p->ID,'_arc_image_id',$image);else delete_post_meta($p->ID,'_arc_image_id');
        if($r->has_param('comments_enabled'))update_post_meta($p->ID,'_arc_comments_enabled',rest_sanitize_boolean($r->get_param('comments_enabled'))?'1':'0');
        return self::post_payload(get_post($p->ID),$uid);
    }
    public static function share(int $uid,string $type,int $id,int $target,string $message): array|WP_Error {
        $original=get_post($id);
        if(!$original)return new WP_Error('arc_share','Původní obsah nebyl nalezen.',['status'=>404]);
        if($type==='community'){
            if($original->post_type!==self::TYPE || $original->post_status!=='private'
              || get_post_meta($id,'_arc_audience',true)!=='public'
              || !self::audience_allowed((int)$original->post_author,'public')
              || get_post_meta($id,'_arc_shared_id',true) || !self::can_read($original,$uid))
              return new WP_Error('arc_share','Sdílet lze pouze původní veřejný příspěvek.',['status'=>403]);
        }elseif($type==='article'){
            if($original->post_type!=='post'||$original->post_status!=='publish'||$original->post_password!=='')
              return new WP_Error('arc_share','Článek není veřejně dostupný.',['status'=>403]);
        }elseif($type==='kit'){
            if($original->post_type!==ARC_Data::TYPE || !ARC_Data::can_view($original,0) || (int)$original->post_author!==$uid)
                return new WP_Error('arc_share','Sdílet lze pouze vlastní veřejný balíček při veřejném profilu.',['status'=>403]);
        }else return new WP_Error('arc_share','Neznámý typ obsahu.',['status'=>400]);
        if($target && !self::audience_allowed($uid,'friend_wall')) return new WP_Error('arc_profile_visibility','Sdílení na zeď přítele vyžaduje veřejný profil autora.',['status'=>403]);
        if($target && ($target===$uid || !ARC_Relations::connected($uid,$target)))
          return new WP_Error('arc_share','Na zeď lze sdílet jen potvrzenému příteli.',['status'=>403]);
        $key='arc_share_rate_'.$uid;$limit=(int)get_transient($key);
        if($limit>=6)return new WP_Error('arc_share','Limit 6 sdílení za hodinu.',['status'=>429]);
        $idNew=wp_insert_post(['post_type'=>self::TYPE,'post_title'=>'Sdílený příspěvek',
          'post_content'=>mb_substr(sanitize_textarea_field($message),0,800),
          'post_status'=>'private','post_author'=>$uid],true);
        if(is_wp_error($idNew))return $idNew;
        update_post_meta($idNew,'_arc_audience',$target?'friend_wall':'public');
        update_post_meta($idNew,'_arc_shared_type',$type);
        update_post_meta($idNew,'_arc_shared_id',$id);
        update_post_meta($idNew,'_arc_comments_enabled','1');
        if($target){update_post_meta($idNew,'_arc_wall_target_id',$target);ARC_Notifications::friend_share($uid,$target,(int)$idNew);}
        else ARC_Notifications::social_post($uid,(int)$idNew,'public');
        set_transient($key,$limit+1,HOUR_IN_SECONDS);
        return self::post_payload(get_post($idNew),$uid);
    }

}
