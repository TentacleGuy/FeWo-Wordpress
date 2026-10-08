<?php
// Local integration environment only. No real mail is sent by these tests.
define('WP_INSTALLING',true);
require __DIR__ . '/../tools/wordpress/wp-load.php';
require_once ABSPATH . 'wp-admin/includes/upgrade.php';
if (!is_blog_installed()) {
    wp_install('Ferienwohnung Test','fwb-test-admin','test@example.invalid',false,'','Local-Only-Test-Password-927!');
}
update_option('timezone_string','Europe/Berlin');
require_once ABSPATH . 'wp-admin/includes/plugin.php';
$result=activate_plugin('ferienwohnung-buchung/ferienwohnung-buchung.php');
if (is_wp_error($result)) { throw new RuntimeException($result->get_error_message()); }
echo 'WordPress ' . get_bloginfo('version') . " activated.\n";
