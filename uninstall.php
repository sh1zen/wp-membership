<?php
/**
 * @author    sh1zen
 * @copyright Copyright (C) 2024.
 * @license   http://www.gnu.org/licenses/gpl.html GNU/GPL
 */

/**
 * Uninstall Procedure
 */
global $wpdb;

// if uninstall.php is not called by WordPress, die
if (!defined('WP_UNINSTALL_PLUGIN')) {
    die();
}

// setup constants
require_once __DIR__ . '/inc/wps_and_constants.php';

// Leave no trail
$option_names = array('wpms');

foreach ($option_names as $option_name) {
    delete_option($option_name);
}

$wpdb->query("DROP TABLE IF EXISTS " . wps('wpms')->options->table_name());
$wpdb->query("DROP TABLE IF EXISTS " . WP_MEMBERSHIP_TABLE_LEVELS);
$wpdb->query("DROP TABLE IF EXISTS " . WP_MEMBERSHIP_TABLE_HISTORY);
$wpdb->query("DROP TABLE IF EXISTS " . WP_MEMBERSHIP_TABLE_SUBSCRIPTIONS);
$wpdb->query("DROP TABLE IF EXISTS " . WP_MEMBERSHIP_TABLE_COMMUNICATIONS);
$wpdb->query("DROP TABLE IF EXISTS " . WP_MEMBERSHIP_TABLE_COMMUNICATIONS_SENT);

wps_uninstall();