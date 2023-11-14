<?php
/**
 * @author    sh1zen
 * @copyright Copyright (C) 2024.
 * @license   http://www.gnu.org/licenses/gpl.html GNU/GPL
 */

define("WPMS_ABSPATH", dirname(__DIR__) . '/');

const WPMS_INCPATH = WPMS_ABSPATH . 'inc/';
const WPMS_MODULES = WPMS_ABSPATH . 'modules/';
const WPMS_ADMIN = WPMS_ABSPATH . 'admin/';
const WPMS_SUPPORTERS = WPMS_MODULES . 'supporters/';

// wps-framework commons
if (!defined('WPS_FRAMEWORK')) {
    if (defined('WPS_FRAMEWORK_SOURCE') and file_exists(WPS_FRAMEWORK_SOURCE . 'loader.php')) {
        require_once WPS_FRAMEWORK_SOURCE . 'loader.php';
    }
    else {
        if (!file_exists(WPMS_ABSPATH . 'vendors/wps-framework/loader.php')) {
            return;
        }
        require_once WPMS_ABSPATH . 'vendors/wps-framework/loader.php';
    }
}

wps(
    'wpms',
    [
        'modules_path' => WPMS_MODULES,
    ],
    [
        'cache'         => true,
        'moduleHandler' => true,
    ]
);

define('WPMS_DEBUG', !wps_core()->online);

function wpms_setup_db_table_constants(): void
{
    global $wpdb;

    // prevent double initialization
    if (defined('WP_MEMBERSHIP_TABLE_LEVELS')) {
        return;
    }

    define('WP_MEMBERSHIP_TABLE_LEVELS', "{$wpdb->prefix}membership_levels");
    define('WP_MEMBERSHIP_TABLE_HISTORY', "{$wpdb->prefix}membership_history");
    define('WP_MEMBERSHIP_TABLE_SUBSCRIPTIONS', "{$wpdb->prefix}membership_subscriptions");
    define('WP_MEMBERSHIP_TABLE_COMMUNICATIONS', "{$wpdb->prefix}membership_communications");
    define('WP_MEMBERSHIP_TABLE_COMMUNICATIONS_SENT', "{$wpdb->prefix}membership_communications_sent");
}

wpms_setup_db_table_constants();
