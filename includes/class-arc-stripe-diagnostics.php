<?php
/** Read-only local Stripe diagnostics. Never starts payments or changes a webhook. */
defined('ABSPATH') || exit;
final class ARC_Stripe_Diagnostics {
    public static function boot(): void { add_action('admin_menu',[self::class,'menu'],30); }
    public static function menu(): void {
        add_submenu_page('ar-community','Kontrola Stripe','Kontrola Stripe','manage_woocommerce','arc-stripe-check',[self::class,'render']);
    }
    private static function status(bool $ok): string {return $ok?'Ano':'Ne';}
    public static function render(): void {
        if(!current_user_can('manage_woocommerce'))wp_die('Nedostatečná oprávnění.');
        $wc=class_exists('WooCommerce') && function_exists('WC');
        $gateway=class_exists('WC_Stripe_API');
        $settings=(array)get_option('woocommerce_stripe_settings',[]);
        $mode=!empty($settings['testmode'])&&$settings['testmode']==='yes'?'test':'live';
        $legacy=(array)get_option('alf_stripe_billing_state',[]);
        $state=(array)($legacy[$mode]??[]);
        if(!function_exists('is_plugin_active'))require_once ABSPATH.'wp-admin/includes/plugin.php';
        $old=is_plugin_active('adrenaline-live/adrenaline-life.php');
        $stateExists=!empty($state['webhook_id']) && !empty($state['webhook_secret']);
        global $wpdb;
        $table=$wpdb->prefix.'alf_billing_subscriptions';
        $hasSubs=$wpdb->get_var($wpdb->prepare('SHOW TABLES LIKE %s',$wpdb->esc_like($table)))===$table;
        $subCounts=$hasSubs?$wpdb->get_results('SELECT status,COUNT(*) total FROM `'.esc_sql($table).'` GROUP BY status',ARRAY_A):[];
        $events=$wpdb->prefix.'alf_billing_events';
        $hasEvents=$wpdb->get_var($wpdb->prepare('SHOW TABLES LIKE %s',$wpdb->esc_like($events)))===$events;
        $errors=$hasEvents?(int)$wpdb->get_var("SELECT COUNT(*) FROM `".esc_sql($events)."` WHERE status='failed'"):0;
        echo '<div class="wrap"><h1>AR Community – Stripe (lokální diagnostika)</h1>';
        echo '<p>Bez přímého přístupu k účtu Stripe tento přehled nemůže potvrdit úspěšné živé platby. Nic nesynchronizuje, nevytváří ani neopravuje.</p>';
        echo '<table class="widefat striped" style="max-width:850px"><tbody>';
        foreach([
            'WooCommerce načteno'=>self::status($wc),
            'WooCommerce Stripe API dostupné'=>self::status($gateway),
            'Platební metoda zapnuta'=>self::status(($settings['enabled']??'')==='yes'),
            'Režim Woo Stripe'=>strtoupper($mode),
            'Původní plugin aktivní'=>self::status($old),
            'Původní billing webhook konfigurován'=>self::status($stateExists),
            'Původní webhook má aktivní PHP handler'=>self::status($old),
            'Historické billing tabulky'=>self::status($hasSubs && $hasEvents),
            'Lokální chybné billing eventy'=>(string)$errors,
        ] as $key=>$value)echo '<tr><th style="width:46%">'.esc_html($key).'</th><td>'.esc_html($value).'</td></tr>';
        echo '</tbody></table><h2>Historie předplatných (lokální databáze)</h2>';
        if($subCounts){echo '<table class="widefat striped" style="max-width:550px"><thead><tr><th>Stav</th><th>Počet</th></tr></thead><tbody>';
            foreach($subCounts as $row)echo '<tr><td>'.esc_html((string)$row['status']).'</td><td>'.(int)$row['total'].'</td></tr>';
            echo '</tbody></table>';
        }else echo '<p>Nebyla nalezena žádná historická předplatná nebo tabulka neexistuje.</p>';
        if($stateExists && !$old)echo '<div class="notice notice-warning"><p>Ve Stripe může být nakonfigurován původní endpoint, který nyní neobsluhuje aktivní plugin. Ověřte v Stripe Dashboard → Developers → Webhooks skutečné doručování a aktivní předplatná. Plugin neaktivujte naslepo.</p></div>';
        echo '<p>Kontrolu skutečného Stripe live/test účtu proveďte samostatně; žádné API klíče ani webhook podpisy se zde nevypisují.</p></div>';
    }
}
