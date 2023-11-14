<?php
/**
 * @author    sh1zen
 * @copyright Copyright (C) 2024.
 * @license   http://www.gnu.org/licenses/gpl.html GNU/GPL
 */

namespace WPMembership\core;

use WPS\core\Query;
use WPS\core\UtilEnv;

class MembershipLevel
{
    public int $id;

    public string $title;

    public string $slug;

    public string $description;

    public int $duration;

    public string $type;

    public bool $active;

    public function __construct($args)
    {
        $args = array_filter((array)$args);

        $this->id = $args['id'] ?? 0;
        $this->title = $args['title'] ?? '';
        $this->slug = $args['slug'] ?? '';
        $this->description = $args['description'] ?? '';
        $this->duration = $args['duration'] ?? 0;
        $this->type = $args['type'] ?? '';
        $this->active = UtilEnv::to_boolean($args['active'] ?? false);
    }

    public function durationDays(): int
    {
        return (int)$this->duration / DAY_IN_SECONDS;
    }

    public function count(): int
    {
        return (int) Query::getInstance()->select('COUNT(*)', WP_MEMBERSHIP_TABLE_SUBSCRIPTIONS)->where(['level_id' => $this->id])->query_one() ?: 0;
    }
}