<?php
/**
 * @author    sh1zen
 * @copyright Copyright (C) 2023.
 * @license   http://www.gnu.org/licenses/gpl.html GNU/GPL
 */

use WPS\core\Actions;
use WPS\core\Query;
use WPS\core\UtilEnv;

Actions::schedule("wpms-members-check-expired", HOUR_IN_SECONDS, function () {

    $query = Query::getInstance()->select('user_id', WP_MEMBERSHIP_TABLE_SUBSCRIPTIONS);
    $users = $query->where(['expirydate' => wps_time('mysql'), 'compare' => '<'])->query(false, true);

    foreach ($users as $user_id) {

        $user = wps_get_user($user_id);

        wpms_membership_drop($user);
        wpms_user_notify($user, 'expired');

        UtilEnv::safe_time_limit(10, 60);
    }
});

Actions::schedule("wpms-before-expire", MINUTE_IN_SECONDS * 30, function () {

    $query = Query::getInstance(OBJECT, true)->tables(
        ['T0' => WP_MEMBERSHIP_TABLE_COMMUNICATIONS, 'T1' => WP_MEMBERSHIP_TABLE_SUBSCRIPTIONS]
    );
    $query->columns([WP_MEMBERSHIP_TABLE_COMMUNICATIONS => 'id', WP_MEMBERSHIP_TABLE_SUBSCRIPTIONS => 'user_id']);
    $query->where([WP_MEMBERSHIP_TABLE_COMMUNICATIONS => ['active' => '1', 'event' => 'before_expire']]);
    $query->where_unquoted(['T0.timegap' => 'TIMESTAMPDIFF(SECOND, NOW(), T1.expirydate)', 'compare' => '>']);
    $query->limit(10);

    foreach ($query->query() as $match) {
        UtilEnv::safe_time_limit(10, 60);
        wpms_user_notify($match->user_id, $match->id);
    }
});