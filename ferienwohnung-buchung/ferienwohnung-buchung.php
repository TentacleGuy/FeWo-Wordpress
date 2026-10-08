<?php
/**
 * Plugin Name: Ferienwohnung Buchung
 * Description: Belegungskalender, Buchungsanfragen, Formularbaukasten, E-Mail-Vorlagen und PDF-Rechnungen für eine Ferienwohnung.
 * Version: 1.5.3
 * Requires at least: 6.4
 * Requires PHP: 8.1
 * License: GPL-2.0-or-later
 * Text Domain: ferienwohnung-buchung
 */
defined('ABSPATH') || exit;
define('FWB_VERSION', '1.5.3');
define('FWB_DIR', plugin_dir_path(__FILE__));
define('FWB_URL', plugin_dir_url(__FILE__));
foreach (['i18n', 'domain', 'settings', 'design', 'layout', 'store', 'documents', 'api', 'management', 'admin', 'frontend', 'designer'] as $file) {
    require_once FWB_DIR . 'includes/' . $file . '.php';
}
register_activation_hook(__FILE__, ['FWB_Store', 'install']);
add_action('plugins_loaded', static function () {
    if (get_option('fwb_version') !== FWB_VERSION) { FWB_Store::install(); }
    FWB_I18n::boot(); FWB_Designer::boot(); FWB_API::boot(); FWB_Admin::boot(); FWB_Frontend::boot();
});
