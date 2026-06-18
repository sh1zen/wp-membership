<?php
/**
 * @author    sh1zen
 * @copyright Copyright (C) 2025.
 * @license   http://www.gnu.org/licenses/gpl.html GNU/GPL
 */

define("WPMC_ABSPATH", dirname(__DIR__) . '/');

const WPMC_INCPATH = WPMC_ABSPATH . 'inc/';
const WPMC_MODULES = WPMC_ABSPATH . 'modules/';
const WPMC_ADMIN = WPMC_ABSPATH . 'admin/';
const WPMC_SUPPORTERS = WPMC_MODULES . 'supporters/';

// wps-framework commons
if (!defined('WPS_FRAMEWORK')) {
    if (defined('WPS_FRAMEWORK_SOURCE') and file_exists(WPS_FRAMEWORK_SOURCE . 'loader.php')) {
        require_once WPS_FRAMEWORK_SOURCE . 'loader.php';
    }
    else {
        if (!file_exists(WPMC_ABSPATH . 'vendors/wps-framework/loader.php')) {
            return;
        }
        require_once WPMC_ABSPATH . 'vendors/wps-framework/loader.php';
    }
}

wps(
    'wpmc',
    [
        'modules_path' => WPMC_MODULES,
    ],
    [
        'cache'         => true,
        'ajax'          => true,
        'moduleHandler' => true,
    ]
);

define('WPMC_DEBUG', !wps_core()->online);

function wpmc_setup_db_table_constants(): void
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

wpmc_setup_db_table_constants();
