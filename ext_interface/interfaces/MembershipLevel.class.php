<?php

use WPS\core\UtilEnv;

/**
 * @author    sh1zen
 * @copyright Copyright (C) 2023.
 * @license   http://www.gnu.org/licenses/gpl.html GNU/GPL
 */
class MembershipLevel
{
    public int $id;

    public string $title;

    public string $slug;

    public string $description;

    public int $duration;

    public string $type;

    public bool $active;

    public int $count;

    public function __construct($args)
    {
        $args = array_filter((array)$args);

        $this->id = $args['id'] ?? 0;
        $this->title = $args['title'] ?? '';
        $this->slug = $args['slug'] ?? '';
        $this->description = $args['description'] ?? '';
        $this->duration = $args['duration'] ?? 0;
        $this->type = $args['type'] ?? '';
        $this->active = UtilEnv::to_boolean($args['active'] ?? true);
        $this->count = $args['count'] ?? 0;
    }
}