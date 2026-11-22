<?php
defined('ABSPATH') || exit;
final class ARC_Data {
    public const TYPE = 'arc_collection';
    public const META_ITEMS = '_arc_items';
    public const META_KIND = '_arc_kind';
    public const META_PUBLIC = '_arc_public_profile';
    public const META_BIO = '_arc_profile_bio';
    public const META_INTERESTS = '_arc_profile_interests';
    public static function register_post_type(): void {
        register_post_type(self::TYPE, [
            'labels'=>['name'=>'AR balíčky a oblíbené','singular_name'=>'AR seznam','menu_name'=>'Seznamy výbavy'],
            'public'=>false,'publicly_queryable'=>false,'exclude_from_search'=>true,
            'show_ui'=>true,'show_in_menu'=>'ar-community','show_in_rest'=>false,
            'supports'=>['title','author'],'capability_type'=>'post','map_meta_cap'=>true,
            'rewrite'=>false,'query_var'=>false,
        ]);
    }
    public static function public_profile(int $uid): bool {
        return $uid>0 && (string)get_user_meta($uid,self::META_PUBLIC,true)==='1';
    }
    public static function my_collections(int $uid): array {
        return get_posts(['post_type'=>self::TYPE,'post_status'=>['publish','private'], 'author'=>$uid,
            'posts_per_page'=>150,'orderby'=>'date','order'=>'DESC','suppress_filters'=>false]);
    }
    public static function defaults(int $uid): int {
        foreach (self::my_collections($uid) as $post) {
            if (get_post_meta($post->ID,self::META_KIND,true)==='favorites') return (int)$post->ID;
        }
        $id=wp_insert_post(['post_type'=>self::TYPE,'post_title'=>'Moje oblíbené','post_status'=>'private','post_author'=>$uid],true);
        if (is_wp_error($id)) return 0;
        update_post_meta($id,self::META_KIND,'favorites');
        update_post_meta($id,self::META_ITEMS,[]);
        return (int)$id;
    }
    public static function items(int $id): array {
        $rows=get_post_meta($id,self::META_ITEMS,true);
        if (!is_array($rows)) return [];
        $items=[];
        foreach($rows as $entry) {
            if (!is_array($entry)) continue;
            $pid=absint($entry['product_id']??0);
            if(!$pid) continue;
            $items[$pid]=[
                'product_id'=>$pid,
                'qty'=>max(1,min(99,absint($entry['qty']??1))),
                'owned'=>!empty($entry['owned']),
                'note'=>sanitize_textarea_field((string)($entry['note']??'')),
            ];
        }
        return array_values($items);
    }
    public static function valid_product(int $id): bool {
        if(!$id || get_post_type($id)!=='product' || get_post_status($id)!=='publish') return false;
        return function_exists('wc_get_product') && (bool)wc_get_product($id);
    }
    public static function add_item(int $id, int $product, int $qty=1, bool $owned=false, string $note=''): bool {
        if (!self::valid_product($product)) return false;
        $items=self::items($id);$map=[];
        foreach($items as $item) $map[$item['product_id']]=$item;
        if (!isset($map[$product]) && count($map)>=60) return false;
        $map[$product]=['product_id'=>$product,'qty'=>max(1,min(99,$qty)),'owned'=>$owned,'note'=>sanitize_textarea_field($note)];
        update_post_meta($id,self::META_ITEMS,array_values($map));
        return true;
    }
    public static function remove_item(int $id, int $product): void {
        $items=array_values(array_filter(self::items($id),static fn($item)=>$item['product_id']!==$product));
        update_post_meta($id,self::META_ITEMS,$items);
    }
    public static function can_view(WP_Post $p, int $viewer): bool {
        if ($viewer && (int)$p->post_author===$viewer) return true;
        return $p->post_status==='publish' && self::public_profile((int)$p->post_author)
            && get_post_meta($p->ID,self::META_KIND,true)==='kit';
    }
    public static function payload(WP_Post $p, bool $with_items=true): array {
        $author=get_userdata((int)$p->post_author);
        $data=['id'=>(int)$p->ID,'title'=>$p->post_title,
            'visibility'=>$p->post_status==='publish'?'public':'private',
            'kind'=>(string)(get_post_meta($p->ID,self::META_KIND,true)?:'kit'),
            'author_id'=>(int)$p->post_author,'author_name'=>ARC_Profile::display_alias((int)$p->post_author),
            'updated_at'=>$p->post_modified_gmt,
        ];
        if($with_items) $data['items']=self::items($p->ID);
        return $data;
    }
    public static function public_url(int $id): string {
        $page=absint(get_option('arc_page_library',0));
        $base=$page ? get_permalink($page) : home_url('/komunita/');
        return add_query_arg('ar_kit',$id,$base);
    }
    public static function member_url(int $id): string {
        $page=absint(get_option('arc_page_profile',0));
        $base=$page ? get_permalink($page) : home_url('/profil/');
        return add_query_arg('ar_member',$id,$base);
    }
}
