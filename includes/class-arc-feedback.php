<?php
defined('ABSPATH') || exit;

/** Logged-in member feedback. Independent of WooCommerce and Fresh Affiliate. */
final class ARC_Feedback {
    private const SCHEMA = '1';
    private const OPTION_EMAIL = 'arc_feedback_email_enabled';

    private static function table(): string {
        global $wpdb;
        return $wpdb->prefix . 'arc_feedback';
    }

    public static function install(): void {
        global $wpdb;
        require_once ABSPATH . 'wp-admin/includes/upgrade.php';
        $table = self::table();
        $charset = $wpdb->get_charset_collate();
        dbDelta("CREATE TABLE $table (
            id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
            user_id bigint(20) unsigned NOT NULL,
            user_email varchar(190) NOT NULL DEFAULT '',
            user_display varchar(190) NOT NULL DEFAULT '',
            message text NOT NULL,
            status varchar(20) NOT NULL DEFAULT 'new',
            created_at_gmt datetime NOT NULL,
            handled_at_gmt datetime NULL,
            PRIMARY KEY  (id),
            KEY status_date (status,created_at_gmt),
            KEY member_date (user_id,created_at_gmt)
        ) $charset;");
        update_option('arc_feedback_db_version', self::SCHEMA, false);
    }

    public static function boot(): void {
        add_action('init', [self::class, 'maybe_install'], 9);
        add_action('wp_enqueue_scripts', [self::class, 'assets'], 25);
        add_action('wp_footer', [self::class, 'widget'], 87);
        add_action('wp_ajax_arc_feedback_send', [self::class, 'submit']);
        add_action('admin_menu', [self::class, 'admin_menu'], 31);
        add_action('admin_post_arc_feedback_settings', [self::class, 'save_settings']);
        add_action('admin_post_arc_feedback_handle', [self::class, 'handle']);
    }

    public static function maybe_install(): void {
        if ((string)get_option('arc_feedback_db_version') !== self::SCHEMA) self::install();
    }

    public static function assets(): void {
        if (is_admin() || !is_user_logged_in()) return;
        wp_enqueue_style('arc-feedback', ARC_URL . 'assets/feedback.css', [], ARC_VERSION);
        wp_enqueue_script('arc-feedback', ARC_URL . 'assets/feedback.js', [], ARC_VERSION, true);
        wp_add_inline_script('arc-feedback', 'window.ARCFeedback=' . wp_json_encode([
            'ajaxUrl' => admin_url('admin-ajax.php'),
            'nonce' => wp_create_nonce('arc_feedback_send'),
        ]) . ';', 'before');
    }

    public static function widget(): void {
        if (is_admin() || !is_user_logged_in()) return;
        echo '<aside class="arc-feedback-widget" data-arc-feedback aria-label="Zpětná vazba">';
        echo '<button type="button" class="arc-feedback-tab" data-arc-feedback-toggle aria-controls="arc-feedback-panel" aria-expanded="false"><span class="arc-feedback-tab-label">Zpětná vazba</span></button>';
        echo '<section class="arc-feedback-panel" id="arc-feedback-panel" data-arc-feedback-panel inert aria-hidden="true" aria-label="Odeslat zpětnou vazbu">';
        echo '<div class="arc-feedback-heading"><strong>Zpětná vazba</strong><button type="button" data-arc-feedback-close aria-label="Zavřít formulář">×</button></div>';
        echo '<p>Jsme na začátku a víme, že ještě máme co zlepšovat. Neboj se nám říct svůj názor, upozornit na chybu nebo poslat nápad. Každé zprávě se věnujeme – děláme to pro vás. ❤️</p>';
        echo '<form data-arc-feedback-form><label for="arc-feedback-message">Tvoje zpráva</label>';
        echo '<textarea id="arc-feedback-message" name="message" rows="5" minlength="5" maxlength="3000" required placeholder="Co se povedlo, co nefunguje nebo co ti v komunitě chybí?"></textarea>';
        echo '<small>Zpráva se uloží k tvému přihlášenému účtu.</small>';
        echo '<button type="submit" data-arc-feedback-submit>Odeslat zpětnou vazbu</button>';
        echo '<p class="arc-feedback-result" data-arc-feedback-result role="status" aria-live="polite"></p></form></section>';
        echo '</aside>';
    }

    public static function submit(): void {
        if (!is_user_logged_in()) wp_send_json_error(['message' => 'Pro odeslání se přihlas.'], 401);
        check_ajax_referer('arc_feedback_send', 'nonce');
        $user_id = get_current_user_id();
        $message = sanitize_textarea_field((string)wp_unslash($_POST['message'] ?? ''));
        $length = function_exists('mb_strlen') ? mb_strlen($message) : strlen($message);
        if ($length < 5 || $length > 3000) wp_send_json_error(['message' => 'Zpráva musí mít 5 až 3000 znaků.'], 400);
        $count = (int)get_transient('arc_feedback_count_' . $user_id);
        if ($count >= 6) wp_send_json_error(['message' => 'Příliš mnoho zpráv. Zkus to prosím později.'], 429);
        $last = (int)get_transient('arc_feedback_recent_' . $user_id);
        if ($last) wp_send_json_error(['message' => 'Další zprávu můžeš odeslat za chvíli.'], 429);
        $user = wp_get_current_user();
        global $wpdb;
        $result = $wpdb->insert(self::table(), [
            'user_id' => $user_id,
            'user_email' => (string)$user->user_email,
            'user_display' => (string)$user->display_name,
            'message' => $message,
            'status' => 'new',
            'created_at_gmt' => current_time('mysql', true),
        ], ['%d', '%s', '%s', '%s', '%s', '%s']);
        if ($result !== 1) wp_send_json_error(['message' => 'Zprávu se nepodařilo uložit. Zkus to znovu.'], 500);
        $id = (int)$wpdb->insert_id;
        set_transient('arc_feedback_count_' . $user_id, $count + 1, HOUR_IN_SECONDS);
        set_transient('arc_feedback_recent_' . $user_id, 1, 15);
        if (get_option(self::OPTION_EMAIL, 'no') === 'yes') {
            $to = sanitize_email((string)get_option('admin_email'));
            if (is_email($to)) {
                $body = "Zpětná vazba Fresh Community (#$id)\n\nUživatel: {$user->display_name} (WP ID: $user_id)\nE-mail: {$user->user_email}\n\nZpráva:\n$message\n\nAdministrace:\n" . admin_url('admin.php?page=arc-feedback');
                // Email is informational; failure must never undo a saved message.
                wp_mail($to, 'Fresh Community – nová zpětná vazba #' . $id, $body);
            }
        }
        wp_send_json_success(['message' => 'Děkujeme! Tvoje zpětná vazba byla uložena.']);
    }

    public static function admin_menu(): void {
        add_submenu_page('ar-community', 'Zpětná vazba', 'Zpětná vazba', 'manage_options', 'arc-feedback', [self::class, 'admin_page']);
    }

    public static function save_settings(): void {
        if (!current_user_can('manage_options')) wp_die('Nemáš oprávnění.', 403);
        check_admin_referer('arc_feedback_settings');
        update_option(self::OPTION_EMAIL, !empty($_POST['send_email']) ? 'yes' : 'no', false);
        wp_safe_redirect(add_query_arg(['page' => 'arc-feedback', 'settings_saved' => 1], admin_url('admin.php')));
        exit;
    }

    public static function handle(): void {
        if (!current_user_can('manage_options')) wp_die('Nemáš oprávnění.', 403);
        $id = absint($_POST['feedback_id'] ?? 0);
        check_admin_referer('arc_feedback_handle_' . $id);
        $status = sanitize_key((string)wp_unslash($_POST['status'] ?? ''));
        if ($id && in_array($status, ['new', 'handled'], true)) {
            global $wpdb;
            $wpdb->update(self::table(), [
                'status' => $status,
                'handled_at_gmt' => $status === 'handled' ? current_time('mysql', true) : null,
            ], ['id' => $id], ['%s', '%s'], ['%d']);
        }
        wp_safe_redirect(add_query_arg(['page' => 'arc-feedback', 'updated' => 1], admin_url('admin.php')));
        exit;
    }

    public static function admin_page(): void {
        if (!current_user_can('manage_options')) wp_die('Nemáš oprávnění.', 403);
        global $wpdb;
        $table = self::table();
        $status = sanitize_key((string)wp_unslash($_GET['status'] ?? 'all'));
        if (!in_array($status, ['all', 'new', 'handled'], true)) $status = 'all';
        $page = max(1, absint($_GET['paged'] ?? 1));
        $where = $status === 'all' ? '1=1' : $wpdb->prepare('status = %s', $status);
        $total = (int)$wpdb->get_var("SELECT COUNT(*) FROM $table WHERE $where");
        $rows = $wpdb->get_results($wpdb->prepare("SELECT * FROM $table WHERE $where ORDER BY id DESC LIMIT %d OFFSET %d", 30, ($page - 1) * 30));
        echo '<div class="wrap"><h1>Fresh Community – Zpětná vazba</h1>';
        if (isset($_GET['settings_saved'])) echo '<div class="notice notice-success"><p>Nastavení bylo uloženo.</p></div>';
        if (isset($_GET['updated'])) echo '<div class="notice notice-success"><p>Stav zprávy byl změněn.</p></div>';
        echo '<form method="post" action="' . esc_url(admin_url('admin-post.php')) . '" style="background:#fff;border:1px solid #ddd;padding:15px;max-width:800px;margin:20px 0">';
        echo '<input type="hidden" name="action" value="arc_feedback_settings">';
        wp_nonce_field('arc_feedback_settings');
        echo '<label><input type="checkbox" name="send_email" value="1" ' . checked(get_option(self::OPTION_EMAIL, 'no'), 'yes', false) . '> Posílat každou novou zpětnou vazbu na administrátorský e-mail webu (<strong>' . esc_html((string)get_option('admin_email')) . '</strong>)</label>';
        echo '<p class="description">Všechny zprávy se ukládají do databáze bez ohledu na nastavení e-mailů.</p>';
        submit_button('Uložit nastavení', 'secondary', 'submit', false);
        echo '</form><p>';
        foreach (['all' => 'Všechny', 'new' => 'Nové', 'handled' => 'Vyřešené'] as $key => $label) {
            echo '<a class="button ' . ($key === $status ? 'button-primary' : '') . '" style="margin-right:7px" href="' . esc_url(add_query_arg(['page' => 'arc-feedback', 'status' => $key], admin_url('admin.php'))) . '">' . esc_html($label) . '</a>';
        }
        echo '</p><p>Celkem zpráv: <strong>' . $total . '</strong></p>';
        echo '<table class="widefat striped" style="max-width:1250px"><thead><tr><th>Datum</th><th>Uživatel</th><th>Zpráva</th><th>Stav / Akce</th></tr></thead><tbody>';
        if (!$rows) echo '<tr><td colspan="4">Žádná zpětná vazba.</td></tr>';
        foreach ($rows as $row) {
            $user = get_userdata((int)$row->user_id);
            $display = $user ? $user->display_name : $row->user_display;
            $email = $user ? $user->user_email : $row->user_email;
            $user_url = $user ? get_edit_user_link((int)$user->ID) : '';
            echo '<tr><td>' . esc_html(get_date_from_gmt($row->created_at_gmt, 'd. m. Y H:i')) . '<br><small>#' . (int)$row->id . '</small></td><td>';
            if ($user_url) echo '<a href="' . esc_url($user_url) . '">';
            echo esc_html($display);
            if ($user_url) echo '</a>';
            echo '<br><small>' . esc_html($email) . ' · ID ' . (int)$row->user_id . '</small></td>';
            echo '<td style="max-width:600px;white-space:pre-wrap;overflow-wrap:anywhere">' . esc_html($row->message) . '</td><td>';
            echo '<strong>' . ($row->status === 'handled' ? 'Vyřešeno' : 'Nové') . '</strong><form method="post" action="' . esc_url(admin_url('admin-post.php')) . '" style="margin-top:10px">';
            echo '<input type="hidden" name="action" value="arc_feedback_handle"><input type="hidden" name="feedback_id" value="' . (int)$row->id . '"><input type="hidden" name="status" value="' . ($row->status === 'handled' ? 'new' : 'handled') . '">';
            wp_nonce_field('arc_feedback_handle_' . (int)$row->id);
            echo '<button type="submit" class="button">' . ($row->status === 'handled' ? 'Vrátit mezi nové' : 'Označit vyřešené') . '</button></form></td></tr>';
        }
        echo '</tbody></table>';
        $pages = (int)ceil($total / 30);
        if ($pages > 1) {
            echo '<p>';
            for ($i = 1; $i <= min($pages, 25); $i++) echo '<a class="button ' . ($i === $page ? 'button-primary' : '') . '" style="margin-right:5px" href="' . esc_url(add_query_arg(['page' => 'arc-feedback', 'paged' => $i, 'status' => $status], admin_url('admin.php'))) . '">' . $i . '</a>';
            echo '</p>';
        }
        echo '</div>';
    }
}
