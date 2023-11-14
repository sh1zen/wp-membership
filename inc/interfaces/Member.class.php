<?php
/**
 * @author    sh1zen
 * @copyright Copyright (C) 2024.
 * @license   http://www.gnu.org/licenses/gpl.html GNU/GPL
 */

namespace WPMembership\core;

use WPS\core\Query;

class Member
{
    private ?\WP_User $user;

    private ?MembershipSubscription $subscription = null;

    private function __construct($user)
    {
        $this->user = $user;
    }

    public static function get($user): ?Member
    {
        $user = wps_get_user($user);

        if (!$user) {
            return null;
        }

        return new self($user);
    }

    public function on_sub($level = false): bool
    {
        if (!$level) {
            return $this->has_subscription();
        }

        return is_numeric($level) ? $this->get_sub()->level_id == $level : $this->get_sub()->get_level()->slug == $level;
    }

    public function has_subscription(): bool
    {
        return $this->get_sub()->is_valid();
    }

    public function get_sub(): ?MembershipSubscription
    {
        if (!$this->subscription) {
            $this->subscription = wpms_user_get_subscription($this->user);
        }
        return $this->subscription;
    }

    public function get_level(): ?MembershipLevel
    {
        return $this->get_sub()->get_level();
    }

    public function get_user(): ?\WP_User
    {
        return $this->user;
    }

    public function renew_count(): int
    {
        return (int)Query::getInstance()->select('COUNT(*)', WP_MEMBERSHIP_TABLE_HISTORY)->where(['user_id' => $this->user->ID, 'action' => 'join'])->query(true) ?: 0;
    }

    public function get_pays($context = ''): float
    {
        $historyQuery = Query::getInstance()->tables(WP_MEMBERSHIP_TABLE_HISTORY)->where(['user_id' => $this->user->ID, 'action' => 'join']);

        if ($context === 'last') {
            $historyQuery->columns('paid')->orderby('id', 'DESC')->limit(1);
        }
        elseif ($context === 'avg') {
            $historyQuery->columns('AVG(paid)');
        }
        elseif ($context === 'min') {
            $historyQuery->columns('MIN(paid)');
        }
        elseif ($context === 'max') {
            $historyQuery->columns('MAX(paid)');
        }
        else {
            $historyQuery->columns('SUM(paid)');
        }

        return (float)$historyQuery->query(true) ?: 0;
    }

    public function is_suspended(): bool
    {
        return $this->get_sub()->is_suspended();
    }

    public function gift_days(): int
    {
        return $this->get_sub()->gift_days();
    }

    public function post_count(): int
    {
        return (int)Query::getInstance()->select(
            'COUNT(*)',
            Query::getInstance()->wpdb()->posts
        )->where(['post_status' => 'publish', 'post_author' => $this->user->ID, 'post_type' => 'post'])->query_one();
    }
}