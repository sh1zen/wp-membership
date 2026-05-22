<?php
/**
 * @author    sh1zen
 * @copyright Copyright (C) 2025.
 * @license   http://www.gnu.org/licenses/gpl.html GNU/GPL
 */

use WPS\core\CronActions;
use WPS\core\Query;

CronActions::schedule("WPMC-notify-expiring", HOUR_IN_SECONDS * 3, 'wpmc_notify_expiring_members', '08:00');

CronActions::schedule("WPMC-check-expired", HOUR_IN_SECONDS * 3, 'wpmc_drop_expired_memberships', '09:00');

CronActions::schedule("WPMC-fix-tables", WEEK_IN_SECONDS, function () {

    $user_ids = Query::getInstance()->where_unquoted([
        'user_id' => Query::getInstance()->select('ID', Query::getInstance()->wpdb()->users)->compile(),
        'compare' => 'NOT IN'
    ])->select('user_id', WP_MEMBERSHIP_TABLE_SUBSCRIPTIONS)->query_multi() ?: [];

    foreach ($user_ids as $user_id) {
        wpmc_delete_member($user_id);
    }

}, '07:00');

add_action('delete_user', 'wpmc_delete_member', 10, 1);

add_action('wpmc_reset_membership', function ($user_id) {
    wps('wpmc')->cache->delete($user_id, 'user_subscription');
    wps('wpmc')->cache->delete($user_id, 'member');
}, 10, 1);
