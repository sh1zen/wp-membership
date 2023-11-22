<?php
/**
 * @author    sh1zen
 * @copyright Copyright (C) 2023.
 * @license   http://www.gnu.org/licenses/gpl.html GNU/GPL
 */

use WPMembership\core\Member;
use WPMembership\core\MembershipLevel;
use WPMembership\core\MembershipSubscription;
use WPS\core\Query;
use WPS\core\TextReplacer;
use WPS\core\UtilEnv;

require_once __DIR__ . '/interfaces/Member.class.php';
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

    $res = Query::getInstance()->tables(WP_MEMBERSHIP_TABLE_LEVELS)->where([$by => $level])->query_one();

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

function wpms_subscription_get_users($level_id = 0): array
{
    global $wpdb;

    if ($user_ids = wps('wpms')->cache->get($level_id, 'level-users')) {
        return $user_ids;
    }

    if ($level_id) {
        $query = Query::getInstance()->select('DISTINCT user_id', WP_MEMBERSHIP_TABLE_SUBSCRIPTIONS);
        $query->where(['level_id' => $level_id]);
    }
    else {
        $query = Query::getInstance()->select('DISTINCT ID', $wpdb->users);
        $query->where(['ID' => Query::getInstance()->select('DISTINCT user_id', WP_MEMBERSHIP_TABLE_SUBSCRIPTIONS)->compile(), 'compare' => 'NOT IN']);
    }

    $user_ids = $query->query_multi();

    wps('wpms')->cache->set($level_id, $user_ids, 'level-users', true);

    return $user_ids;
}

function wpms_user_get_subscription($user): ?MembershipSubscription
{
    if (!$user = wps_get_user($user)) {
        return null;
    }

    if ($cache = wps('wpms')->cache->get($user->ID, 'user_subscription')) {
        return $cache;
    }

    $res = Query::getInstance()->tables(WP_MEMBERSHIP_TABLE_SUBSCRIPTIONS)->where(['user_id' => $user->ID])->query_one();

    $sub = new MembershipSubscription($res);

    if ($res) {
        wps('wpms')->cache->set($user->ID, $sub, 'user_subscription', true);
    }

    return $sub;
}

function wpms_register_update($user, $level_id, $action, $paid = 0): bool
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

function wpms_register_update_field($user, $level_id, $field): bool
{
    if (!$user = wps_get_user($user)) {
        return false;
    }

    $historyQuery = Query::getInstance()->tables(WP_MEMBERSHIP_TABLE_HISTORY)->insert($field);
    $historyQuery->orderby('id', 'DESC')->limit(1);
    $historyQuery->where(['level_id' => $level_id, 'user_id' => $user->ID]);

    return (bool)$historyQuery->query();
}

function wpms_get_member($member): ?Member
{
    if($member instanceof Member){
        return $member;
    }

    if (!$user = wps_get_user($member)) {
        return null;
    }

    if ($cache = wps('wpms')->cache->get($user->ID, 'member')) {
        return $cache;
    }

    $member = Member::get($user);

    wps('wpms')->cache->set($user->ID, $member, 'member', true);

    return $member;
}

function wpms_membership_update($user, $level_id, $paid = 0): bool
{
    if (!$level_id) {
        // we are dropping membership
        return (bool)wpms_membership_drop($user);
    }

    // check if new level_id exist
    if (!($level = wpms_get_level($level_id, 'id'))->id) {
        return false;
    }

    $res = true;

    $member = wpms_get_member($user);

    if (!$member) {
        return false;
    }

    $oldSubscription = $member->get_sub();

    $query = Query::getInstance()->tables(WP_MEMBERSHIP_TABLE_SUBSCRIPTIONS);

    $query->insert(['level_id' => $level_id]);
    $query->insert(['startdate' => wps_time('mysql')]);
    $query->insert(['expirydate' => wps_time('mysql', $level->duration)]);

    if ($oldSubscription->id) {
        $query->where(['user_id' => $member->get_user()->ID]);
    }
    else {
        $query->insert(['user_id' => $user->ID]);
    }

    $query->begin_transaction();

    if ($oldSubscription->level_id !== $level->id) {
        $res &= $query->query();

        if ($oldSubscription->id) {
            $res &= wpms_register_update($member->get_user(), $oldSubscription->level_id, 'leave');
        }

        $res &= wpms_register_update($member->get_user(), $level_id, 'join', $paid);
    }
    else {
        $res &= wpms_register_update_field($member->get_user(), $level_id, ['paid' => $paid]);
    }

    if ($res) {
        $query->commit();
    }
    else {
        $query->rollback();
    }

    wps('wpms')->cache->delete($member->get_user()->ID, 'user_subscription');
    wps('wpms')->cache->delete($member->get_user()->ID, 'member');

    return (bool)$res;
}

function wpms_membership_renew($user): bool
{
    $member = wpms_get_member($user);

    if (!$member or !$member->get_sub()->is_valid()) {
        return false;
    }

    $level = $member->get_sub()->get_level();

    $query = Query::getInstance()->tables(WP_MEMBERSHIP_TABLE_SUBSCRIPTIONS);
    $query->where(['user_id' => $user->ID])->insert(['startdate' => wps_time('mysql')])->insert(['expirydate' => wps_time('mysql', $level->duration)]);

    $query->begin_transaction();

    $res = $query->query();

    $res &= wpms_register_update($user, $level->id, 'leave');
    $res &= wpms_register_update($user, $level->id, 'join', $member->get_pays('last'));

    if ($res) {
        $query->commit();
    }
    else {
        $query->rollback();
    }

    wps('wpms')->cache->delete($user->ID, 'user_subscription');
    wps('wpms')->cache->delete($user->ID, 'member');

    return (bool)$res;
}

function wpms_membership_drop($user)
{
    $sub = wpms_user_get_subscription($user);

    if (!$sub or !$sub->id) {
        return false;
    }

    $user = wps_get_user($user);

    $query = Query::getInstance()->begin_transaction();

    $res = $query->delete(['user_id' => $user->ID], WP_MEMBERSHIP_TABLE_SUBSCRIPTIONS)->query();

    if ($res) {
        $res &= wpms_register_update($user, $sub->level_id, 'leave');
    }

    if ($res) {
        $query->commit();
    }
    else {
        $query->rollback();
    }

    wps('wpms')->cache->delete($user->ID, 'user_subscription');
    wps('wpms')->cache->delete($user->ID, 'member');

    return $res ? $sub->level_id : false;
}

/**
 * $context = leave/expired/join/signup
 */
function wpms_user_notify($user, $context): bool
{
    if (!$user = wps_get_user($user)) {
        return false;
    }

    $query = Query::getInstance()->tables(WP_MEMBERSHIP_TABLE_COMMUNICATIONS);

    if (is_numeric($context)) {
        $communications = $query->where(['id' => $context])->query();
    }
    else {

        $subscription = wpms_user_get_subscription($user);

        $query->where(['active' => '1'])->where([['level_id' => $subscription->level_id], ['level_id' => 0]], 'OR');

        switch ($context) {
            case 'before_expire':
                $query->where(['event' => 'before_expire'])->where(['timegap' => $subscription->time_left(), 'compare' => '<']);
                break;

            case 'expired':
            case 'after_expire':
                $query->where(['event' => 'after_expire']);
                break;

            case 'leave':
            case 'drop':
            case 'join':
            case 'signup':
                $query->where(['event' => $context]);
                break;

            default:
                return false;
        }

        $communications = $query->query();
    }

    $res = true;

    foreach ($communications as $comm) {
        UtilEnv::safe_time_limit(20, 120);
        $res &= wp_mail($user->user_email, TextReplacer::replace($comm->subject), TextReplacer::replace($comm->message));
    }

    return (bool)$res;
}
