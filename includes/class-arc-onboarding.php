<?php
defined('ABSPATH') || exit;
/** Site-customizable welcome that does not interfere with Woo account or affiliate attribution. */
final class ARC_Onboarding {
    public static function boot(): void {
        add_action('admin_menu',[self::class,'menu'],29);
        add_action('admin_post_arc_save_welcome',[self::class,'save']);
        add_action('wp_ajax_arc_welcome_done',[self::class,'done']);
        add_action('wp_ajax_arc_welcome_step',[self::class,'step']);
    }
    public static function menu(): void {
        add_submenu_page('ar-community','Uvítání členů','Uvítání členů','manage_options','arc-welcome',[self::class,'admin']);
    }
    public static function admin(): void {
        if(!current_user_can('manage_options'))return;
        $heading=(string)get_option('arc_welcome_heading','Nejsi na to sám.');
        $intro=(string)get_option('arc_welcome_intro',ARC_Config::description());
        echo '<div class="wrap"><h1>Fresh Community – uvítání nových členů</h1><p>Text se ukazuje na nástěnce Můj účet. Odkazy a jednotlivé kroky průvodce fungují automaticky.</p>';
        if(isset($_GET['saved']))echo '<div class="notice notice-success"><p>Uvítání bylo uloženo.</p></div>';
        echo '<form method="post" action="'.esc_url(admin_url('admin-post.php')).'"><input type="hidden" name="action" value="arc_save_welcome">';
        wp_nonce_field('arc_save_welcome');
        echo '<table class="form-table"><tr><th>Nadpis</th><td><input class="large-text" name="heading" maxlength="140" value="'.esc_attr($heading).'"></td></tr><tr><th>Představení komunity</th><td><textarea name="intro" class="large-text" rows="6" maxlength="1800">'.esc_textarea($intro).'</textarea></td></tr></table>';
        submit_button('Uložit uvítání');echo '</form></div>';
    }
    public static function save(): void {
        if(!current_user_can('manage_options'))wp_die('Nemáš oprávnění.',403);
        check_admin_referer('arc_save_welcome');
        update_option('arc_welcome_heading',mb_substr(sanitize_text_field((string)wp_unslash($_POST['heading']??'')),0,140),false);
        update_option('arc_welcome_intro',mb_substr(sanitize_textarea_field((string)wp_unslash($_POST['intro']??'')),0,1800),false);
        wp_safe_redirect(admin_url('admin.php?page=arc-welcome&saved=1'));exit;
    }
    public static function done(): void {
        if(!is_user_logged_in())wp_send_json_error(['message'=>'Přihlas se.'],401);
        check_ajax_referer('arc_welcome_done','nonce');
        update_user_meta(get_current_user_id(),'_arc_welcome_done','1');
        wp_send_json_success(['message'=>'Průvodce je dokončen. Kdykoli si průvodce můžeš otevřít na nástěnce Můj účet.']);
    }
    public static function step(): void {
        if(!is_user_logged_in())wp_send_json_error(['message'=>'Přihlas se.'],401);
        check_ajax_referer('arc_welcome_done','nonce');
        $step=isset($_POST['step'])?absint($_POST['step']):0;
        if($step>3)wp_send_json_error(['message'=>'Neplatný krok průvodce.'],400);
        // Reopening the guide is allowed even when already marked complete.
        update_user_meta(get_current_user_id(),'_arc_welcome_step',$step);
        wp_send_json_success(['step'=>$step]);
    }

    /** WooCommerce dashboard: always-present signpost plus once-only guided introduction. */
    public static function dashboard(int $uid): string {
        if(!$uid)return '';
        $done=(bool)get_user_meta($uid,'_arc_welcome_done',true);
        $reopen=isset($_GET['arc_guide']) && $_GET['arc_guide']==='1';
        $GLOBALS['arc_welcome_reopen']=$reopen;
        $welcome=(!$done || $reopen)?self::panel($uid):'';
        unset($GLOBALS['arc_welcome_reopen']);
        $base=function_exists('wc_get_page_permalink') ? wc_get_page_permalink('myaccount') : home_url('/muj-ucet/');
        $profile=class_exists('AR_Community')?AR_Community::community_account_url():$base;
        $friends=ARC_Navigation::app_link('pratele');
        $affiliate=function_exists('wc_get_account_endpoint_url')?wc_get_account_endpoint_url('fresh-affiliate'):$base;
        $orders=function_exists('wc_get_account_endpoint_url')?wc_get_account_endpoint_url('orders'):$base;
        $cards=[
          ['Moje komunita','Profil, nastavení soukromí, přátelé a oznámení.',$profile,'👤'],
          ['Přátelé','Najdi podobně naladěné lidi a pozvi své známé.',$friends,'👥'],
          ['Komunitní zeď','Příběhy, fotografie a komentáře.',ARC_Config::app_url(),'💬'],
        ];
        if(ARC_Config::enabled('show_affiliate') && function_exists('wc_get_account_endpoint_url'))$cards[]=['Referenční program','Doporučení a odměny podle pravidel programu.',$affiliate,'🤝'];
        if(function_exists('wc_get_account_endpoint_url'))$cards[]=['Moje objednávky','Stav objednávek a historie nákupů.',$orders,'🛍'];
        if(ARC_Config::enabled('show_premium'))$cards[]=['Premium','Další možnosti pro členy.',home_url('/premium/'),'★'];
        $html='<div class="arc-wrap arc-dashboard" data-arc-dashboard>';
        $html.=$welcome;
        $html.='<section class="arc-panel arc-dashboard-directory"><h2>Vítej ve svém účtu</h2><p>'.esc_html(ARC_Config::description()).'</p>';
        $html.='<div class="arc-dashboard-grid">';
        foreach($cards as $c){$html.='<a class="arc-dashboard-card" href="'.esc_url($c[2]).'"><span class="arc-dashboard-emoji" aria-hidden="true">'.esc_html($c[3]).'</span><strong>'.esc_html($c[0]).'</strong><small>'.esc_html($c[1]).'</small><span class="arc-dashboard-arrow" aria-hidden="true">→</span></a>';}
        $html.='</div><p class="arc-dashboard-guide-link"><a href="'.esc_url(add_query_arg('arc_guide','1',$base)).'">Znovu otevřít průvodce komunitou →</a></p></section></div>';
        wp_enqueue_style('arc-onboarding',ARC_URL.'assets/onboarding.css',[],ARC_VERSION);
        return $html;
    }
    public static function panel(int $uid): string {
        $heading=(string)get_option('arc_welcome_heading','Nejsi na to sám.');
        $intro=(string)get_option('arc_welcome_intro',ARC_Config::description());
        $fresh=!get_user_meta($uid,'_arc_welcome_done',true);
        $profile=class_exists('AR_Community')?AR_Community::community_account_url():home_url('/muj-ucet/');
        $affiliate=function_exists('wc_get_account_endpoint_url')?wc_get_account_endpoint_url('fresh-affiliate'):home_url('/muj-ucet/fresh-affiliate/');
        $links=[
          ['Uprav si profil','Fotografie, oblíbené sporty a nastavení soukromí.',$profile.'#arc-edit-profile','user-round'],
          ['Pozvi přátele','Vyhledej členy nebo pošli pozvánku přes WhatsApp a e-mail.',ARC_Navigation::app_link('pratele'),'users'],
          ['Sdílej příspěvek','Přidej příspěvek, fotografii nebo komentář.',ARC_Config::app_url(),'message-circle'],
          ['Instaluj aplikaci','Komunita kdykoli po ruce v telefonu.',ARC_Config::app_url().'#arc-install','smartphone'],
        ];
        $setup_at=(int)get_user_meta($uid,'_arc_password_setup_notice',true);
        $mail_notice=($setup_at && $setup_at>time()-30*DAY_IN_SECONDS)
            ? '<div class="arc-welcome-password" role="status"><strong>Účet je připravený.</strong> Na registrační e-mail jsme ti poslali odkaz pro nastavení hesla. Už teď můžeš komunitu používat. Pokud zpráva nepřišla, zkontroluj spam nebo použij možnost Zapomenuté heslo.</div>'
            : '';
        $out='<section class="arc-panel arc-welcome" data-arc-welcome>'.$mail_notice.'<div class="arc-welcome-eyebrow">'.esc_html(mb_strtoupper(ARC_Config::short_name())).' · VÍTEJ V KOMUNITĚ</div><h2>'.esc_html($heading).'</h2><p>'.nl2br(esc_html($intro)).'</p>';
        $out.='<p><strong>Co tu najdeš?</strong> Můžeš sdílet příspěvky veřejně nebo jen s přáteli, reagovat a najít lidi s podobnými zájmy.</p>';
        if($fresh || !empty($GLOBALS['arc_welcome_reopen'])){
            $active=min(3,max(0,(int)get_user_meta($uid,'_arc_welcome_step',true)));
            $out.='<div class="arc-welcome-first"><h3>První kroky v komunitě</h3><p class="arc-welcome-guide-intro">Projdeme společně čtyři jednoduché kroky. Kdykoli můžeš pokračovat tam, kde jsi skončil/a, nebo průvodce přeskočit.</p>';
            $out.='<div class="arc-welcome-flow" data-arc-welcome-flow data-active-step="'.esc_attr((string)$active).'">';
            $out.='<div class="arc-welcome-step-info" aria-live="polite">Krok <strong data-arc-welcome-current>'.($active+1).'</strong> ze 4</div>';
            $out.='<nav class="arc-welcome-progress" aria-label="Postup úvodním průvodcem">';
            foreach($links as $i=>$link){
                $out.='<button type="button" class="arc-welcome-progress-item'.($i===$active?' is-active':'').($i<$active?' is-past':'').'" data-arc-welcome-go="'.$i.'" aria-label="Krok '.($i+1).': '.esc_attr($link[0]).'" aria-current="'.($i===$active?'step':'false').'"><span class="arc-welcome-progress-number">'.($i+1).'</span><span class="arc-welcome-progress-label">'.esc_html($link[0]).'</span></button>';
            }
            $out.='</nav><div class="arc-welcome-progress-track" aria-hidden="true"><span data-arc-welcome-progress-fill style="width:'.(25*($active+1)).'%"></span></div>';
            foreach($links as $i=>$link){
                $out.='<section class="arc-welcome-slide" data-arc-welcome-slide="'.$i.'"'.($i!==$active?' hidden':'').'><h4>'.esc_html($link[0]).'</h4><p>'.esc_html($link[1]).'</p>';
                if($i===3){$out.='<div class="arc-welcome-install">'.do_shortcode('[ar_instalovat_aplikaci]').'</div>';}
                $out.='</section>'; // Navigation belongs to the permanent directory below.
            }
            $out.='<div class="arc-welcome-controls"><button type="button" class="arc-btn arc-btn-subtle" data-arc-welcome-prev'.($active===0?' hidden':'').'>← Zpět</button><button type="button" class="arc-btn" data-arc-welcome-next'.($active===3?' hidden':'').'>Další krok →</button><button type="button" class="arc-btn" data-arc-welcome-done'.($active!==3?' hidden':'').'>Dokončit průvodce</button></div></div>';
            $out.='<div class="arc-welcome-finish"><button type="button" class="arc-welcome-skip" data-arc-welcome-done>Přeskočit průvodce</button><span data-arc-welcome-status role="status" aria-live="polite"></span></div></div>';
        }
        if(ARC_Config::enabled('show_affiliate') && function_exists('wc_get_account_endpoint_url'))$out.='<div class="arc-welcome-affiliate"><h3>Referenční program</h3><p>Pozvi další lidi a zjisti podmínky případných odměn přímo v programu.</p><a class="arc-btn arc-btn-subtle" href="'.esc_url($affiliate).'">Zjistit více o referenčním programu →</a></div>';
        if(ARC_Config::enabled('show_premium'))$out.='<p class="arc-welcome-premium"><strong>Premium:</strong> Další možnosti pro členy. <a href="'.esc_url(home_url('/premium/')).'">Více o Premium →</a></p>';
        $out.='</section>';
        if($fresh || !empty($GLOBALS['arc_welcome_reopen'])){
            wp_enqueue_script('arc-onboarding',ARC_URL.'assets/onboarding.js',[],ARC_VERSION,true);
            wp_add_inline_script('arc-onboarding','window.ARCWelcome='.wp_json_encode(['url'=>admin_url('admin-ajax.php'),'nonce'=>wp_create_nonce('arc_welcome_done')]).';','before');
        }
        wp_enqueue_style('arc-onboarding',ARC_URL.'assets/onboarding.css',[],ARC_VERSION);
        return $out;
    }
}
