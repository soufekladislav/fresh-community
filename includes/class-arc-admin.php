<?php
defined('ABSPATH') || exit;
final class ARC_Admin {
    public static function menu(): void {
        add_menu_page('Fresh Community','Fresh Community','manage_options','ar-community',[self::class,'page'],'dashicons-groups',58);
        add_submenu_page('ar-community','Nastavení Fresh Community','Nastavení','manage_options','ar-community',[self::class,'page']);
    }
    public static function save(): void {
        if(!current_user_can('manage_options')) wp_die('Nedostatečná oprávnění.',403);
        check_admin_referer('arc_save_settings');
        foreach(['profile','people','library','advisor'] as $key) {
            $id=absint($_POST['arc_page_'.$key]??0);
            update_option('arc_page_'.$key,$id && get_post_type($id)==='page'?$id:0,false);
        }
        if (isset($_POST['arc_google_client_id']) && !defined('ARC_GOOGLE_CLIENT_ID')) {
            $client = trim(sanitize_text_field((string)wp_unslash($_POST['arc_google_client_id'])));
            if ($client === '' || preg_match('/^[0-9]+-[a-z0-9-]+\.apps\.googleusercontent\.com$/i', $client))
                update_option('arc_google_client_id', $client, false);
        }
        ARC_Config::save_from_post($_POST);
        $type=sanitize_key((string)wp_unslash($_POST['arc_legacy_type']??'post')); 
        update_option('arc_legacy_type',post_type_exists($type)?$type:'post',false);
        wp_safe_redirect(add_query_arg('updated','1',admin_url('admin.php?page=ar-community')));exit;
    }
    public static function initialize_profiles(): void {
        if(!current_user_can('manage_options')) wp_die('Nedostatečná oprávnění.','',['response'=>403]);
        check_admin_referer('arc_initialize_profiles');
        global $wpdb;
        $start=absint($_POST['arc_last_user_id']??0);
        $users=$wpdb->get_col($wpdb->prepare("SELECT ID FROM {$wpdb->users} WHERE ID>%d ORDER BY ID ASC LIMIT 100",$start));
        $last=$start;
        foreach($users as $uid){$last=(int)$uid;ARC_Profile::initialize($last);}
        $more=(bool)$wpdb->get_var($wpdb->prepare("SELECT ID FROM {$wpdb->users} WHERE ID>%d ORDER BY ID ASC LIMIT 1",$last));
        wp_safe_redirect(add_query_arg(['page'=>'ar-community','arc_initialized'=>count($users),'arc_next'=>$more?$last:0,'arc_finished'=>$more?'0':'1'],admin_url('admin.php')));
        exit;
    }
    /** Switch only ALF's display page. Never deactivate Stripe billing or mutate subscriptions. */
    public static function move_legacy_app(): void {
        if(!current_user_can('manage_options'))wp_die('Nemáš oprávnění.',403);
        check_admin_referer('arc_move_legacy_app');
        if(!class_exists('ALF_Plugin'))wp_die('Původní aplikace ALF není aktivní.',400);
        $community=get_page_by_path('app');
        $legacy=get_page_by_path('premium/jidelnicek');
        if(!$community || !$legacy)wp_die('Chybí /app/ nebo /premium/jidelnicek/.',400);
        $communityContent=(string)$community->post_content.' '.(string)get_post_meta($community->ID,'_elementor_data',true);
        $legacyContent=(string)$legacy->post_content.' '.(string)get_post_meta($legacy->ID,'_elementor_data',true);
        if(!str_contains($communityContent,'[ar_live]') || !str_contains($legacyContent,'[adrenaline_life_app]'))
            wp_die('Stránky nemají požadované shortcody. Přesun nebyl proveden.',400);
        update_option('alf_app_page_id',(int)$legacy->ID,false);
        wp_safe_redirect(add_query_arg(['page'=>'ar-community','arc_legacy_moved'=>1],admin_url('admin.php')));exit;
    }
    public static function page(): void {
        if(!current_user_can('manage_options')) return;
        $status=ARC_Integrations::status();
        echo '<div class="wrap"><h1>Fresh Community '.esc_html(ARC_VERSION).'</h1>';
        if(isset($_GET['updated'])) echo '<div class="notice notice-success"><p>Nastavení uloženo.</p></div>';
        echo '<p>Konfigurace samostatné komunitní sítě. Uložením nastavení se nemění existující účty ani obsah.</p>';
        echo '<h2>Stav komponent</h2><table class="widefat striped"><tbody>';
        foreach(['woocommerce'=>'WooCommerce (volitelné)','affiliate'=>'Fresh Affiliate (volitelné)','fresh_sales'=>'Fresh Sales (jen vazba)','advisor_provider'=>'AI poradce – připojený provider'] as $key=>$label)
            echo '<tr><td>'.esc_html($label).'</td><td>'.($status[$key]?'<strong style="color:green">K dispozici</strong>':'Nenalezeno / zatím nevytvořeno').'</td></tr>';
        echo '</tbody></table><h2>Identita a funkce komunity</h2><form method="post" action="'.esc_url(admin_url('admin-post.php')).'">';
        echo '<input type="hidden" name="action" value="arc_save_settings">';wp_nonce_field('arc_save_settings');
        $config=ARC_Config::all();
        foreach(['name'=>'Název komunity','short_name'=>'Krátký název (PWA)','description'=>'Popis komunity'] as $field=>$label){
            echo '<p><label><strong>'.esc_html($label).'</strong><br><input class="regular-text" name="arc_'.esc_attr($field).'" value="'.esc_attr((string)$config[$field]).'" maxlength="'.($field==='description'?220:($field==='name'?80:28)).'"></label></p>';
        }
        echo '<p><label><strong>Hlavní stránka komunity</strong> ';wp_dropdown_pages(['name'=>'arc_app_page_id','selected'=>absint($config['app_page_id']),'show_option_none'=>'— vyberte stránku s [ar_live] —','option_none_value'=>0]);echo '</label></p>';
        echo '<p><label><strong>URL obchodu (volitelně)</strong><br><input class="regular-text" type="url" name="arc_shop_url" value="'.esc_attr((string)$config['shop_url']).'" placeholder="https://..."></label></p>';
        foreach(['show_shop'=>'Zobrazit produkty z WooCommerce','show_premium'=>'Zobrazit položky Premium','show_affiliate'=>'Zobrazit referenční program','show_youtube'=>'Nabídnout odesílání videa na YouTube','global_navigation'=>'Zobrazovat spodní navigaci na všech stránkách webu','woo_dashboard'=>'Nahradit úvodní nástěnku WooCommerce komunitou','sport_fields'=>'Zobrazovat sportovní údaje v profilu'] as $key=>$label){
            echo '<p><label><input type="checkbox" name="arc_'.esc_attr($key).'" value="1" '.checked(!empty($config[$key]),true,false).'> '.esc_html($label).'</label></p>';
        }
        echo '<h3>Vzhled instalované aplikace (PWA)</h3>';
        foreach (['icon_192'=>'Ikona 192×192 (URL z Knihovny médií)','icon_512'=>'Ikona 512×512 (URL z Knihovny médií)','theme_color'=>'Barva aplikace (#RRGGBB)','background_color'=>'Barva pozadí (#RRGGBB)'] as $key=>$label) {
            echo '<p><label><strong>'.esc_html($label).'</strong><br><input class="regular-text" name="arc_'.esc_attr($key).'" value="'.esc_attr((string)$config[$key]).'"></label></p>';
        }
        echo '<p class="description">Nevyplněná ikona použije existující ikonu Adrenaline Live pro původní instalaci nebo Site Icon WordPressu pro novou komunitu. Změna ikony či PWA stránky vyžaduje otestování instalované aplikace na zařízeních.</p>';
        echo '<h2>Další nastavení a stránky</h2>';
        foreach(['profile'=>'Sdílená šablona profilu /profil/','people'=>'Adresář Lidé (historický)','library'=>'Veřejné balíčky','advisor'=>'Osobní poradce'] as $key=>$label) {
            echo '<p><label><strong>'.esc_html($label).'</strong> ';wp_dropdown_pages(['name'=>'arc_page_'.$key,'selected'=>absint(get_option('arc_page_'.$key,0)),
                'show_option_none'=>'— není přiřazena —','option_none_value'=>0]);echo '</label></p>';
        }
        $types=get_post_types(['public'=>true],'objects');
        $selected=(string)get_option('arc_legacy_type','post');
        echo '<h2>Rychlé přihlášení Google</h2><p>OAuth Client ID (Web): <input name="arc_google_client_id" type="text" size="60" value="'.esc_attr(ARC_Google::client_id()).'" placeholder="123456.apps.googleusercontent.com" '.(defined('ARC_GOOGLE_CLIENT_ID')?'disabled':'').'></p>';
        echo '<p><strong>Redirect URI Google:</strong> <code>'.esc_html(ARC_Google::callback_uri()).'</code></p><p>Registrovat u Google Cloud jako Authorized redirect URI; authorized JavaScript origin je origin webu. Pro test na stagingu použít samostatnou web OAuth konfiguraci. Propojení se stávajícím WP účtem je výslovné.</p>';
        echo '<p><label><strong>Zdroj historických profilů „Lidé“ (pouze diagnostika)</strong> <select name="arc_legacy_type">';
        foreach($types as $type) echo '<option value="'.esc_attr($type->name).'" '.selected($selected,$type->name,false).'>'.esc_html($type->labels->name.' ('.$type->name.')').'</option>';
        echo '</select></label></p><p><button class="button button-primary">Uložit nastavení</button></p></form>';
        echo '<p><strong>Komunitní stránka:</strong> <code>'.esc_html(ARC_Config::app_url()).'</code> s <code>[ar_live]</code>. Název a funkce nastavíte výše.</p>';
        echo '<h2>Fresh Community / Shortcody pro Elementor a WP stránky</h2><p><code>[ar_live]</code> · <code>[ar_community_wall]</code> · <code>[ar_community_account]</code> · <code>[ar_community_people]</code> · <code>[ar_community_friends]</code> · <code>[ar_live_navigation]</code> · <code>[ar_community_profile]</code> · <code>[ar_community_library]</code> · <code>[ar_community_advisor]</code> · <code>[ar_live_premium]</code> · <code>[ar_community_submit_article]</code> · <code>[ar_community_favorite_button]</code></p>';
        if(ARC_Config::legacy_site()){
        echo '<h2>Diagnostika migrace bez zápisu</h2><p>Vybraný typ příspěvku: <code>'.esc_html($selected).'</code>. Jednoduchý náhled nemůže bezpečně rozpoznat osobu jen podle autora, protože historické příspěvky mohl vytvářet administrátor.</p>';
        $rows=get_posts(['post_type'=>$selected,'post_status'=>['publish','draft','private'],'posts_per_page'=>15]);
        echo '<table class="widefat striped"><thead><tr><th>ID</th><th>Historický příspěvek</th><th>Autor příspěvku</th><th>Ověřený profil</th></tr></thead><tbody>';
        foreach($rows as $p){$u=get_userdata($p->post_author);echo '<tr><td>'.(int)$p->ID.'</td><td>'.esc_html($p->post_title).'</td><td>'.esc_html($u?$u->display_name:'nenalezen').'</td><td>Nutné ověřit vazbu</td></tr>';}
        echo '</tbody></table>';
        echo '<p><strong>Migrace je záměrně vypnutá.</strong> Nejprve je potřeba určit skutečnou vazbu profil ↔ uživatel. Původní články se nemažou.</p>';
        echo '<p><strong>Eventy jsou vypnuté:</strong> Fresh Event je pouze analyzovaný samostatný plugin. V této verzi žádné eventové registrace ani vstupenky neběží.</p>';
        echo '<p><strong>Předplatné:</strong> Stávající Live/Stripe billing musí zůstat aktivní. Nové prémiové aplikace jsou zatím jen v katalogu.</p>';
                echo '<h2>Rozdělení dvou aplikací</h2>';
        $alf_page=absint(get_option('alf_app_page_id'));
        $alf_url=$alf_page?get_permalink($alf_page):'';
        echo '<p><strong>Stávající stránka starého jídelníčku (ALF):</strong> <code>'.esc_html($alf_url?:'není určená').'</code>. Předplatné a Stripe necháváme běžet.</p>';
        echo '<p>1. Připrav /app/ s <code>[ar_live]</code>. 2. Připrav /profil/ s <code>[ar_community_profile]</code> a nastav ji výše jako šablonu profilu. 3. Připrav /pratele/ s <code>[ar_community_friends]</code>. 4. Připrav /premium/ s <code>[ar_live_premium]</code>. 5. Na /premium/jidelnicek/ umísti starý shortcode <code>[adrenaline_life_app]</code> a až potom klikni níže. Stránku prémiového jídelníčku zpřístupni pouze oprávněným předplatitelům; samotný shortcode ALF má další vlastní kontrolu přístupu.</p>';
        if(isset($_GET['arc_legacy_moved']))echo '<div class="notice notice-success"><p>Stránka původní aplikace byla přepnuta do Premium. Billing a objednávky nebyly změněny.</p></div>';
        echo '<form method="post" action="'.esc_url(admin_url('admin-post.php')).'"><input type="hidden" name="action" value="arc_move_legacy_app">';
        wp_nonce_field('arc_move_legacy_app');
        echo '<button type="submit" class="button">Po kontrole stránek přesunout pouze staré rozhraní ALF do Premium</button></form>';
        }
        echo '<h2>Stávající WP účty – bezpečná inicializace profilů</h2>';
        echo '<p>Každý účet má jeden WP user ID. Nový síťový profil je neveřejný, pokud nebyl již dříve výslovně zveřejněn. Dávka vytvoří pouze chybějící user_meta a nezasahuje do WooCommerce ani Fresh Affiliate.</p>';
        if(isset($_GET['arc_initialized'])) echo '<p>Dokončeno v poslední dávce: '.absint($_GET['arc_initialized']).' uživatelů. '.(!empty($_GET['arc_finished']) && $_GET['arc_finished']==='1'?'Další účty již nezbývají.':'Můžeš pokračovat další dávkou.').'</p>';
        echo '<form method="post" action="'.esc_url(admin_url('admin-post.php')).'">';
        echo '<input type="hidden" name="action" value="arc_initialize_profiles">';
        echo '<input type="hidden" name="arc_last_user_id" value="'.(isset($_GET['arc_next'])?absint($_GET['arc_next']):0).'">';
        wp_nonce_field('arc_initialize_profiles');
        echo '<button class="button">Inicializovat další dávku (max. 100 účtů)</button></form>';
        echo '<h2>Uvedení do provozu</h2><ol><li>Záloha databáze a aktivace na stagingu.</li><li>Nastavit veřejné stránky s odpovídajícími shortcody.</li><li>Ověřit Můj účet → Moje komunita.</li><li>Test soukromého/veřejného profilu, seznamu a oblíbených.</li><li>Nezveřejňovat historické profily bez ověření souhlasu a vazby.</li></ol></div>';
    }
}
