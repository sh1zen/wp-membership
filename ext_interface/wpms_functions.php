<?php
/**
 * @author    sh1zen
 * @copyright Copyright (C) 2023.
 * @license   http://www.gnu.org/licenses/gpl.html GNU/GPL
 */

use WPS\core\Query;

function wpms_get_level($level, $by = 'id')
{
    if (is_object($level)) {
        return wpms_get_level($level->id ?? 0, 'id');
    }

    $cache = wps('wpms')->cache->get($by . $level, 'level');

    if ($cache) {
        return $cache;
    }

    $cache = Query::getInstance()->tables(WP_MEMBERSHIP_TABLE_LEVELS)->where([$by => $level])->query(true);

    wps('wpms')->cache->set($by . $level, $cache, 'level', true);

    return $cache;
}

function wpms_get_levels()
{
    $cache = wps('wpms')->cache->get('all', 'level');

    if ($cache) {
        return $cache;
    }

    $cache = Query::getInstance()->tables(WP_MEMBERSHIP_TABLE_LEVELS)->query();

    wps('wpms')->cache->set('all', $cache, 'level', true);

    return $cache;
}

function wpms_get_user_subscription($user)
{
    $user = wps_get_user($user);

    if (!$user) {
        return false;
    }

    $cache = wps('wpms')->cache->get($user->ID, 'user_subscription');

    if ($cache) {
        return $cache;
    }

    $cache = Query::getInstance()->tables(WP_MEMBERSHIP_TABLE_SUBSCRIPTIONS)->where(['user_id' => $user->ID])->query(true);

    wps('wpms')->cache->set($user->ID, $cache, 'user_subscription', true);

    return $cache;
}

function wpms_update_register($user, $level, $action)
{
    $user = wps_get_user($user);
    $level = wpms_get_level($level, 'id');

    $historyQuery = Query::getInstance()->tables(WP_MEMBERSHIP_TABLE_HISTORY);
    $historyQuery->insert(['level_id' => $level->id]);
    $historyQuery->insert(['user_id' => $user->ID]);
    $historyQuery->insert(['action' => $action]);
    $historyQuery->query();
}

function wpms_add_subscription($user, $level)
{
    $user = wps_get_user($user);
    $level = wpms_get_level($level, 'id');

    $query = Query::getInstance()->tables(WP_MEMBERSHIP_TABLE_SUBSCRIPTIONS);
    $query->insert(['level_id' => $level->id]);
    $query->insert(['startdate' => wps_time('mysql')]);
    $query->insert(['expirydate' => wps_time('mysql', $level->duration)]);

    if (wpms_get_user_subscription($user)) {
        $query->where(['user_id' => $user->ID]);
    }
    else {
        $query->insert(['user_id' => $user->ID]);
    }

    wpms_update_register($user, $level, 'join');

    wps('wpms')->cache->delete($user->ID, 'user_subscription');

    return $query->query();
}

function wpms_memberhsip_drop($user)
{
    $user = wps_get_user($user);
    $sub = wpms_get_user_subscription($user);

    if (!$sub) {
        return false;
    }

    $query = Query::getInstance()->tables(WP_MEMBERSHIP_TABLE_SUBSCRIPTIONS);
    $query->insert(['level_id' => 0]);
    $query->insert(['startdate' => wps_time('zero')]);
    $query->insert(['expirydate' => wps_time('zero')]);
    $query->where(['user_id' => $user->ID]);

    wpms_update_register($user, $sub->level_id, 'leave');

    wps('wpms')->cache->delete($user->ID, 'user_subscription');

    return $query->query();
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