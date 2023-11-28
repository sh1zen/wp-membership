<?php
/**
 * @author    sh1zen
 * @copyright Copyright (C) 2023.
 * @license   http://www.gnu.org/licenses/gpl.html GNU/GPL
 */

define('WPMS_DEBUG', $_SERVER["SERVER_ADDR"] === '127.0.0.1');

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

