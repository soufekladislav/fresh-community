<?php
defined('ABSPATH') || exit;
/** Paid applications hub. Existing legacy billing stays untouched. */
final class ARC_Premium {
    public static function catalog(): array {
        return [
          ['key'=>'ar-jidelnicek','label'=>'AR Jídelníček','description'=>'Jídelníček, plán pohybu a energetická kalkulačka','status'=>'available','url'=>home_url('/premium/jidelnicek/')],
          ['key'=>'cykloservisy','label'=>'Mapa cykloservisů','description'=>'Vyhledávání servisů kol','status'=>'planned'],
          ['key'=>'singletraily','label'=>'Mapa singletrailů','description'=>'Tipy na trasy','status'=>'planned'],
          ['key'=>'fitness-benefity','label'=>'Fitness výhody','description'=>'Speciální nabídky pro členy','status'=>'planned'],
          ['key'=>'event-benefity','label'=>'Speciální eventy','description'=>'Připravované komunitní výhody','status'=>'planned'],
        ];
    }
    /** Read-only: never starts a trial, creates an order, or changes Stripe data. */
    public static function access(int $uid): array {
        $scopes=apply_filters('arc_premium_entitlements',[],$uid);
        $scopes=is_array($scopes)?array_values(array_filter($scopes,'is_string')):[];
        // Optional WooCommerce Subscriptions adapter. No recurring charge is
        // ever initiated by Fresh Community: the payment plugin is authoritative.
        if($uid && function_exists('wcs_user_has_subscription')) {
            $premiumProduct=get_page_by_path('adrenaline-live-predplatne',OBJECT,'product');
            $premiumId=($premiumProduct instanceof WP_Post)?(int)$premiumProduct->ID:0;
            if($premiumId && wcs_user_has_subscription($uid,$premiumId,'active')) {
                $scopes[]='ar-jidelnicek';
            }
        }

        if($uid && class_exists('ALF_Plugin') && method_exists(ALF_Plugin::instance(),'get_entitlement')) {
            $legacy=ALF_Plugin::instance()->get_entitlement($uid);
            if($legacy && ($legacy->status??'')==='active' && !empty($legacy->access_until)
                && strtotime($legacy->access_until.' UTC')>=time())$scopes[]='ar-jidelnicek';
        }
        return ['enabled'=>count($scopes)>0,'entitlements'=>array_values(array_unique($scopes)),
            'provider'=>'woocommerce_subscriptions_or_legacy_read_only','apps'=>apply_filters('arc_premium_apps',self::catalog()),
            'message'=>'Komunitní účet je zdarma. Prémiové aplikace se otevírají pouze s odpovídajícím oprávněním.'];
    }
    public static function render(): string {
        wp_enqueue_style('arc-live',ARC_URL.'assets/live.css',[],ARC_VERSION);
        $access=self::access(get_current_user_id());
        $out='<section class="arc-premium-hub"><div class="arc-premium-intro"><span>ADRENALINE LIVE PREMIUM</span><h1>Více možností pro aktivní život</h1><p>Komunita je zdarma. Nadstandardní aplikace postupně přibývají podle potřeb členů.</p></div><div class="arc-premium-grid">';
        foreach($access['apps'] as $app){
            $key=sanitize_key((string)($app['key']??''));$label=esc_html((string)($app['label']??''));
            $available=($app['status']??'')==='available' && in_array($key,$access['entitlements'],true);
            $out.='<article class="arc-premium-card"><small>'.($available?'AKTIVNÍ':'PŘIPRAVUJEME').'</small><h2>'.$label.'</h2><p>'.esc_html((string)($app['description']??'')).'</p>';
            if($available && !empty($app['url']))$out.='<a class="arc-premium-link" href="'.esc_url($app['url']).'">Otevřít aplikaci →</a>';
            elseif(($app['status']??'')==='available')$out.='<span class="arc-premium-muted">Dostupné po aktivaci příslušného členství</span>';
            else $out.='<span class="arc-premium-muted">Připravujeme</span>';
            $out.='</article>';
        }
        return $out.'</div><p class="arc-premium-note">Přístup k aplikacím se ověřuje podle aktivního předplatného. Platby a jejich obnovení spravuje WooCommerce s příslušným předplatným.</p></section>';
    }
}
