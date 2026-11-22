<?php
defined('ABSPATH') || exit;
final class ARC_Shortcodes {
    public static function register(): void {
        add_shortcode('ar_community_account',[self::class,'account']);
        add_shortcode('ar_community_friends',[self::class,'friends_page']);
        add_shortcode('ar_live_notifications',[self::class,'notifications_page']);
        add_shortcode('ar_community_favorite_button',[self::class,'favorite_button']);
        add_shortcode('ar_community_favorite_products',[ARC_Saved::class,'favorite_products']);
        add_shortcode('ar_community_saved_posts',[ARC_Saved::class,'saved_posts']);
        add_shortcode('ar_community_saved',[ARC_Saved::class,'overview']);
        add_shortcode('ar_community_saved_icon',[ARC_Saved::class,'menu_icon']);
        add_shortcode('ar_community_save_article',[ARC_Saved::class,'article_button']);
        add_shortcode('ar_community_library',[self::class,'library']);
        add_shortcode('ar_community_people',[self::class,'people']);
        add_shortcode('ar_community_profile',[self::class,'profile']);
        add_shortcode('ar_community_advisor',[self::class,'advisor']);
        add_shortcode('ar_community_submit_article',[self::class,'article_form']);
        add_action('admin_post_arc_submit_article',[self::class,'submit_article']);
    }
    public static function assets_register(): void {
        wp_register_style('arc-community',ARC_URL.'assets/community.css',[],ARC_VERSION);
        wp_register_script('arc-community',ARC_URL.'assets/community.js',[],ARC_VERSION,true);
        wp_register_script('arc-account-extra',ARC_URL.'assets/account-extra.js',['arc-community'],ARC_VERSION,true);
        wp_register_script('arc-notifications',ARC_URL.'assets/notifications.js',['arc-community'],ARC_VERSION,true);
        wp_enqueue_style('arc-saved',ARC_URL.'assets/saved.css',[],ARC_VERSION);
        wp_register_script('arc-saved',ARC_URL.'assets/saved.js',['arc-community'],ARC_VERSION,true);
        wp_register_style('arc-social-pages',ARC_URL.'assets/social-pages.css',[],ARC_VERSION);
        wp_register_style('arc-kit-manager',ARC_URL.'assets/bundles.css',['arc-saved'],ARC_VERSION);
        wp_register_script('arc-kit-manager',ARC_URL.'assets/bundles.js',['arc-community'],ARC_VERSION,true);
        if(is_page('ulozene')){
            wp_enqueue_style('arc-community');
            wp_enqueue_style('arc-kit-manager');
            wp_enqueue_script('arc-kit-manager');
        }

        wp_register_script('arc-social-pages',ARC_URL.'assets/social-pages.js',['arc-community'],ARC_VERSION,true);
    }
    public static function assets(): void {
        wp_enqueue_style('arc-community');wp_enqueue_script('arc-community');
        wp_localize_script('arc-community','ARC',[
            'root'=>esc_url_raw(rest_url('ar-community/v1/')),'nonce'=>wp_create_nonce('wp_rest'),
            'loggedIn'=>is_user_logged_in(),'loginUrl'=>function_exists('wc_get_page_permalink')?wc_get_page_permalink('myaccount'):wp_login_url(),
            'cartUrl'=>function_exists('wc_get_cart_url')?wc_get_cart_url():'',
        ]);
    }
    private static function card_product(int $id, int $affiliate_owner=0): string {
        if(!ARC_Data::valid_product($id)) return '';
        $product=wc_get_product($id);
        $url=get_permalink($id);
        if($affiliate_owner) $url=ARC_Integrations::link($affiliate_owner,$url);
        return '<li class="arc-product"><a href="'.esc_url($url).'">'.esc_html(get_the_title($id)).'</a>'
            .'<span class="arc-price">'.wp_kses_post($product->get_price_html()).'</span></li>';
    }
    public static function account(): string {
        self::assets();
        wp_enqueue_script('arc-account-extra');
        wp_enqueue_script('arc-notifications');
        if(!is_user_logged_in()) return '<div class="arc-wrap"><p>Pro správu komunitního profilu se prosím přihlas nebo registruj.</p></div>';
        if(isset($_GET['arc_profile_preview'])) return self::profile();
        $uid=get_current_user_id();
        $preview=add_query_arg('arc_profile_preview','1',function_exists('wc_get_account_endpoint_url')?wc_get_account_endpoint_url('ar-community'):ARC_Data::member_url($uid));
        $public=ARC_Data::member_url($uid);
        $avatar=ARC_Profile::avatar_url($uid);
        $avatar_html=$avatar?'<img src="'.esc_url($avatar).'" width="88" height="88" alt="Náhled profilové fotografie">':'<span aria-hidden="true">＋</span>';
        $share=esc_url($public); 
        return '<div class="arc-wrap arc-account" data-arc-account>
          <h2>Moje komunita</h2>
          <p class="arc-account-intro">Tvůj prostor v '.esc_html(ARC_Config::name()).' – přátelé, příspěvky a oznámení na jednom místě.</p>
          '.self::notifications_markup().'
          <div class="arc-masonry">
            <div class="arc-account-column arc-account-main">
            <section class="arc-panel arc-profile-panel" id="arc-edit-profile"><h3>Můj profil</h3>
              <div class="arc-avatar-tools">
                <button type="button" class="arc-avatar-preview" data-arc-avatar-preview data-arc-photo-action="gallery" aria-label="Vybrat nebo vyfotit profilovou fotografii" title="Změnit profilovou fotografii">'.$avatar_html.'</button>
                <div data-arc-avatar-form>
                   <span class="arc-photo-caption">Vyfotit novou fotografii nebo vybrat z galerie.</span>
                   <div class="arc-avatar-actions"><button type="button" class="arc-btn arc-btn-subtle" data-arc-photo-action="camera">📷 Vyfotit</button><button type="button" class="arc-btn arc-btn-subtle" data-arc-photo-action="gallery">▧ Galerie</button></div>
                   <input id="arc-avatar-file" class="arc-avatar-file-input" type="file" name="avatar" accept="image/*" aria-label="Vybrat profilovou fotografii" required>
                   <p class="arc-small">Fotografii můžeš vybrat z galerie nebo pořídit fotoaparátem. Větší snímek se před odesláním zmenší.</p>
                   <label class="arc-photo-consent"><input type="checkbox" name="consent" required> Beru na vědomí, že nahraná fotografie má veřejně dostupnou adresu souboru, i když skryji profil.</label>
                   <p class="arc-small">Fotografie se uloží společně s údaji tlačítkem <strong>Uložit profil</strong>.</p>
                   <p class="arc-small" data-arc-avatar-message role="status"></p>
                </div>
              </div>
              <form data-arc-profile>
                <label>Zobrazované jméno (komunita a WordPress)<input name="alias" minlength="2" maxlength="65" required placeholder="Jméno viditelné u příspěvků, přátel a oznámení"></label><p class="arc-small">Změna se propíše také do zobrazovaného jména a přezdívky WordPress účtu. Přihlašovací jméno a e-mail se nemění.</p>
                <label for="arc-bio-editor">O mně</label><div class="arc-bio-toolbar"><button type="button" data-arc-rich="bold" aria-label="Tučný text"><strong>B</strong></button><button type="button" data-arc-rich="link" aria-label="Vložit odkaz">🔗 Odkaz</button></div><div id="arc-bio-editor" class="arc-bio-editor" contenteditable="true" role="textbox" aria-multiline="true" aria-label="O mně – tučné písmo a odkazy" data-arc-bio-editor></div><textarea name="bio" rows="3" class="arc-bio-fallback" aria-label="O mně" hidden></textarea>
                <label>Zájmy<input name="interests" maxlength="250" placeholder="Moje zájmy…"></label>
                '.(ARC_Config::enabled('sport_fields')?'<label>Můj vztah ke sportu<input name="sport_relation" maxlength="120" placeholder="Rekreační sportovec, nadšený turista…"></label>
                <fieldset class="arc-sport-professional"><legend>Sportovní profesní profil (nepovinné)</legend>
                  <label>Moje sportovní profese a služby<input name="sport_profession" maxlength="160" placeholder="Fyzioterapeut, masér, fitness trenér, průvodce…"></label>
                  <label>Sportovní znalosti a profesní zkušenosti<textarea name="sport_experience" rows="3" maxlength="900" placeholder="Specializace, praxe, certifikace, co mohu nabídnout ostatním…"></textarea></label>
                  <p class="arc-small">Údaje se zobrazují jen návštěvníkům, kterým je přístupný tvůj profil. Budou podkladem pro budoucí vyhledávání odborníků.</p>
                </fieldset>':'').'
                <label>Viditelnost profilu<select name="visibility"><option value="private">Neveřejný – pouze já</option><option value="network">Jen potvrzení přátelé</option><option value="public">Veřejný – všichni</option></select></label>
                <label class="arc-optin-label"><input type="checkbox" name="email_notifications" value="1"> Chci dostávat oznámení z komunity také e-mailem (výchozí nastavení: zapnuto).</label>
                <label class="arc-optin-label"><input type="checkbox" name="marketing_optin" value="1"> Chci dostávat dobrovolná obchodní oznámení od provozovatele komunity. Souhlas mohu kdykoli odvolat.</label>
                <p class="arc-small">Nastavení profilu neurčuje viditelnost jednotlivých příspěvků. Fakturační údaje a affiliate vazby se nezveřejňují.</p>
                <div class="arc-inline"><button type="submit" class="arc-btn">Uložit profil</button><a class="arc-btn arc-btn-subtle arc-account-link" href="'.esc_url($preview).'">Náhled profilu</a></div>
                <p class="arc-inline-status" data-arc-profile-saved role="status" aria-live="polite"></p>
                <div class="arc-profile-share"><label for="arc-share-url">Odkaz na můj komunitní profil</label><div class="arc-share-row"><input id="arc-share-url" type="url" value="'.esc_attr($public).'" readonly><button type="button" class="arc-btn arc-btn-subtle" data-arc-copy-profile>Kopírovat</button></div><small>Odkaz si můžeš prohlédnout nebo sdílet. Pokud je profil neveřejný, ostatní jeho obsah neuvidí.</small><a href="'.esc_url($public).'" target="_blank" rel="noopener">Zobrazit svůj profil ↗</a></div>
              </form>
            </section>
            '.(ARC_Config::enabled('show_premium')?'<section class="arc-panel arc-premium-panel"><div class="arc-premium-eyebrow">PŘIPRAVUJEME</div><h3>'.esc_html(ARC_Config::name()).' <span>Premium</span></h3>
              <p>Více zážitků a výhod na jednom místě. Připravujeme nadstandardní aplikace, služby a speciální nabídky.</p>
              <p class="arc-small">Jídelníček, mapy tras a servisů, sportovní benefity a další funkce postupně. Prémiové nástroje budou zpřístupněny po jejich dokončení.</p>
              <a class="arc-premium-pill" href="'.esc_url(home_url('/premium/')).'">Otevřít Premium →</a>
            </section>':'').'
            </div>
            <div class="arc-account-column arc-account-side">
            <section class="arc-panel arc-friends-panel"><div class="arc-friends-panel-heading"><h3>Přátelé</h3><a class="arc-account-link" href="'.esc_url(ARC_Navigation::app_link('pratele')).'">Spravovat přátele →</a></div>
              '.self::friends_markup().'
            </section>
            '.(ARC_Config::enabled('show_shop') && function_exists('wc_get_products')?'<section class="arc-panel arc-products-panel"><div class="arc-saved-heading"><h3>Oblíbené produkty</h3><a class="arc-saved-all-link" href="'.esc_url(ARC_Saved::url('products')).'">Spravovat uložené →</a></div>
              <p class="arc-small">Oblíbené produkty a balíčky nyní spravuješ na stránce Uložené.</p>
              '.ARC_Saved::favorite_products(['limit'=>4,'compact'=>1]).'
            </section>':'').'
            <section class="arc-panel arc-saved-posts-panel" id="arc-saved-posts"><div class="arc-saved-heading"><h3>Uložené příspěvky</h3><a class="arc-saved-all-link" href="'.esc_url(ARC_Saved::url('posts')).'">Zobrazit všechny →</a></div>
              <p class="arc-small">Příspěvky a články, které si chceš přečíst nebo prohlédnout později.</p>
              '.ARC_Saved::saved_posts_markup(5,false).'
            </section>
            </div>
          </div>
          <p class="arc-status" data-arc-status role="status" aria-live="polite"></p>
        </div>';
    }
    /** Standalone /pratele/ – uses the same REST permissions, invitations and friends as My Account. */
    /** Responsive community friends. Account dashboard keeps its legacy compact tools. */
    public static function friends_page(): string {
        self::assets();wp_enqueue_style('arc-social-pages');wp_enqueue_script('arc-social-pages');
        if(!is_user_logged_in())return '<div class="arc-social-shell"><p>Pro přístup k přátelům se přihlas.</p><a href="'.esc_url(wp_login_url(get_permalink())).'">Přihlásit se</a></div>';
        $linkBanner='';
        $linkToken=isset($_GET['arc_invite']) && is_scalar($_GET['arc_invite'])?sanitize_text_field(wp_unslash($_GET['arc_invite'])):'';
        $inviter=$linkToken?ARC_Invites::inviter_for_token($linkToken):0;
        if($inviter && $inviter!==get_current_user_id())
            $linkBanner='<div class="arc-invite-link-banner" data-arc-invite-link-banner><strong>Pozvánka od '.esc_html(ARC_Profile::display_alias($inviter)).'</strong><p>Potvrzením se stanete přáteli v komunitě. Referenční přiřazení existujících účtů zůstává nedotčené.</p><button type="button" class="arc-social-btn arc-social-primary" data-arc-accept-invite="'.esc_attr($linkToken).'">Přijmout pozvánku</button></div>';
        return '<div class="arc-social-shell arc-social-friends" data-arc-social="friends" data-arc-loading="true">'.$linkBanner.'
          <header class="arc-social-heading"><h2>Přátelé</h2><button type="button" class="arc-social-btn arc-social-primary" data-arc-view="invite">Pozvat přátele</button></header>
          <nav class="arc-social-tabs" aria-label="Sekce přátel"><button type="button" class="is-active" data-arc-view="list">Moji přátelé</button><button type="button" data-arc-view="invite">Pozvat přátele</button><button type="button" data-arc-view="requests">Nové žádosti <span data-arc-requests-count></span></button></nav>
          <section data-arc-panel="list"><label class="arc-social-search"><span class="screen-reader-text">Filtrovat přátele</span><input data-arc-friends-filter type="search" placeholder="Hledat mezi mými přáteli…" autocomplete="off"></label><div data-arc-social-friends class="arc-social-people-grid"><p>Načítám přátele…</p></div></section>
          <section data-arc-panel="requests" hidden><h3>Nové žádosti o přátelství</h3><div data-arc-social-requests class="arc-social-people-grid"></div></section>
          <section data-arc-panel="invite" hidden>
            <nav class="arc-invite-modes" aria-label="Způsob pozvání">
              <button type="button" class="is-active" data-arc-invite-mode="community">Najít v komunitě</button>
              <button type="button" data-arc-invite-mode="outside">Pozvat do komunity</button>
            </nav>
            <div data-arc-invite-section="community">
              <p class="arc-social-help">Vyhledej člena podle zobrazovaného jména nebo e-mailu. Výsledky se zobrazí po zadání alespoň jednoho znaku. Soukromé profily nenabízíme.</p>
              <label class="arc-social-search"><span class="screen-reader-text">Najít v komunitě</span><input data-arc-search-input type="search" placeholder="Najít v komunitě…" autocomplete="off" aria-controls="arc-community-suggestions" aria-autocomplete="list"></label>
              <div id="arc-community-suggestions" class="arc-community-suggestions" role="listbox" data-arc-suggestions aria-live="polite"></div>
              <div class="arc-selected-member" data-arc-selected-member aria-live="polite"></div>
            </div>
            <div data-arc-invite-section="outside" hidden>
              <p class="arc-social-help">Odešli pozvánku e-mailem, přes WhatsApp nebo sdíleným odkazem. Pokud máš vlastní referenční program, využijeme existující Fresh Affiliate.</p>
              <div class="arc-social-invite-row"><button type="button" class="arc-social-btn" data-arc-invite-copy>Kopírovat odkaz</button><button type="button" class="arc-social-btn" data-arc-invite-whatsapp>WhatsApp</button><button type="button" class="arc-social-btn" data-arc-invite-email>E-mailová aplikace</button></div>
              <form data-arc-invite-email-form class="arc-social-invite-form"><label>Poslat pozvánku e-mailem<input type="email" name="email" autocomplete="email" placeholder="e-mail@priklad.cz" required></label><button type="submit" class="arc-social-btn arc-social-primary">Odeslat pozvánku</button></form>
            </div>
            <div class="arc-sent-list"><h3>Odeslané žádosti <small>Posledních 30 dní</small></h3>
              <div data-arc-social-sent class="arc-social-sent-rows" aria-live="polite"><p class="arc-social-empty">Načítám odeslané žádosti…</p></div>
            </div>
          </section><p data-arc-social-status class="arc-social-status" role="status" aria-live="polite"></p></div>';
    }
    public static function notifications_page(): string {
        self::assets();wp_enqueue_style('arc-social-pages');wp_enqueue_script('arc-social-pages');
        if(!is_user_logged_in())return '<div class="arc-social-shell"><p>Pro zobrazení oznámení se přihlas.</p><a href="'.esc_url(wp_login_url(get_permalink())).'">Přihlásit se</a></div>';
        return '<div class="arc-social-shell arc-social-notifications" data-arc-social="notifications" data-arc-loading="true"><header class="arc-social-heading arc-social-heading-actions"><button type="button" class="arc-social-btn" data-arc-notify-readall>Označit vše přečtené</button></header><nav class="arc-social-tabs" aria-label="Filtr oznámení"><button type="button" class="is-active" data-arc-notify-filter="all">Všechna</button><button type="button" data-arc-notify-filter="unread">Nepřečtená</button></nav><div class="arc-social-notice-list" data-arc-social-notices aria-live="polite">Načítám oznámení…</div><p data-arc-social-status class="arc-social-status" role="status" aria-live="polite"></p></div>';
    }
    private static function notifications_markup(): string {
        $url=esc_url(ARC_Navigation::app_link('oznameni'));
        return '<section class="arc-notifications arc-panel arc-notifications-compact" data-arc-notifications aria-label="Upozornění z komunity"><div class="arc-notifications-head"><h3>Upozornění <span data-arc-notification-count class="arc-notification-badge" hidden></span></h3></div><p class="arc-notifications-brief" data-arc-notifications-list role="status" aria-live="polite">Načítám upozornění…</p><a class="arc-account-link" href="'.$url.'">Přejít na oznámení →</a></section>';
    }
    private static function friends_markup(): string {
        return '<div class="arc-account-friends-simple">
          <form data-arc-friend-search class="arc-friend-search"><label>Vyhledat člena v síti<input name="query" type="search" minlength="2" placeholder="Zobrazované jméno nebo celý e-mail…" autocomplete="off" required></label><button type="submit" class="arc-btn arc-btn-subtle">Hledat</button><div data-arc-friend-results aria-live="polite"></div></form>
          <h4>Moji přátelé <small>10 posledních registrovaných</small></h4>
          <div data-arc-friends aria-live="polite"><p class="arc-small">Načítám přátele…</p></div>
          <p data-arc-friends-status class="arc-inline-status" role="status" aria-live="polite"></p>
        </div>';
    }
    public static function favorite_button($atts): string {
        self::assets();
        $atts=shortcode_atts(['product_id'=>0,'label'=>'0'],(array)$atts,'ar_community_favorite_button');
        $id=absint($atts['product_id']);
        // Elementor WooCommerce Single Product/Products Loop: ID is resolved automatically.
        if(!$id && is_singular('product'))$id=(int)get_queried_object_id();
        if(!$id && function_exists('wc_get_product')){
            global $product;
            if($product instanceof WC_Product)$id=(int)$product->get_id();
        }
        if(!$id)$id=(int)get_the_ID();
        if(function_exists('wc_get_product') && $id && get_post_type($id)==='product_variation'){
            $v=wc_get_product($id);
            if($v instanceof WC_Product_Variation)$id=$v->get_parent_id();
        }
        if(!ARC_Data::valid_product($id)) return '';
        $labeled=(string)$atts['label']==='1';
        return '<button type="button" class="arc-fav arc-fav-icon" data-arc-favorite="'.esc_attr((string)$id).'" data-arc-favorite-label="'.($labeled?'1':'0').'" aria-pressed="false" aria-label="Přidat do oblíbených" title="Přidat do oblíbených">'
            .'<span aria-hidden="true">♡</span>'.($labeled?'<span class="arc-fav-word"> Uložit</span>':'').'</button>';
    }
    public static function library(): string {
        self::assets();
        $selected=isset($_GET['ar_kit'])?absint(wp_unslash($_GET['ar_kit'])):0;
        if($selected) {
            $p=get_post($selected);
            if(!$p || $p->post_type!==ARC_Data::TYPE || !ARC_Data::can_view($p,get_current_user_id()))
                return '<div class="arc-wrap"><p>Balíček není dostupný.</p></div>';
            $uid=(int)$p->post_author;
            $author=get_userdata($uid);
            $items=ARC_Data::items($p->ID);
            $html='<div class="arc-wrap"><article class="arc-panel"><h2>'.esc_html($p->post_title).'</h2>';
            $html.='<p>Výbavu doporučuje <a href="'.esc_url(ARC_Data::member_url($uid)).'">'.esc_html(ARC_Profile::display_alias($uid)).'</a></p>';
            if(ARC_Integrations::affiliate_active($uid)) $html.='<p class="arc-small">Některé odkazy jsou partnerské. Autor může získat provizi z nákupu.</p>';
            $html.='<ul class="arc-products">';
            foreach($items as $item) {
                $html.=self::card_product($item['product_id'],$uid);
                if($item['owned']) $html.='<li class="arc-small">Autor tuto položku již vlastní.</li>';
                if($item['note']) $html.='<li class="arc-small">'.esc_html($item['note']).'</li>';
            }
            $html.='</ul><p class="arc-small">Aktuální cenu a skladovost ověř na kartě produktu.</p></article></div>';
            return $html;
        }
        $page=isset($_GET['arc_page'])?max(1,absint($_GET['arc_page'])):1;
        $posts=get_posts(['post_type'=>ARC_Data::TYPE,'post_status'=>'publish','posts_per_page'=>24,'paged'=>$page,'orderby'=>'date','order'=>'DESC']);
        $html='<div class="arc-wrap"><h2>Výbava na cesty – doporučují naši členové</h2><div class="arc-grid">';
        $count=0;
        foreach($posts as $p) {
            if(!ARC_Data::can_view($p,0)) continue;
            $count++;$user=get_userdata((int)$p->post_author);
            $html.='<article class="arc-panel"><h3><a href="'.esc_url(ARC_Data::public_url((int)$p->ID)).'">'.esc_html($p->post_title).'</a></h3>';
            $html.='<p>Autor: '.esc_html(ARC_Profile::display_alias((int)$p->post_author)).'</p>';
            $html.='<p>'.count(ARC_Data::items($p->ID)).' produktů</p></article>';
        }
        if(!$count) $html.='<p>Zatím tu nejsou žádné veřejné balíčky.</p>';
        return $html.'</div></div>';
    }
    public static function people(): string {
        if(isset($_GET['ar_member'])) return self::profile();
        self::assets();
        $users=get_users(['meta_key'=>'_arc_profile_visibility','meta_value'=>'public','number'=>32,'orderby'=>'registered','order'=>'DESC']);
        $html='<div class="arc-wrap"><h2>Lidé a profily</h2><div class="arc-grid">';
        foreach($users as $u) {
            $html.='<article class="arc-panel"><h3><a href="'.esc_url(ARC_Data::member_url((int)$u->ID)).'">'.esc_html(ARC_Profile::display_alias((int)$u->ID)).'</a></h3>';
            $html.='<p>'.esc_html((string)get_user_meta($u->ID,ARC_Data::META_INTERESTS,true)).'</p></article>';
        }
        if(!$users) $html.='<p>První veřejné profily teprve vznikají.</p>';
        return $html.'</div></div>';
    }
    /** One Elementor template + query arg makes a unique shareable URL for each member. */
    public static function profile(): string {
        self::assets();
        $id=isset($_GET['ar_member'])?absint(wp_unslash($_GET['ar_member'])):get_current_user_id();
        if(!$id)return '<div class="arc-wrap"><p>Profil vyžaduje přihlášení nebo odkaz na člena.</p></div>';
        $viewer=get_current_user_id();$owner=$id===$viewer;
        if(!ARC_Profile::can_view($id,$viewer))return '<div class="arc-wrap"><p>Profil není dostupný.</p></div>';
        $user=get_userdata($id);if(!$user)return '<p>Profil nenalezen.</p>';
        $avatar=ARC_Profile::avatar_url($id,'medium');
        $avatarHtml=$avatar?'<img class="arc-profile-avatar" src="'.esc_url($avatar).'" alt="Fotografie profilu" width="132" height="132">':'<span class="arc-profile-avatar arc-profile-avatar-blank" aria-hidden="true">'.esc_html(mb_substr(ARC_Profile::display_alias($id),0,1)).'</span>';
        $bio=ARC_Profile::bio_html((string)get_user_meta($id,ARC_Data::META_BIO,true));
        $scopeText=['private'=>'Soukromý – pouze já','network'=>'Jen potvrzení přátelé','public'=>'Veřejný – všichni'][ARC_Profile::visibility($id)]??'Soukromý';
        $html='<div class="arc-wrap arc-public-profile"><header class="arc-public-hero">'.$avatarHtml.'<div class="arc-public-hero-text"><h1>'.esc_html(ARC_Profile::display_alias($id)).'</h1><p class="arc-public-visibility">Viditelnost profilu: '.esc_html($scopeText).'</p><div class="arc-public-bio">'.($bio!==''?wpautop($bio):'<p>Člen komunity Adrenaline Race</p>').'</div>';
        $interests=sanitize_text_field((string)get_user_meta($id,ARC_Data::META_INTERESTS,true));
        if($interests!=='') {
            $html.='<div class="arc-profile-interests"><strong>Oblíbené sporty</strong><div class="arc-interest-tags">';
            foreach(array_filter(array_map('trim',explode(',',$interests))) as $interest)$html.='<span>'.esc_html($interest).'</span>';
            $html.='</div></div>';
        }
        $profession=sanitize_text_field((string)get_user_meta($id,'_arc_sport_profession',true));
        $experience=sanitize_textarea_field((string)get_user_meta($id,'_arc_sport_experience',true));
        if($profession!=='' || $experience!=='') {
            $html.='<section class="arc-profile-professional"><h2>Sportovní profese a zkušenosti</h2>';
            if($profession!=='') $html.='<strong>'.esc_html($profession).'</strong>';
            if($experience!=='') $html.='<div>'.wpautop(esc_html($experience)).'</div>';
            $html.='</section>';
        }
        if($owner)$html.='<p><a class="arc-account-link" href="'.esc_url(function_exists('wc_get_account_endpoint_url')?wc_get_account_endpoint_url('ar-community'):home_url('/muj-ucet/')).'">Upravit můj profil</a></p>';
        if($owner){
        $html.='</div><aside class="arc-profile-social"><h2>Přátelé</h2><p class="arc-profile-vis"><strong>Viditelnost profilu:</strong> '.esc_html(['private'=>'Soukromý – pouze já','network'=>'Jen potvrzení přátelé','public'=>'Veřejný – všichni'][ARC_Profile::visibility($id)]??'Soukromý').'</p>';
        $allFriends=ARC_Relations::connected_ids($id);
        $viewerFriends=$viewer?ARC_Relations::connected_ids($viewer):[];
        $visibleFriends=array_values(array_filter($allFriends,static function($friend)use($viewer,$owner){
            if(!get_userdata($friend))return false;
            // A visitor sees only friends whose own profiles they are allowed to open.
            return ARC_Profile::can_view((int)$friend,$viewer);
        }));
        $mutual=$viewer && !$owner?array_values(array_intersect($allFriends,$viewerFriends)):[];
        // Never expose hidden social members or private relationships in somebody else's profile.
        $mutual=array_values(array_filter($mutual,static fn($friend)=>ARC_Profile::can_view((int)$friend,$viewer)));
        if($viewer && !$owner && $mutual) { $html.='<h3>Společní přátelé</h3><div class="arc-friend-chips">'; foreach(array_slice($mutual,0,8) as $fid)$html.=self::friend_chip((int)$fid);$html.='</div>'; }
        $html.='<h3>Přátelé, které můžeš zobrazit</h3><div class="arc-friend-chips">';
        foreach(array_slice($visibleFriends,0,9) as $fid)$html.=self::friend_chip((int)$fid);
        if(!$visibleFriends)$html.='<p class="arc-small">Zatím nejsou k dispozici žádní přátelé s profilem viditelným pro tebe.</p>';
        $html.='</div><a class="arc-account-link" href="'.esc_url(ARC_Navigation::app_link('pratele')).'">Spravovat přátele →</a></aside>';
        }else $html.='</div>';
        $html.='</header><div class="arc-profile-tiles">';
        $wallPosts=get_posts(['post_type'=>ARC_Wall::TYPE,'post_status'=>'private','author'=>$id,'posts_per_page'=>12,'meta_query'=>[['key'=>'_arc_audience','value'=>'public']]]);
        $html.='<section class="arc-profile-section"><h2>Příspěvky</h2><div class="arc-profile-grid">';
        foreach($wallPosts as $post){
            if(!ARC_Wall::can_read($post,$viewer))continue;
            $imgId=absint(get_post_meta($post->ID,'_arc_image_id',true));
            $image=$imgId?ARC_Wall::photo($imgId,$id):'';
            $video=esc_url_raw((string)get_post_meta($post->ID,'_arc_video',true));
            $html.='<article class="arc-profile-tile">'.($image?'<img src="'.esc_url($image).'" alt="Fotografie příspěvku" loading="lazy">':'').'<div class="arc-profile-tile-content"><span class="arc-tile-type">PŘÍSPĚVEK</span><p>'.esc_html(wp_trim_words(wp_strip_all_tags($post->post_content),35)).'</p>';
            if($video && ARC_Wall::allowed_video($video))$html.='<a href="'.esc_url($video).'" target="_blank" rel="noopener noreferrer">Přehrát video ↗</a>';
            $html.='<time datetime="'.esc_attr(get_post_time('c',true,$post)).'">'.esc_html(get_the_date('j. n. Y',$post)).'</time></div></article>';
        }
        // Re-shares addressed to this member's wall. Server-side ACL is applied per viewer.
        $incoming=get_posts(['post_type'=>ARC_Wall::TYPE,'post_status'=>'private','posts_per_page'=>12,
            'meta_key'=>'_arc_wall_target_id','meta_value'=>$id,'orderby'=>'date','order'=>'DESC']);
        foreach($incoming as $shared){
            if(!ARC_Wall::can_read($shared,$viewer))continue;
            $data=ARC_Wall::post_payload($shared,$viewer);$original=$data['shared']??null;
            if(!$original)continue;
            $html.='<article class="arc-profile-tile arc-profile-reshare"><div class="arc-profile-tile-content">';
            $html.='<span class="arc-tile-type">PŘEPOSLÁNO NA ZEĎ</span>';
            $html.='<p><strong>'.esc_html($data['author_alias']).'</strong> přeposlal/a obsah od <strong>'.esc_html($original['author_alias']).'</strong></p>';
            if($data['body'])$html.='<p>'.esc_html(wp_trim_words($data['body'],22)).'</p>';
            if($original['type']==='article'){
                $html.='<h3><a href="'.esc_url($original['url']).'">'.esc_html($original['title']).'</a></h3>';
                if(!empty($original['image']))$html.='<img loading="lazy" src="'.esc_url($original['image']).'" alt="">';
            } else {
                $html.='<p>'.esc_html(wp_trim_words($original['body'],32)).'</p>';
                if(!empty($original['image']))$html.='<img loading="lazy" src="'.esc_url($original['image']).'" alt="">';
            }
            $html.='</div></article>';
        }
        if(!$wallPosts && !$incoming)$html.='<p class="arc-small">Zatím žádné veřejné příspěvky.</p>';
        $html.='</div></section>';
        $articles=get_posts(['post_type'=>'post','post_status'=>'publish','author'=>$id,'posts_per_page'=>30]);
        $verified=get_posts(['post_type'=>'post','post_status'=>'publish','posts_per_page'=>30,'meta_key'=>'_arc_verified_author_user_id','meta_value'=>(string)$id]);
        $articleIds=[];
        foreach(array_merge($verified,$articles) as $post){
            $assigned=absint(get_post_meta($post->ID,'_arc_verified_author_user_id',true));
            if($assigned && $assigned!==$id)continue;
            $articleIds[$post->ID]=$post;
        }
        $html.='<section class="arc-profile-section"><h2>Články v magazínu</h2><div class="arc-profile-grid">';
        foreach(array_slice(array_values($articleIds),0,12) as $post){
            $thumbnail=get_the_post_thumbnail_url($post->ID,'medium_large')?:'';
            $html.='<article class="arc-profile-tile">';
            if($thumbnail)$html.='<a href="'.esc_url(get_permalink($post)).'"><img loading="lazy" src="'.esc_url($thumbnail).'" alt=""></a>';
            $html.='<div class="arc-profile-tile-content"><span class="arc-tile-type">MAGAZÍN</span><h3><a href="'.esc_url(get_permalink($post)).'">'.esc_html(get_the_title($post)).'</a></h3><p>'.esc_html(wp_trim_words(wp_strip_all_tags(get_the_excerpt($post)),20)).'</p></div></article>';
        }
        if(!$articleIds)$html.='<p class="arc-small">Zatím žádné publikované články.</p>';
        $html.='</div></section>';
        $collections=[];
        foreach(ARC_Data::my_collections($id) as $c)if($c->post_status==='publish'&&get_post_meta($c->ID,ARC_Data::META_KIND,true)==='kit'&&ARC_Data::public_profile($id))$collections[]=$c;
        if($collections){
          $html.='<section class="arc-profile-section"><h2>Doporučená výbava</h2><div class="arc-profile-grid">';
          foreach(array_slice($collections,0,12) as $c)$html.='<article class="arc-profile-tile"><div class="arc-profile-tile-content"><span class="arc-tile-type">VÝBAVA</span><h3><a href="'.esc_url(ARC_Data::public_url($c->ID)).'">'.esc_html($c->post_title).'</a></h3></div></article>';
          $html.='</div></section>';
        }
        $html.='</div></div>';
        return $html;
    }
    private static function friend_chip(int $id): string {
        $url=ARC_Profile::avatar_url($id);
        $avatar=$url?'<img src="'.esc_url($url).'" alt="" loading="lazy">':'<span aria-hidden="true">'.esc_html(mb_substr(ARC_Profile::display_alias($id),0,1)).'</span>';
        return '<a class="arc-friend-chip" href="'.esc_url(ARC_Data::member_url($id)).'">'.$avatar.'<span>'.esc_html(ARC_Profile::display_alias($id)).'</span></a>';
    }
    public static function advisor(): string {
        self::assets();
        // The provider is deliberately not enabled before verification of costs and product retrieval.
        return '<section class="arc-wrap arc-panel"><h2>Osobní poradce na cesty</h2><p>Kam se chystáš a co už máš připravené?</p><p class="arc-small">Chatová část se připravuje. Seznam výbavy si už nyní můžeš vytvořit v Můj účet → Moje komunita.</p></section>';
    }
    public static function article_form(): string {
        self::assets();
        if(!is_user_logged_in()) return '<p>Pro přidání článku se prosím přihlas.</p>';
        $ok=isset($_GET['arc_article']) && $_GET['arc_article']==='submitted';
        return '<div class="arc-wrap arc-panel"><h2>Napsat článek do magazínu</h2>'.($ok?'<p>Článek je odeslán ke schválení.</p>':'')
        .'<form method="post" action="'.esc_url(admin_url('admin-post.php')).'">'
        .'<input type="hidden" name="action" value="arc_submit_article">'.wp_nonce_field('arc_submit_article','arc_nonce',true,false)
        .'<label>Nadpis <input type="text" name="title" maxlength="180" required></label>'
        .'<label>Článek <textarea name="content" rows="9" minlength="80" maxlength="12000" required></textarea></label>'
        .'<button class="arc-btn" type="submit">Odeslat redakci ke schválení</button></form></div>';
    }
    public static function submit_article(): void {
        if(!is_user_logged_in()) wp_die('Přihlášení je vyžadováno.','',['response'=>403]);
        check_admin_referer('arc_submit_article','arc_nonce');
        $uid=get_current_user_id();
        if(get_user_meta($uid,'_arc_community_suspended',true))wp_die('Komunitní publikování je pozastaveno.','',['response'=>403]);
        $count=(int)get_transient('arc_article_daily_'.$uid);
        if($count>=3) wp_die('Limit tří příspěvků za den.','',['response'=>429]);
        $title=mb_substr(sanitize_text_field((string)wp_unslash($_POST['title']??'')),0,180);
        $content=wp_kses_post((string)wp_unslash($_POST['content']??''));
        if(mb_strlen(trim($title))<5 || mb_strlen(trim(wp_strip_all_tags($content)))<80 || mb_strlen($content)>12000) wp_die('Neplatný titulek nebo text.','',['response'=>400]);
        $id=wp_insert_post(['post_type'=>'post','post_status'=>'pending','post_title'=>$title,
            'post_content'=>$content,'post_author'=>$uid],true);
        if(is_wp_error($id)) wp_die('Článek se nepodařilo uložit.','',['response'=>500]);
        update_post_meta($id,'_arc_community_submitted',1);
        set_transient('arc_article_daily_'.$uid,$count+1,DAY_IN_SECONDS);
        $redirect=wp_get_referer()?:home_url('/');
        wp_safe_redirect(add_query_arg('arc_article','submitted',$redirect));exit;
    }
}
