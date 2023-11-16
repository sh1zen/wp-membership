<?php
/**
 * @author    sh1zen
 * @copyright Copyright (C) 2023.
 * @license   http://www.gnu.org/licenses/gpl.html GNU/GPL
 */

use WPS\core\Query;

require_once __DIR__ . '/interfaces/MembershipLevel.class.php';
require_once __DIR__ . '/interfaces/MembershipSubscription.class.php';

function wpms_get_level($level, $by = 'id'): MembershipLevel
{
    if (is_object($level)) {
        return wpms_get_level($level->id ?? 0, 'id');
    }

    if ($cache = wps('wpms')->cache->get($by . $level, 'level')) {
        return $cache;
    }

    $res = Query::getInstance()->tables(WP_MEMBERSHIP_TABLE_LEVELS)->where([$by => $level])->query(true);

    $level_object = new MembershipLevel($res);

    if ($res) {
        wps('wpms')->cache->set($by . $level, $level_object, 'level', true);
    }

    return $level_object;
}

/**
 * @return MembershipLevel[]
 */
function wpms_get_levels(): array
{
    if ($levels = wps('wpms')->cache->get('all', 'level')) {
        return $levels;
    }

    $levels = Query::getInstance()->tables(WP_MEMBERSHIP_TABLE_LEVELS)->query() ?: [];

    foreach ($levels as $index => $level) {
        $levels[$index] = new MembershipLevel($level);
    }

    wps('wpms')->cache->set('all', $levels, 'level', true);

    return $levels;
}

function wpms_get_user_subscription($user): ?MembershipSubscription
{
    $user = wps_get_user($user);

    if (!$user) {
        return null;
    }

    if ($cache = wps('wpms')->cache->get($user->ID, 'user_subscription')) {
        return $cache;
    }

    $res = Query::getInstance()->tables(WP_MEMBERSHIP_TABLE_SUBSCRIPTIONS)->where(['user_id' => $user->ID])->query(true);

    $sub = new MembershipSubscription($res);

    if ($res) {
        wps('wpms')->cache->set($user->ID, $sub, 'user_subscription', true);
    }

    return $sub;
}

function wpms_update_register($user, $level_id, $action, $paid = 0): bool
{
    if (!$user = wps_get_user($user)) {
        return false;
    }

    $historyQuery = Query::getInstance()->tables(WP_MEMBERSHIP_TABLE_HISTORY);
    $historyQuery->insert(['level_id' => $level_id]);
    $historyQuery->insert(['user_id' => $user->ID]);
    $historyQuery->insert(['action' => $action]);
    $historyQuery->insert(['paid' => $paid]);

    return (bool)$historyQuery->query();
}

function wpms_update_register_update_field($user, $level_id, $field): bool
{
    if (!$user = wps_get_user($user)) {
        return false;
    }

    $historyQuery = Query::getInstance()->tables(WP_MEMBERSHIP_TABLE_HISTORY)->insert($field);
    $historyQuery->orderby('id', 'DESC')->limit(1);
    $historyQuery->where(['level_id' => $level_id, 'user_id' => $user->ID]);

    return (bool)$historyQuery->query();
}

function wpms_get_pays($user, $last = false): int
{
    if (!$user = wps_get_user($user)) {
        return 0;
    }

    $historyQuery = Query::getInstance()->tables(WP_MEMBERSHIP_TABLE_HISTORY)->where(['user_id' => $user->ID, 'action' => 'join']);

    if ($last) {
        $historyQuery->columns('paid')->orderby('id', 'DESC')->limit(1);
    }
    else {
        $historyQuery->columns('SUM(paid)');
    }

    return (int)$historyQuery->query(true) ?: 0;
}

function wpms_update_subscription($user, $level_id, $paid = 0): bool
{
    if (!$user = wps_get_user($user)) {
        return false;
    }

    if (!$level_id) {
        // we are dropping membership
        return (bool)wpms_membership_drop($user);
    }

    // check if new level_id exist
    if (!($level = wpms_get_level($level_id, 'id'))->id) {
        return false;
    }

    $res = true;

    $oldSubscription = wpms_get_user_subscription($user);

    $query = Query::getInstance()->tables(WP_MEMBERSHIP_TABLE_SUBSCRIPTIONS);

    $query->insert(['level_id' => $level_id]);
    $query->insert(['startdate' => wps_time('mysql')]);
    $query->insert(['expirydate' => wps_time('mysql', $level->duration)]);

    $query->begin_transaction();

    if ($oldSubscription->id) {
        $query->where(['user_id' => $user->ID]);
        $res &= wpms_update_register($user, $oldSubscription->level_id, 'leave');
    }
    else {
        $query->insert(['user_id' => $user->ID]);
    }

    if ($oldSubscription->level_id !== $level->id) {
        $res &= $query->query();
        $res &= wpms_update_register($user, $level_id, 'join', $paid);
    }
    else {
        $res &= wpms_update_register_update_field($user, $level_id, ['paid' => $paid]);
    }

    if ($res) {
        $query->commit();
    }
    else {
        $query->rollback();
    }

    wps('wpms')->cache->delete($user->ID, 'user_subscription');

    return (bool)$res;
}

function wpms_renew_subscription($user): bool
{
    if (!$user = wps_get_user($user)) {
        return false;
    }

    $level = wpms_get_user_subscription($user)->get_level();

    if (!$level->id) {
        return false;
    }

    $query = Query::getInstance()->tables(WP_MEMBERSHIP_TABLE_SUBSCRIPTIONS);
    $query->where(['user_id' => $user->ID])->insert(['startdate' => wps_time('mysql')])->insert(['expirydate' => wps_time('mysql', $level->duration)]);

    $query->begin_transaction();

    $res = $query->query();

    $res &= wpms_update_register($user, $level->id, 'leave');
    $res &= wpms_update_register($user, $level->id, 'join', wpms_get_pays($user, true));

    if ($res) {
        $query->commit();
    }
    else {
        $query->rollback();
    }

    wps('wpms')->cache->delete($user->ID, 'user_subscription');

    return (bool)$res;
}

function wpms_membership_drop($user)
{
    $sub = wpms_get_user_subscription($user);

    if (!$sub or !$sub->id) {
        return false;
    }

    $user = wps_get_user($user);

    $res = Query::getInstance()->delete(['user_id' => $user->ID], WP_MEMBERSHIP_TABLE_SUBSCRIPTIONS)->query();

    if ($res) {
        wpms_update_register($user, $sub->level_id, 'leave');
    }

    wps('wpms')->cache->delete($user->ID, 'user_subscription');

    return $res ? $sub->level_id : false;
}

function wpms_send_message($user, $comm_id)
{
    $user = wps_get_user($user);

    if (!$user) {
        return false;
    }

    $comm = Query::getInstance()->tables(WP_MEMBERSHIP_TABLE_COMMUNICATIONS)->where(['id' => $comm_id])->query(true);

    if (!$comm) {
        return false;
    }

    // todo add text replacer tags
    return wp_mail($user->user_email, $comm->subject, $comm->message);
}