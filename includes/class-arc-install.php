<?php
defined('ABSPATH') || exit;
/**
 * Non-invasive PWA installer for the configured Fresh Community instance.
 * Does not alter user accounts, community records, WooCommerce, push subscriptions,
 * or the existing service worker registration and scope.
 */
final class ARC_Install {
    private static bool $printed = false;

    public static function boot(): void {
        add_action('init', [self::class, 'shortcodes']);
        add_action('wp_enqueue_scripts', [self::class, 'assets'], 30);
        add_action('wp_head', [self::class, 'manifest_on_shortcode_page'], 31);
    }

    public static function shortcodes(): void {
        add_shortcode('ar_instalovat_aplikaci', [self::class, 'button']);
        add_shortcode('ar_install_app', [self::class, 'button']);
    }

    private static function has_installer_shortcode(): bool {
        if (is_admin() || !is_singular()) return false;
        $page = get_queried_object();
        if (!$page instanceof WP_Post) return false;
        $text = (string)$page->post_content;
        if (has_shortcode($text, 'ar_instalovat_aplikaci') || has_shortcode($text, 'ar_install_app')) return true;
        $elementor = (string)get_post_meta($page->ID, '_elementor_data', true);
        return str_contains($elementor, '[ar_instalovat_aplikaci') || str_contains($elementor, '[ar_install_app');
    }

    public static function assets(): void {
        wp_register_style('arc-install-pwa', ARC_URL.'assets/install-pwa.css', [], ARC_VERSION);
        wp_register_script('arc-install-pwa', ARC_URL.'assets/install-pwa.js', [], ARC_VERSION, true);
        $is_app = class_exists('ARC_PWA') && ARC_PWA::on_live();
        $has_shortcode = self::has_installer_shortcode();
        // Preload the CTA style in wp_head; shortcodes rendered by Elementor can be late.
        if ($has_shortcode) wp_enqueue_style('arc-install-pwa');
        if ($is_app || $has_shortcode) self::enqueue();
    }

    private static function enqueue(): void {
        wp_enqueue_script('arc-install-pwa');
        wp_add_inline_script('arc-install-pwa', 'window.ARCInstallConfig='.wp_json_encode([
            'appUrl' => esc_url_raw(ARC_Config::app_url()),
            'name' => ARC_Config::name(),
            'version' => ARC_VERSION,
            'isApp' => class_exists('ARC_PWA') && ARC_PWA::on_live(),
            'deviceEndpoint' => is_user_logged_in() ? esc_url_raw(rest_url('ar-community/v1/pwa-device')) : '',
            'restNonce' => is_user_logged_in() ? wp_create_nonce('wp_rest') : '',
        ]).';', 'before');
    }

    public static function manifest_on_shortcode_page(): void {
        // /app/ already receives this manifest from ARC_PWA. Do not print a duplicate there.
        if (!self::has_installer_shortcode() || (class_exists('ARC_PWA') && ARC_PWA::on_live())) return;
        echo '<link rel="manifest" href="'.esc_url(add_query_arg('arc_pwa', 'manifest', ARC_Config::app_url())).'">' . "\n";
    }

    public static function button($raw_atts = []): string {
        $atts = shortcode_atts([
            'text' => 'Instalovat aplikaci',
            'popis' => ARC_Config::name().' vždy po ruce. Komunita přímo v mobilu i počítači.',
        ], (array)$raw_atts, 'ar_instalovat_aplikaci');
        if (!wp_style_is('arc-install-pwa', 'registered')) {
            wp_register_style('arc-install-pwa', ARC_URL.'assets/install-pwa.css', [], ARC_VERSION);
        }
        if (!wp_script_is('arc-install-pwa', 'registered')) {
            wp_register_script('arc-install-pwa', ARC_URL.'assets/install-pwa.js', [], ARC_VERSION, true);
        }
        wp_enqueue_style('arc-install-pwa');
        self::enqueue();
        $id = wp_unique_id('arc-install-');
        $html = '<div class="arc-install-widget" data-arc-install-widget>';
        $html .= '<span class="arc-install-description">'.esc_html((string)$atts['popis']).'</span>';
        $html .= '<button type="button" class="arc-install-button" data-arc-install-trigger aria-describedby="'.esc_attr($id).'">';
        $html .= '<span class="arc-install-button-icon" aria-hidden="true">↓</span><span data-arc-install-label>'.esc_html((string)$atts['text']).'</span></button>';
        $html .= '<span id="'.esc_attr($id).'" class="arc-install-status" data-arc-install-status aria-live="polite"></span>';
        $html .= '</div>';
        // A single dialog shared by every button on the page. No automatic permission request.
        if (!self::$printed) {
            self::$printed = true;
            $html .= '<dialog class="arc-install-guide" data-arc-install-guide aria-labelledby="arc-install-guide-title">';
            $html .= '<div class="arc-install-guide-inner"><button type="button" class="arc-install-close" data-arc-install-close aria-label="Zavřít">×</button>';
            $html .= '<h3 id="arc-install-guide-title">Instalace '.esc_html(ARC_Config::name()).'</h3>';
            $html .= '<p data-arc-install-instructions>Aplikaci lze přidat přímo z nabídky prohlížeče.</p>';
            $html .= '<a class="arc-install-open" href="'.esc_url(ARC_Config::app_url()).'">Otevřít '.esc_html(ARC_Config::name()).'</a>';
            $html .= '</div></dialog>';
        }
        return $html;
    }
}
