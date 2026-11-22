<?php
/**
 * Plugin Name: Fresh Community
 * Description: Konfigurovatelné komunitní prostředí pro WordPress – profily, přátelé, příspěvky a mikrokomunity.
 * Version: 0.2.0-beta.10.17
 * Update URI: https://freshcrackers.cz/fresh-community/
 * Requires at least: 6.3
 * Requires PHP: 8.0
 * Text Domain: ar-community
 * Author: Fresh Crackers
 * Fresh Console: yes
 * Fresh Admin Slug: ar-community
 * Fresh Icon: dashicons-groups
 * Fresh Description: Komunitní síť
 */
defined('ABSPATH') || exit;
define('ARC_VERSION', '0.2.0-beta.10.17');
define('ARC_FILE', __FILE__);
define('ARC_PATH', plugin_dir_path(__FILE__));
define('ARC_URL', plugin_dir_url(__FILE__));
require_once ARC_PATH . 'includes/class-arc-config.php';
require_once ARC_PATH . 'includes/class-arc-data.php';
require_once ARC_PATH . 'includes/class-arc-integrations.php';
require_once ARC_PATH . 'includes/class-arc-price.php';
require_once ARC_PATH . 'includes/class-arc-api.php';
require_once ARC_PATH . 'includes/class-arc-shortcodes.php';
require_once ARC_PATH . 'includes/class-arc-saved.php';
require_once ARC_PATH . 'includes/class-arc-admin.php';
require_once ARC_PATH . 'includes/class-arc-profile.php';
require_once ARC_PATH . 'includes/class-arc-relations.php';
require_once ARC_PATH . 'includes/class-arc-wall.php';
require_once ARC_PATH . 'includes/class-arc-wall-private-media.php';
require_once ARC_PATH . 'includes/class-arc-live-api.php';
require_once ARC_PATH . 'includes/class-arc-live-shortcodes.php';
require_once ARC_PATH . 'includes/class-arc-premium.php';
require_once ARC_PATH . 'includes/class-ar-community.php';
require_once ARC_PATH . 'includes/class-arc-google.php';
require_once ARC_PATH . 'includes/class-arc-account.php';
require_once ARC_PATH . 'includes/class-arc-invites.php';
require_once ARC_PATH . 'includes/class-arc-navigation.php';
require_once ARC_PATH . 'includes/class-arc-notifications.php';
require_once ARC_PATH . 'includes/class-arc-pwa.php';
require_once ARC_PATH . 'includes/class-arc-install.php';
require_once ARC_PATH . 'includes/class-arc-pwa-devices.php';
require_once ARC_PATH . 'includes/class-arc-stripe-diagnostics.php';
require_once ARC_PATH . 'includes/class-arc-management.php';
require_once ARC_PATH . 'includes/class-arc-feedback.php';
require_once ARC_PATH . 'includes/class-arc-user-cleanup.php';
require_once ARC_PATH . 'includes/class-arc-onboarding.php';
require_once ARC_PATH . 'includes/class-arc-youtube.php';
register_activation_hook(__FILE__, ['AR_Community', 'activate']);
register_deactivation_hook(__FILE__, ['AR_Community', 'deactivate']);
add_action('plugins_loaded', ['AR_Community', 'boot']);
add_action('plugins_loaded', ['ARC_Install', 'boot']);
add_action('plugins_loaded', ['ARC_PWA_Devices', 'boot']);
add_action('plugins_loaded', ['ARC_Stripe_Diagnostics', 'boot']);
add_action('plugins_loaded', ['ARC_YouTube', 'boot']);
