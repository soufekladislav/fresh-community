<?php
/**
 * First-install pages. Explicit admin action, never creates or overwrites pages
 * automatically during activation or plugin updates.
 */
defined('ABSPATH') || exit;
final class ARC_Setup {
    private const PAGES = [
        'wall' => ['title'=>'Komunitní zeď','slug'=>'komunita','shortcode'=>'[ar_live]'],
        'account' => ['title'=>'Moje komunita','slug'=>'moje-komunita','shortcode'=>'[ar_community_account]'],
        'profile' => ['title'=>'Profil člena','slug'=>'profil','shortcode'=>'[ar_community_profile]'],
        'people' => ['title'=>'Členové','slug'=>'clenove','shortcode'=>'[ar_community_people]'],
        'friends' => ['title'=>'Přátelé','slug'=>'pratele','shortcode'=>'[ar_community_friends]'],
        'messages' => ['title'=>'Zprávy','slug'=>'zpravy','shortcode'=>'[ar_community_messages]'],
        'notifications' => ['title'=>'Oznámení','slug'=>'oznameni','shortcode'=>'[ar_live_notifications]'],
    ];
    public static function boot(): void {
        add_action('admin_menu',[self::class,'menu'],11);
        add_action('admin_post_arc_setup_pages',[self::class,'install_pages']);
        add_action('admin_post_arc_setup_invite',[self::class,'create_invitation']);
    }
    public static function menu(): void {
        add_submenu_page('ar-community','První instalace','První instalace','manage_options','arc-first-install',[self::class,'page']);
    }
    public static function id(string $key): int {
        $id=absint(get_option('arc_setup_page_'.$key,0));
        if ($id && get_post_status($id)==='publish') return $id;
        return 0;
    }
    public static function url(string $key): string {
        $id=self::id($key);
        if ($id) return (string)get_permalink($id);
        if ($key==='wall') return ARC_Config::app_url();
        $slug=self::PAGES[$key]['slug'] ?? '';
        return $slug ? home_url('/'.$slug.'/') : ARC_Config::app_url();
    }
    private static function source(WP_Post $page): string {
        return (string)$page->post_content.' '.(string)get_post_meta($page->ID,'_elementor_data',true);
    }
    private static function existing(string $key,array $spec): ?WP_Post {
        if($key==='wall' && ARC_Config::app_page_id()) {
            $page=get_post(ARC_Config::app_page_id());
            if($page instanceof WP_Post && str_contains(self::source($page),$spec['shortcode'])) return $page;
        }
        $known=['profile'=>'arc_page_profile','people'=>'arc_page_people'];
        if(isset($known[$key])){
            $id=absint(get_option($known[$key],0));
            if($id){$p=get_post($id);if($p instanceof WP_Post && str_contains(self::source($p),$spec['shortcode']))return $p;}
        }
        $saved=self::id($key);
        if ($saved) { $page=get_post($saved); if($page instanceof WP_Post)return $page; }
        $page=get_page_by_path($spec['slug'],OBJECT,'page');
        return $page instanceof WP_Post ? $page : null;
    }
    public static function install_pages(): void {
        if(!current_user_can('manage_options'))wp_die('Nemáš oprávnění.',403);
        check_admin_referer('arc_setup_pages');
        // AR sites have an existing /app/ layout. Never override it.
        if(ARC_Config::legacy_site() && !ARC_Config::app_page_id()) {
            $app=get_page_by_path('app',OBJECT,'page');
            if($app instanceof WP_Post) {
                update_option('arc_setup_page_wall',(int)$app->ID,false);
                $settings=ARC_Config::all();$settings['app_page_id']=(int)$app->ID;
                update_option(ARC_Config::OPTION,$settings,false);
            }
        }
        $created=0;$reused=0;$conflicts=[];
        foreach(self::PAGES as $key=>$spec) {
            if($key==='wall' && ARC_Config::legacy_site() && ARC_Config::app_page_id())continue;
            $page=self::existing($key,$spec);
            if($page instanceof WP_Post) {
                if(str_contains(self::source($page),$spec['shortcode'])) {
                    update_option('arc_setup_page_'.$key,(int)$page->ID,false);
                    $reused++;
                }else{
                    $conflicts[]=$spec['slug']; // Never overwrite unrelated content.
                }
                continue;
            }
            $id=wp_insert_post(['post_type'=>'page','post_status'=>'publish','post_title'=>$spec['title'],'post_name'=>$spec['slug'],'post_content'=>$spec['shortcode'],'comment_status'=>'closed'],true);
            if(is_wp_error($id)||!$id){$conflicts[]=$spec['slug'];continue;}
            update_option('arc_setup_page_'.$key,(int)$id,false);
            $created++;
        }
        $wall=self::id('wall');
        if($wall && !ARC_Config::app_page_id()) {
            $settings=ARC_Config::all();$settings['app_page_id']=$wall;
            update_option(ARC_Config::OPTION,$settings,false);
        }
        $profile=self::id('profile');if($profile)update_option('arc_page_profile',$profile,false);
        $people=self::id('people');if($people)update_option('arc_page_people',$people,false);
        wp_safe_redirect(add_query_arg(['page'=>'arc-first-install','created'=>$created,'reused'=>$reused,'conflicts'=>implode(',',$conflicts)],admin_url('admin.php')));
        exit;
    }
    public static function create_invitation(): void {
        if(!current_user_can('manage_options'))wp_die('Nemáš oprávnění.',403);
        check_admin_referer('arc_setup_invite');
        $url=ARC_Invites::issue(get_current_user_id());
        if($url) set_transient('arc_setup_invite_'.get_current_user_id(),$url,10*MINUTE_IN_SECONDS);
        wp_safe_redirect(admin_url('admin.php?page=arc-first-install&invitation='.($url?'1':'0')));exit;
    }
    public static function page(): void {
        if(!current_user_can('manage_options'))return;
        echo '<div class="wrap"><h1>Fresh Community – první instalace</h1>';
        echo '<p>Bezpečný průvodce založí pouze chybějící stránky. Existující stránky ani obsah nepřepíše. Nastavení identity a registrací je v <a href="'.esc_url(admin_url('admin.php?page=ar-community')).'">Nastavení Fresh Community</a>.</p>';
        if(isset($_GET['created'])) {
            echo '<div class="notice notice-info"><p>Nové stránky: '.absint($_GET['created']).'; již existující: '.absint($_GET['reused']??0).'.';
            $raw=sanitize_text_field((string)wp_unslash($_GET['conflicts']??''));
            if($raw)echo ' Konfliktní slugy (bez zásahu): '.esc_html($raw).'.';
            echo '</p></div>';
        }
        echo '<table class="widefat striped" style="max-width:920px"><thead><tr><th>Stránka</th><th>Shortcode</th><th>Stav</th></tr></thead><tbody>';
        foreach(self::PAGES as $key=>$spec) {
            $page=self::existing($key,$spec);
            $status=$page instanceof WP_Post
                ? (str_contains(self::source($page),$spec['shortcode']) ? 'Existuje' : 'Existuje jiný obsah – vyřešit ručně')
                : 'Chybí';
            echo '<tr><td>'.esc_html($spec['title']).'</td><td><code>'.esc_html($spec['shortcode']).'</code></td><td>';
            echo $page instanceof WP_Post ? '<a href="'.esc_url(get_edit_post_link($page->ID)).'">'.esc_html($status).'</a>' : esc_html($status);
            echo '</td></tr>';
        }
        echo '</tbody></table><form method="post" action="'.esc_url(admin_url('admin-post.php')).'">';
        echo '<input type="hidden" name="action" value="arc_setup_pages">';
        wp_nonce_field('arc_setup_pages');submit_button('Vytvořit chybějící komunitní stránky');
        echo '</form>';
        echo '<h2>Registrace</h2><p>Režim komunity nastavte v hlavním nastavení. Veřejnou WordPress registraci musí administrátor povolit v <a href="'.esc_url(admin_url('options-general.php')).'">Nastavení → Obecné</a> (Členství → Registrovat se může kdokoli); režim „jen na pozvánku“ potom na straně serveru odmítne registraci bez platné pozvánky.</p>';
        if(!get_option('users_can_register') && get_option('woocommerce_enable_myaccount_registration')!=='yes')echo '<p><strong>Upozornění:</strong> WordPress zatím nepovoluje registraci nových uživatelů.</p>';
        echo '<h2>Testovací pozvánka správce</h2><form method="post" action="'.esc_url(admin_url('admin-post.php')).'"><input type="hidden" name="action" value="arc_setup_invite">';
        wp_nonce_field('arc_setup_invite');submit_button('Vytvořit pozvánku', 'secondary');echo '</form>';
        $link=(string)get_transient('arc_setup_invite_'.get_current_user_id());
        if($link)echo '<p><input class="large-text" readonly value="'.esc_attr($link).'"></p>';
        echo '<p><strong>Před ostrým provozem:</strong> ověřte dva testovací účty, pozvánky, soukromí, chat, přihlášení, e-mail a PWA. Průvodce nemaže data ani nepřepisuje WordPress registrační nastavení.</p></div>';
    }
}
