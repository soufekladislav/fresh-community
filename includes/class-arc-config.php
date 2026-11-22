<?php
/**
 * Per-site community configuration. Existing Adrenaline Live installations
 * retain their prior identity until an administrator changes these settings.
 */
defined('ABSPATH') || exit;
final class ARC_Config {
    public const OPTION = 'arc_community_settings';

    public static function legacy_site(): bool {
        $page = get_page_by_path('app', OBJECT, 'page');
        if (!$page instanceof WP_Post) return false;
        $source = (string) $page->post_content . ' ' . (string) get_post_meta($page->ID, '_elementor_data', true);
        return str_contains($source, '[ar_live') || str_contains($source, '[ar_community_wall');
    }

    public static function defaults(): array {
        $legacy = self::legacy_site();
        return [
            'name' => $legacy ? 'Adrenaline Live' : 'Fresh Community',
            'registration_mode' => $legacy ? 'public' : 'invite',
            'short_name' => $legacy ? 'AR Live' : 'Komunita',
            'description' => $legacy ? 'Komunita sportovců, turistů a aktivních lidí. Žij. Zažívej. Sdílej.' : 'Komunita pro lidi se společnými zájmy.',
            'app_page_id' => $legacy ? (int) get_page_by_path('app')->ID : 0,
            'shop_url' => $legacy ? home_url('/obchod/') : '',
            'show_shop' => $legacy ? 1 : 0,
            'show_premium' => $legacy ? 1 : 0,
            'show_affiliate' => $legacy ? 1 : 0,
            'show_youtube' => $legacy ? 1 : 0,
            'global_navigation' => $legacy ? 1 : 0,
            'woo_dashboard' => $legacy ? 1 : 0,
            'sport_fields' => $legacy ? 1 : 0,
            'icon_192' => '',
            'icon_512' => '',
            'theme_color' => '#343434',
            'background_color' => '#FFFFFF',
        ];
    }

    public static function all(): array {
        $stored = get_option(self::OPTION, []);
        return array_replace(self::defaults(), is_array($stored) ? $stored : []);
    }

    public static function get(string $key) {
        $all = self::all();
        return $all[$key] ?? null;
    }

    public static function name(): string { return (string) self::get('name'); }
    public static function short_name(): string { return (string) self::get('short_name'); }
    public static function description(): string { return (string) self::get('description'); }
    public static function enabled(string $flag): bool { return (bool) self::get($flag); }

    public static function app_page_id(): int {
        $id = absint(self::get('app_page_id'));
        return $id && get_post_status($id) === 'publish' ? $id : 0;
    }

    public static function app_url(): string {
        $id = self::app_page_id();
        if ($id) return (string) get_permalink($id);
        return self::legacy_site() ? home_url('/app/') : home_url('/');
    }

    /** A dedicated community route must contain the actual shortcode. */
    public static function is_app_page(): bool {
        $id = self::app_page_id();
        if ($id && is_page($id)) return true;
        if (!self::legacy_site() || !is_page('app')) return false;
        return true;
    }

    public static function icon_url(int $size): string {
        $size = $size === 512 ? 512 : 192;
        $custom = esc_url_raw((string) self::get('icon_'.$size));
        if ($custom) return $custom;
        if (!self::legacy_site() && function_exists('get_site_icon_url')) {
            $site = get_site_icon_url($size);
            if ($site) return (string) $site;
        }
        return ARC_URL.'assets/pwa-icon-'.$size.'.png';
    }

    public static function color(string $key): string {
        $value = sanitize_hex_color((string) self::get($key));
        return $value ?: ($key === 'background_color' ? '#FFFFFF' : '#343434');
    }

    public static function shop_url(): string {
        $url = (string) self::get('shop_url');
        return $url !== '' ? $url : home_url('/');
    }

    public static function save_from_post(array $post): void {
        $defaults = self::defaults();
        $old = self::all();
        $values = [];
        $mode=sanitize_key((string)wp_unslash($post['arc_registration_mode']??$old['registration_mode']));
        $values['registration_mode']=in_array($mode,['public','invite'],true)?$mode:'invite';
        foreach (['name', 'short_name', 'description'] as $key) {
            $value = sanitize_text_field((string) wp_unslash($post['arc_'.$key] ?? ''));
            $max = $key === 'description' ? 220 : ($key === 'name' ? 80 : 28);
            $value = function_exists('mb_substr') ? mb_substr($value, 0, $max) : substr($value, 0, $max);
            $values[$key] = $value !== '' ? $value : $defaults[$key];
        }
        $page = absint($post['arc_app_page_id'] ?? 0);
        $values['app_page_id'] = $page && get_post_type($page) === 'page' ? $page : 0;
        $url = esc_url_raw((string) wp_unslash($post['arc_shop_url'] ?? ''));
        $values['shop_url'] = $url;
        foreach (['icon_192','icon_512'] as $key) $values[$key] = esc_url_raw((string) wp_unslash($post['arc_'.$key] ?? ''));
        foreach (['theme_color','background_color'] as $key) {
            $values[$key] = sanitize_hex_color((string) wp_unslash($post['arc_'.$key] ?? '')) ?: $defaults[$key];
        }
        foreach (['show_shop','show_premium','show_affiliate','show_youtube','global_navigation','woo_dashboard','sport_fields'] as $key) {
            $values[$key] = !empty($post['arc_'.$key]) ? 1 : 0;
        }
        update_option(self::OPTION, array_replace($old, $values), false);
    }
}
