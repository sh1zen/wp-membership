<?php
/**
 * @author    sh1zen
 * @copyright Copyright (C) 2024.
 * @license   http://www.gnu.org/licenses/gpl.html GNU/GPL
 */

namespace WPMembership\core;

use WPS\core\Query;

class MembershipSubscription
{
    public int $id;

    public int $user_id;

    public int $level_id;

    public string $startdate;

    public string $expirydate;

    private ?MembershipLevel $level = null;

    public function __construct($args)
    {
        $args = array_filter((array)$args);

        $this->id = absint($args['id'] ?? 0);
        $this->user_id = absint($args['user_id'] ?? 0);
        $this->level_id = absint($args['level_id'] ?? 0);
        $this->startdate = $args['startdate'] ?? '';
        $this->expirydate = $args['expirydate'] ?? '';
    }

    public function time_left()
    {
        return max($this->end_time() - time(), 0);
    }

    public function end_time(): int
    {
        return (int)strtotime($this->expirydate) ?: 0;
    }

    public function gift_days(): int
    {
        if (!$this->is_valid()) {
            return 0;
        }

        $query = Query::getInstance()->select('DATEDIFF(expirydate, startdate) AS date_difference', WP_MEMBERSHIP_TABLE_SUBSCRIPTIONS);

        $total_days = (int)$query->where(['user_id' => $this->user_id])->query_one();

        return $total_days - wpms_get_level($this->level_id)->durationDays();
    }

    public function is_valid(): bool
    {
        return $this->id != 0;
    }

    public function expirydate($offset = false)
    {
        if (!$this->is_valid()) {
            return '0000-00-00 00:00:00';
        }

        if ($offset === false) {
            return $this->expirydate;
        }

        return wp_date('Y-m-d H:i:s', $this->start_time() + $this->get_level()->duration + $offset);
    }

    public function start_time(): int
    {
        return (int)strtotime($this->startdate) ?: 0;
    }

    public function get_level(): ?MembershipLevel
    {
        if (!$this->level) {
            $this->level = wpms_get_level($this->level_id);
        }

        return $this->level;
    }

    public function is_suspended(): bool
    {
        return ($this->is_valid() and empty($this->expirydate));
    }
}