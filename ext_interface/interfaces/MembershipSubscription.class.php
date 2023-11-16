<?php
/**
 * @author    sh1zen
 * @copyright Copyright (C) 2023.
 * @license   http://www.gnu.org/licenses/gpl.html GNU/GPL
 */

class MembershipSubscription
{
    public int $id;

    public int $user_id;

    public int $level_id;

    public int $startdate;

    public int $expirydate;

    public function __construct($args)
    {
        $args = array_filter((array)$args);

        $this->id = $args['id'] ?? 0;
        $this->user_id = $args['user_id'] ?? 0;
        $this->level_id = $args['level_id'] ?? 0;
        $this->startdate = isset($args['startdate']) ? strtotime($args['startdate']) : 0;
        $this->expirydate = isset($args['expirydate']) ? strtotime($args['expirydate']) : 0;
    }

    public function get_level(): ?MembershipLevel
    {
        return wpms_get_level($this->level_id);
    }

    public function get_user(): ?WP_User
    {
        return wps_get_user($this->user_id);
    }
}