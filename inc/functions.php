<?php
/**
 * @author    sh1zen
 * @copyright Copyright (C) 2025.
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


function wpmc_get_level($level, $by = 'id'): MembershipLevel
{
    if (is_object($level)) {
        return wpmc_get_level($level->id ?? 0, 'id');
    }

    if ($cache = wps('wpmc')->cache->get($by . $level, 'level')) {
        return $cache;
    }

    $res = Query::getInstance()->tables(WP_MEMBERSHIP_TABLE_LEVELS)->where([$by => $level])->query_one();

    $level_object = new MembershipLevel($res);

    if ($res) {
        wps('wpmc')->cache->set($by . $level, $level_object, 'level', true);
    }

    return $level_object;
}

/**
 * @return MembershipLevel[]
 */
function wpmc_get_levels(): array
{
    if ($levels = wps('wpmc')->cache->get('all', 'level')) {
        return $levels;
    }

    $levels = Query::getInstance()->tables(WP_MEMBERSHIP_TABLE_LEVELS)->query() ?: [];

    foreach ($levels as $index => $level) {
        $levels[$index] = new MembershipLevel($level);
    }

    wps('wpmc')->cache->set('all', $levels, 'level', true);

    return $levels;
}

function wpmc_stats_count_possible_members($wp_roles): int
{
    $tot = 0;

    foreach ($wp_roles as $role) {
        $tot += count_users()['avail_roles'][$role];
    }

    return $tot;
}

function wpmc_stats_count_members(): int
{
    if ($subscribed = wps('wpmc')->cache->get('stats_count', 'members')) {
        return $subscribed;
    }

    $subscribed = Query::getInstance()->tables(WP_MEMBERSHIP_TABLE_SUBSCRIPTIONS)->select('COUNT(*)')->query_one() ?: 0;

    wps('wpmc')->cache->set('stats_count', $subscribed, 'members', true);

    return $subscribed;
}

function wpmc_subscription_get_users($level_id = 0): array
{
    global $wpdb;

    if ($user_ids = wps('wpmc')->cache->get($level_id, 'level-users')) {
        return $user_ids;
    }

    if ($level_id) {
        $query = Query::getInstance()->select('DISTINCT user_id', WP_MEMBERSHIP_TABLE_SUBSCRIPTIONS);
        $query->where(['level_id' => $level_id]);
    }
    else {
        $query = Query::getInstance()->select('DISTINCT ID', $wpdb->users);
        $query->where_unquoted(['ID' => Query::getInstance()->select('DISTINCT user_id', WP_MEMBERSHIP_TABLE_SUBSCRIPTIONS)->compile(), 'compare' => 'NOT IN']);
    }

    $user_ids = $query->query_multi();

    wps('wpmc')->cache->set($level_id, $user_ids, 'level-users', true);

    return $user_ids;
}

function wpmc_user_get_subscription($user): ?MembershipSubscription
{
    if (!$user = wps_get_user($user)) {
        return null;
    }

    if ($cache = wps('wpmc')->cache->get($user->ID, 'user_subscription')) {
        return $cache;
    }

    $res = Query::getInstance()->tables(WP_MEMBERSHIP_TABLE_SUBSCRIPTIONS)->where(['user_id' => $user->ID])->query_one();

    $sub = new MembershipSubscription($res);

    if ($res) {
        wps('wpmc')->cache->set($user->ID, $sub, 'user_subscription', true);
    }

    return $sub;
}

function wpmc_register_update($user, $level_id, $action, $paid = 0): bool
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

function wpmc_register_update_field($user, $level_id, $field): bool
{
    if (!$user = wps_get_user($user)) {
        return false;
    }

    $historyQuery = Query::getInstance()->tables(WP_MEMBERSHIP_TABLE_HISTORY)->insert($field);
    $historyQuery->orderby('id', 'DESC')->limit(1);
    $historyQuery->where(['level_id' => $level_id, 'user_id' => $user->ID]);

    return (bool)$historyQuery->query();
}

function wpmc_get_member($member): ?Member
{
    if ($member instanceof Member) {
        return $member;
    }

    if (!($user = wps_get_user($member))) {
        return null;
    }

    if ($cache = wps('wpmc')->cache->get($user->ID, 'member')) {
        return $cache;
    }

    $member = Member::get($user);

    wps('wpmc')->cache->set($user->ID, $member, 'member', true);

    return $member;
}

function wpmc_membership_update($user, $level_id, $paid = 0): bool
{
    // check if new level_id exist
    if (!($level = wpmc_get_level($level_id, 'id'))->id) {
        return false;
    }

    if (!$member = wpmc_get_member($user)) {
        return false;
    }

    $res = true;

    $updating = (bool)$member->get_sub()->id;

    $query = Query::getInstance()->tables(WP_MEMBERSHIP_TABLE_SUBSCRIPTIONS);

    $query->insert(['level_id' => $level_id]);
    $query->insert(['startdate' => wps_time('mysql')]);
    $query->insert(['expirydate' => wps_time('mysql', $level->duration)]);

    if ($updating) {
        $query->where(['user_id' => $member->get_user()->ID]);
    }
    else {
        $query->insert(['user_id' => $member->get_user()->ID]);
    }

    $query->begin_transaction();

    if ($member->get_sub()->level_id !== $level->id) {
        $res &= $query->query();

        if ($updating) {
            $res &= wpmc_register_update($member->get_user(), $member->get_sub()->level_id, 'leave');
        }

        $res &= wpmc_register_update($member->get_user(), $level_id, 'join', $paid);
    }
    else {
        $res &= wpmc_register_update_field($member->get_user(), $level_id, ['paid' => $paid]);
    }

    if ($res) {

        if ($member->get_sub()->level_id !== $level->id) {
            // we are changing a subscription level
            wpmc_user_notify($member->get_user(), 'join');
        }

        $query->commit();
    }
    else {
        $query->rollback();
    }

    $res = (bool)$res;

    if ($res) {
        do_action('wpmc_reset_membership', $member->get_user()->ID, 'update');
    }

    return $res;
}

function wpmc_membership_extend($user, $days = 0): bool
{
    $member = wpmc_get_member($user);

    if (!$member or !$member->has_subscription()) {
        return false;
    }

    $days = absint($days);

    $query = Query::getInstance()->tables(WP_MEMBERSHIP_TABLE_SUBSCRIPTIONS);
    $query->insert(['expirydate' => $member->get_sub()->expirydate($days * DAY_IN_SECONDS)]);
    $query->where(['user_id' => $member->get_user()->ID]);

    $res = (bool)$query->query();

    // empty caches
    wps('wpmc')->cache->delete($member->get_user()->ID, 'user_subscription');
    wps('wpmc')->cache->delete($member->get_user()->ID, 'member');

    if ($res) {
        do_action('wpmc_reset_membership', $member->get_user()->ID, 'extend');
    }

    return $res;
}

function wpmc_membership_suspend($user): bool
{
    $member = wpmc_get_member($user);

    if (!$member or !$member->has_subscription()) {
        return false;
    }

    $query = Query::getInstance()->tables(WP_MEMBERSHIP_TABLE_SUBSCRIPTIONS);

    if ($member->is_suspended()) {
        $query->insert(['expirydate' => wps_time('mysql', $member->get_level()->duration, true, $member->get_sub()->start_time())]);
    }
    else {
        $query->insert(['expirydate' => 'NULL'], false);
    }

    $query->where(['user_id' => $member->get_user()->ID]);

    $res = (bool)$query->query();

    if ($res) {
        do_action('wpmc_reset_membership', $member->get_user()->ID, 'suspend');
    }

    return $res;
}

/**
 * context drop | expired
 */
function wpmc_membership_drop($user, $context = 'drop'): int
{
    $member = wpmc_get_member($user);

    if (!$member or !$member->has_subscription()) {
        return 0;
    }

    $query = Query::getInstance()->begin_transaction();

    $res = $query->delete(['user_id' => $member->get_user()->ID], WP_MEMBERSHIP_TABLE_SUBSCRIPTIONS)->query();

    if ($res) {
        $res &= wpmc_register_update($member->get_user(), $member->get_sub()->level_id, 'leave');
    }

    if ($res) {
        wpmc_user_notify($member->get_user(), $context, true);
        $query->commit();
    }
    else {
        $query->rollback();
    }

    if ($res) {
        do_action('wpmc_reset_membership', $member->get_user()->ID, 'drop');
    }

    return $res ? $member->get_sub()->level_id : 0;
}

function wpmc_drop_expired_memberships(): void
{
    $query = Query::getInstance()->select('user_id', WP_MEMBERSHIP_TABLE_SUBSCRIPTIONS);
    $query->where_unquoted(['expirydate' => 'NULL', 'compare' => 'IS NOT']);
    $users = $query->where(['expirydate' => wps_time('mysql'), 'compare' => '<'])->query(false, true);

    foreach ($users as $user_id) {
        wpmc_membership_drop($user_id, 'expired');
    }
}

function wpmc_notify_expiring_members(): void
{
    $query = Query::getInstance(OBJECT, true)->tables([
        'T0' => WP_MEMBERSHIP_TABLE_COMMUNICATIONS,
        'T1' => WP_MEMBERSHIP_TABLE_SUBSCRIPTIONS,
    ]);
    $query->columns([WP_MEMBERSHIP_TABLE_COMMUNICATIONS => 'id', WP_MEMBERSHIP_TABLE_SUBSCRIPTIONS => 'user_id']);
    $query->where([WP_MEMBERSHIP_TABLE_COMMUNICATIONS => ['active' => '1', 'event' => 'before_expire']]);
    $query->where_unquoted([WP_MEMBERSHIP_TABLE_SUBSCRIPTIONS => ['expirydate' => 'NULL', 'compare' => 'IS NOT']]);
    $query->where_unquoted(['T0.timegap' => 'TIMESTAMPDIFF(SECOND, NOW(), T1.expirydate)', 'compare' => '>']);

    // exclude communications sent
    $query->where_unquoted([
        'T0.id'   => Query::getInstance()->select('comm_id', WP_MEMBERSHIP_TABLE_COMMUNICATIONS_SENT)->where_unquoted(['user_id' => 'T1.user_id'])->compile(),
        'compare' => 'NOT IN'
    ]);

    $query->limit(50);

    foreach ($query->query() as $match) {
        wpmc_user_notify($match->user_id, $match->id);
        usleep(5000);
    }
}

function wpmc_delete_member($user_id, $history = true): void
{
    if ($history) {
        Query::getInstance()->delete(['user_id' => $user_id], WP_MEMBERSHIP_TABLE_HISTORY)->query();
    }

    Query::getInstance()->delete(['user_id' => $user_id], WP_MEMBERSHIP_TABLE_SUBSCRIPTIONS)->query();
    Query::getInstance()->delete(['user_id' => $user_id], WP_MEMBERSHIP_TABLE_COMMUNICATIONS_SENT)->query();

    do_action('wpmc_reset_membership', $user_id, 'delete');
}

/**
 * $context = leave/expired/join/signup
 */
function wpmc_user_notify($user, $context, $clear_history = false): bool
{
    $member = wpmc_get_member($user);

    if (!$member) {
        return false;
    }

    if ($clear_history) {
        Query::getInstance()->delete(['user_id' => $member->get_user()->ID], WP_MEMBERSHIP_TABLE_COMMUNICATIONS_SENT)->query();
    }

    $query = Query::getInstance()->tables(WP_MEMBERSHIP_TABLE_COMMUNICATIONS);

    if (is_numeric($context)) {
        $communications = $query->where(['id' => $context])->query();
    }
    else {

        $query->where(['active' => '1'])->where([['level_id' => $member->get_sub()->level_id], ['level_id' => 0]], 'OR');

        switch ($context) {
            case 'before_expire':
                $query->where(['event' => 'before_expire'])->where(['timegap' => $member->get_sub()->time_left(), 'compare' => '<']);
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

    TextReplacer::add_replacer('membership_level', $member->get_level()->title);

    foreach ($communications as $comm) {

        $headers = [];

        UtilEnv::safe_time_limit(20, 120);

        if (UtilEnv::to_boolean($comm->forward_admin)) {
            $headers[] = 'Bcc: ' . apply_filters('wpmc_admin_forward_mail', get_option('admin_email'));
        }

        $message_with_br = preg_replace("/(\r\n|\n|\r)/", "<br>", $comm->message);

        $res = wp_mail(
            $member->get_user()->user_email,
            TextReplacer::replace($comm->subject, $member->get_user()),
            TextReplacer::replace($message_with_br, $member->get_user()),
            $headers
        );

        if ($res) {
            Query::getInstance()->tables(WP_MEMBERSHIP_TABLE_COMMUNICATIONS_SENT)->insert(['user_id' => $member->get_user()->ID, 'comm_id' => $comm->id])->query();
        }
    }

    return true;
}
