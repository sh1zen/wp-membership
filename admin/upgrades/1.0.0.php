<?php
/**
 * @author    sh1zen
 * @copyright Copyright (C) 2024.
 * @license   http://www.gnu.org/licenses/gpl.html GNU/GPL
 */

global $wpdb;

WPS\core\UtilEnv::db_create(
    WP_MEMBERSHIP_TABLE_LEVELS,
    [
        "fields"      => [
            "id"          => "BIGINT NOT NULL AUTO_INCREMENT",
            "title"       => "VARCHAR(255)",
            "slug"        => "VARCHAR(255)",
            "description" => "LONGTEXT",
            "duration"    => "INT NOT NULL DEFAULT 0", // in seconds
            "type"        => "VARCHAR(255)",
            "active"      => "INT NOT NULL DEFAULT 0",
        ],
        "primary_key" => "id"
    ],
    true
);

WPS\core\UtilEnv::db_create(
    WP_MEMBERSHIP_TABLE_SUBSCRIPTIONS,
    [
        "fields"      => [
            "id"         => "BIGINT NOT NULL AUTO_INCREMENT",
            "user_id"    => "BIGINT NOT NULL",
            "level_id"   => "BIGINT NOT NULL DEFAULT 0",
            "startdate"  => "DATETIME DEFAULT CURRENT_TIMESTAMP",
            "expirydate" => "DATETIME DEFAULT NULL",
        ],
        "primary_key" => "id"
    ],
    true
);
$wpdb->query("ALTER TABLE " . WP_MEMBERSHIP_TABLE_SUBSCRIPTIONS . " ADD UNIQUE `idx_user_id` (`user_id`) USING BTREE;");

WPS\core\UtilEnv::db_create(
    WP_MEMBERSHIP_TABLE_COMMUNICATIONS,
    [
        "fields"      => [
            "id"       => "BIGINT NOT NULL AUTO_INCREMENT",
            "level_id" => "BIGINT NOT NULL DEFAULT 0", // 0 => for all levels
            "subject"  => "VARCHAR(255)",
            "message"  => "LONGTEXT",
            "timegap"  => "INT NOT NULL DEFAULT 0", // how many seconds must pass before sending the message
            "event"    => "VARCHAR(255) NOT NULL", // before_expire, after_expire, join, signup, leave, drop
            "active"   => "INT NOT NULL DEFAULT 0",
        ],
        "primary_key" => "id"
    ],
    true
);

WPS\core\UtilEnv::db_create(
    WP_MEMBERSHIP_TABLE_COMMUNICATIONS_SENT,
    [
        "fields"      => [
            "id"        => "BIGINT NOT NULL AUTO_INCREMENT",
            "user_id"   => "BIGINT NOT NULL",
            "comm_id"   => "BIGINT NOT NULL",
            "timestamp" => "DATETIME DEFAULT CURRENT_TIMESTAMP"
        ],
        "primary_key" => "id"
    ],
    true
);
$wpdb->query("ALTER TABLE " . WP_MEMBERSHIP_TABLE_COMMUNICATIONS_SENT . " ADD INDEX `idx_user_id` (`user_id`) USING BTREE;");
$wpdb->query("ALTER TABLE " . WP_MEMBERSHIP_TABLE_COMMUNICATIONS_SENT . " ADD INDEX `idx_comm_id` (`comm_id`) USING BTREE;");

WPS\core\UtilEnv::db_create(
    WP_MEMBERSHIP_TABLE_HISTORY,
    [
        "fields"      => [
            "id"        => "BIGINT NOT NULL AUTO_INCREMENT",
            "user_id"   => "BIGINT NOT NULL",
            "level_id"  => "BIGINT NOT NULL DEFAULT 0",
            "action"    => "VARCHAR(255)", // update, join, leave, expire
            "paid"      => "INT NOT NULL DEFAULT 0",
            "timestamp" => "DATETIME DEFAULT CURRENT_TIMESTAMP"
        ],
        "primary_key" => "id"
    ],
    true
);
$wpdb->query("ALTER TABLE " . WP_MEMBERSHIP_TABLE_HISTORY . " ADD INDEX `idx_user_id` (`user_id`) USING BTREE;");
$wpdb->query("ALTER TABLE " . WP_MEMBERSHIP_TABLE_HISTORY . " ADD INDEX `idx_level_id` (`level_id`) USING BTREE;");
$wpdb->query("ALTER TABLE " . WP_MEMBERSHIP_TABLE_HISTORY . " ADD INDEX `idx_action` (`action`) USING BTREE;");
