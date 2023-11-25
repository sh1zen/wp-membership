<?php
/**
 * @author    sh1zen
 * @copyright Copyright (C) 2023.
 * @license   http://www.gnu.org/licenses/gpl.html GNU/GPL
 */

namespace WPMembership\core;

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

    public function get_level(): ?MembershipLevel
    {
        if (!$this->level) {
            $this->level = wpms_get_level($this->level_id);
        }

        return $this->level;
    }

    public function time_left()
    {
        return max($this->end_time() - time(), 0);
    }

    public function is_valid(): bool
    {
        return $this->id != 0;
    }

    public function start_time(): int
    {
        return (int)strtotime($this->startdate) ?: 0;
    }

    public function end_time(): int
    {
        return (int)strtotime($this->expirydate) ?: 0;
    }
}