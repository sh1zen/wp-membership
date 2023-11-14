<?php
/**
 * @author    sh1zen
 * @copyright Copyright (C) 2024.
 * @license   http://www.gnu.org/licenses/gpl.html GNU/GPL
 */

use WPS\core\CronActions;
use WPS\core\Query;

CronActions::schedule("WPMS-notify-expiring", HOUR_IN_SECONDS * 3, 'wpms_notify_expiring_members', '08:00');

CronActions::schedule("WPMS-check-expired", HOUR_IN_SECONDS * 3, 'wpms_drop_expired_memberships', '09:00');

CronActions::schedule("WPMS-fix-tables", WEEK_IN_SECONDS, function () {

    $user_ids = Query::getInstance()->where([
        'user_id' => Query::getInstance()->select('ID', Query::getInstance()->wpdb()->users)->compile(),
        'compare' => 'NOT IN'
    ])->select('user_id', WP_MEMBERSHIP_TABLE_SUBSCRIPTIONS)->query_multi() ?: [];

    foreach ($user_ids as $user_id) {
        wpms_delete_member($user_id);
    }

}, '07:00');

add_action('delete_user', 'wpms_delete_member', 10, 1);

add_action('wpms_reset_membership', function ($user_id) {
    wps('wpms')->cache->delete($user_id, 'user_subscription');
    wps('wpms')->cache->delete($user_id, 'member');
}, 10, 1);
