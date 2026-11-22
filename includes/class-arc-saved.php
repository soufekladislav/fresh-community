<?php
defined('ABSPATH') || exit;
/** Single user's read-later and product favorites. Reuses existing ARC storage and ACL. */
final class ARC_Saved {
    public static function page(): ?WP_Post {
        $existing=get_page_by_path('ulozene', OBJECT, 'page');
        return $existing instanceof WP_Post && $existing->post_status==='publish' ? $existing : null;
    }
    public static function url(string $tab='posts'): string {
        $page=self::page();
        $base=$page?get_permalink($page):(
            function_exists('wc_get_account_endpoint_url')?wc_get_account_endpoint_url('ar-community'):home_url('/muj-ucet/')
        );
        if(!$page)return $base.'#arc-saved-posts';
        return $base; // Combined screen: saved posts + favorite products.
    }
    /** One-time, non-destructive creation of the /ulozene/ page; never override existing content. */
    public static function ensure_page(): void {
        if(get_option('arc_saved_page_checked_v1'))return;
        $existing=get_page_by_path('ulozene',OBJECT,'page');
        if($existing instanceof WP_Post){
            update_option('arc_saved_page_checked_v1',1,false);
            return;
        }
        $id=wp_insert_post([
            'post_type'=>'page','post_status'=>'publish','post_name'=>'ulozene',
            'post_title'=>'Uložené','post_content'=>'[ar_community_saved]',
            'post_author'=>0,'comment_status'=>'closed',
        ],true);
        if(!is_wp_error($id) && $id){
            update_option('arc_saved_page_checked_v1',1,false);
            update_option('arc_saved_page_id',(int)$id,false);
        }
    }
    private static function assets(): void {
        ARC_Shortcodes::assets();
        wp_enqueue_script('arc-saved');
        // arc-community is a dependency and provides the existing favorite toggle/guest merge.
        wp_enqueue_style('arc-saved');
    }
    /** Lightweight favorites badge in the existing Elementor HTML header (desktop + mobile). */
    public static function enqueue_header_counter(): void {
        if(is_admin())return;
        wp_enqueue_script('arc-menu-saved',ARC_URL.'assets/header-saved.js',[],ARC_VERSION,true);
        wp_localize_script('arc-menu-saved','ARCMenuSaved',[
            'root'=>esc_url_raw(rest_url('ar-community/v1/')),
            'nonce'=>wp_create_nonce('wp_rest'),
            'loggedIn'=>is_user_logged_in(),
        ]);
    }
    public static function menu_icon($atts=[]): string {
        self::assets();
        $atts=shortcode_atts(['label'=>'0'],(array)$atts,'ar_community_saved_icon');
        $showLabel=(string)$atts['label']==='1';
        $count=0;
        if(is_user_logged_in()){
            $collections=ARC_Data::my_collections(get_current_user_id());
            foreach($collections as $item){
                if(get_post_meta($item->ID,ARC_Data::META_KIND,true)==='favorites'){
                    $count=count(ARC_Data::items($item->ID));break;
                }
            }
        }
        return '<a class="arc-saved-menu-icon" data-arc-saved-menu href="'.esc_url(self::url('products')).'" aria-label="Uložené – oblíbené produkty" title="Uložené">'
            .'<span aria-hidden="true" class="arc-saved-menu-symbol"><svg viewBox="0 0 24 24" width="21" height="21" fill="none" stroke="currentColor" stroke-width="1.9" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M6 4.75A1.75 1.75 0 0 1 7.75 3h8.5A1.75 1.75 0 0 1 18 4.75V21l-6-4-6 4V4.75Z"/></svg></span>'
            .'<span class="arc-saved-menu-label"'.($showLabel?'':' hidden').'>Uložené</span>'
            .'<span class="arc-saved-menu-count" data-arc-fav-count'.($count?'':' hidden').'>'.(int)$count.'</span></a>';
    }
    /** In Elementor Loop Item / Single Post the current WordPress post ID is inferred. */
    public static function article_button($atts=[]): string {
        $atts=shortcode_atts(['post_id'=>0,'label'=>'0'],(array)$atts,'ar_community_save_article');
        $id=absint($atts['post_id']);
        if(!$id){$cur=get_post();if($cur && $cur->post_type==='post')$id=(int)$cur->ID;}
        if(!$id && is_singular('post'))$id=get_queried_object_id();
        $post=$id?get_post($id):null;
        if(!$post || $post->post_type!=='post' || $post->post_status!=='publish' || $post->post_password!=='')return '';
        wp_enqueue_style('arc-social-pages',ARC_URL.'assets/social-pages.css',[],ARC_VERSION);
        wp_enqueue_script('arc-article-bookmark',ARC_URL.'assets/article-bookmark.js',[],ARC_VERSION,true);
        wp_localize_script('arc-article-bookmark','ARCBookmark',[
          'root'=>esc_url_raw(rest_url('ar-community/v1/')),'nonce'=>wp_create_nonce('wp_rest'),
          'login'=>wp_login_url(get_permalink($id)),'loggedIn'=>is_user_logged_in()
        ]);
        $saved=is_user_logged_in() && in_array('article:'.$id,(array)get_user_meta(get_current_user_id(),'_arc_saved_wall_items',true),true);
        $label=(string)$atts['label']==='1';
        return '<button type="button" class="arc-article-bookmark'.($saved?' is-saved':'').'" data-arc-article-save="'.(int)$id.'" aria-pressed="'.($saved?'true':'false').'" aria-label="'.($saved?'Odebrat článek z uložených':'Uložit článek na později').'" title="'.($saved?'Odebrat z uložených':'Uložit na později').'">'
          .'<svg viewBox="0 0 24 24" width="21" height="21" fill="none" stroke="currentColor" stroke-width="1.9" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M6 4.75A1.75 1.75 0 0 1 7.75 3h8.5A1.75 1.75 0 0 1 18 4.75V21l-6-4-6 4V4.75Z"/></svg>'
          .($label?'<span data-arc-article-label>'.($saved?'Uloženo':'Uložit').'</span>':'').'</button>';
    }
    /** Client rendering lets guests see their locally-stored product favorites too. */
    public static function favorite_products($atts=[]): string {
        self::assets();
        $atts=shortcode_atts(['limit'=>60,'compact'=>0],(array)$atts,'ar_community_favorite_products');
        $limit=max(1,min(60,absint($atts['limit'])));
        $compact=!empty($atts['compact']);
        return '<div class="arc-favorite-products'.($compact?' arc-favorite-products-compact':'').'"'
            .' data-arc-favorites-list data-limit="'.esc_attr((string)$limit).'" aria-live="polite">'
            .'<p class="arc-small">Načítám oblíbené produkty…</p></div>';
    }
    private static function allowed_saved(int $uid): array {
        $saved=get_user_meta($uid,'_arc_saved_wall_items',true);
        if(!is_array($saved))return [];
        $out=[];
        foreach(array_reverse($saved) as $key){
            if(!is_string($key) || !preg_match('/^(community|article):(\d+)$/',$key,$m))continue;
            $type=$m[1];$id=(int)$m[2];$post=get_post($id);
            if(!$post instanceof WP_Post)continue;
            if($type==='community'){
                if(!ARC_Wall::can_read($post,$uid))continue;
                $body=trim(wp_strip_all_tags($post->post_content));
                $title=wp_trim_words($body,17,'…')?:'Komunitní příspěvek';
                $image=ARC_Wall::photo((int)get_post_meta($id,'_arc_image_id',true),(int)$post->post_author);
                $url=add_query_arg('arc_post',$id,ARC_Config::app_url()); // Feed only: private CPT has no public permalink.
                $kind='Příspěvek';
            }else{
                if($post->post_type!=='post' || $post->post_status!=='publish' || $post->post_password!=='')continue;
                $title=get_the_title($post);$image=get_the_post_thumbnail_url($id,'medium')?:'';
                $url=get_permalink($post);$kind='Magazín';
            }
            $out[]=['type'=>$type,'id'=>$id,'title'=>$title,'image'=>$image,'url'=>$url,'kind'=>$kind];
        }
        return $out;
    }
    /** $limit = 0 means the paged full list; otherwise an account preview. */
    public static function saved_posts_markup(int $limit=0,bool $pager=true): string {
        self::assets();
        if(!is_user_logged_in())return '<p class="arc-small">Pro zobrazení uložených příspěvků se prosím přihlas.</p>';
        $items=self::allowed_saved(get_current_user_id());
        if(!$items)return '<p class="arc-small" data-arc-saved-empty>Zatím nemáš uložené příspěvky ani články.</p>';
        $page=$pager && !$limit?max(1,min(100,absint($_GET['saved_page']??1))):1;
        $size=$limit?:24;
        $portion=array_slice($items,($page-1)*$size,$size);
        $out='<div class="arc-saved-post-list" data-arc-saved-posts aria-live="polite">';
        foreach($portion as $item){
            $out.='<article class="arc-saved-post" data-arc-saved-post-row>';
            $out.='<a href="'.esc_url($item['url']).'" class="arc-saved-post-link">';
            if($item['image'])$out.='<img src="'.esc_url($item['image']).'" alt="" loading="lazy">';
            else $out.='<span class="arc-saved-post-placeholder" aria-hidden="true">★</span>';
            $out.='<span><small>'.esc_html($item['kind']).'</small><strong>'.esc_html($item['title']).'</strong></span></a>';
            $out.='<button type="button" class="arc-saved-remove" data-arc-unsave-post="'.esc_attr($item['type']).'" data-post-id="'.(int)$item['id'].'" aria-label="Odebrat z uložených: '.esc_attr(wp_strip_all_tags($item['title'])).'" title="Odebrat z uložených"><span aria-hidden="true">×</span></button>';
            $out.='</article>';
        }
        $out.='</div>';
        if(!$limit && count($items)>$size){
            $pages=(int)ceil(count($items)/$size);
            $out.='<nav class="arc-saved-pagination" aria-label="Stránkování uložených příspěvků">';
            if($page>1)$out.='<a href="'.esc_url(add_query_arg('saved_page',$page-1,self::url('posts'))).'">← Novější</a>';
            $out.='<span>Strana '.(int)$page.' / '.(int)$pages.'</span>';
            if($page<$pages)$out.='<a href="'.esc_url(add_query_arg('saved_page',$page+1,self::url('posts'))).'">Další →</a>';
            $out.='</nav>';
        }
        return $out;
    }
    public static function saved_posts($atts=[]): string {
        $atts=shortcode_atts(['limit'=>0],(array)$atts,'ar_community_saved_posts');
        return '<div class="arc-saved-standalone">'.self::saved_posts_markup(absint($atts['limit']),true).'</div>';
    }
    /** Products and editable kits live side by side. The existing CPT and item metadata are unchanged. */
    public static function overview($atts=[]): string {
        // Shared public kits are rendered through the existing access-controlled library view.
        if(isset($_GET['ar_kit']) && absint($_GET['ar_kit']))return ARC_Shortcodes::library();
        self::assets();
        wp_enqueue_style('arc-kit-manager',ARC_URL.'assets/bundles.css',['arc-saved'],ARC_VERSION);
        wp_enqueue_script('arc-kit-manager',ARC_URL.'assets/bundles.js',['arc-community'],ARC_VERSION,true);
        return '<div class="arc-saved-page arc-wrap"><h2>Uložené</h2>
          <p class="arc-small">Tvoje oblíbené produkty, balíčky a obsah na později.</p>
          <div class="arc-saved-shop-grid">
            <section class="arc-saved-section arc-saved-shop-products" id="arc-saved-products"><h3>Oblíbené produkty</h3>'.self::favorite_products(['limit'=>60]).'</section>
            <section class="arc-saved-section arc-kit-section" id="arc-kit-manager" data-arc-kits>
              <h3>Moje balíčky</h3><p class="arc-small">Poskládej vlastní výbavu. Sdílení e-mailem a WhatsAppem vytvoří veřejný příspěvek na zdi. Přátelům lze sdílet pouze veřejné balíčky.</p>
              <form data-arc-kit-new class="arc-kit-create"><label class="screen-reader-text" for="arc-kit-new-title">Název balíčku</label><input id="arc-kit-new-title" name="title" maxlength="120" placeholder="Nový balíček…" required><button type="submit">＋ Vytvořit</button></form>
              <div data-arc-kit-list aria-live="polite"><p>Načítám balíčky…</p></div>
              <div data-arc-kit-editor class="arc-kit-editor" hidden><h4 data-arc-kit-edit-title>Upravit balíček</h4>
                <form data-arc-kit-settings class="arc-kit-settings"><input name="title" maxlength="120" required aria-label="Název balíčku"><label><input type="checkbox" name="public"> Veřejný balíček</label><button type="submit">Uložit</button><button type="button" data-arc-kit-delete class="arc-kit-danger">Smazat</button></form>
                <form data-arc-kit-search class="arc-kit-search"><input name="query" minlength="2" placeholder="Přidat produkt – hledat…" required><button type="submit">Hledat</button></form>
                <div data-arc-kit-results></div><div data-arc-kit-items></div>
                <button type="button" data-arc-kit-close class="arc-kit-close">Zavřít úpravy</button>
              </div>
              <div class="arc-kit-share" data-arc-kit-share hidden>
                <h4>Poslat balíček přátelům</h4><p class="arc-small">Vyber jednoho nebo více potvrzených přátel. Každý dostane příspěvek na své zdi a oznámení.</p>
                <div data-arc-kit-contacts></div><button type="button" data-arc-kit-send>Odeslat vybraným</button><button type="button" data-arc-kit-share-close>Storno</button>
              </div>
              <p class="arc-kit-status" data-arc-kit-status role="status" aria-live="polite"></p>
            </section>
          </div>
          <section class="arc-saved-section" id="arc-saved-posts"><h3>Uložené články a příspěvky</h3>'.self::saved_posts_markup(0,true).'</section>
        </div>';
    }
}
