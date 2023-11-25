<?php
/**
 * @author    sh1zen
 * @copyright Copyright (C) 2023.
 * @license   http://www.gnu.org/licenses/gpl.html GNU/GPL
 */

use WPS\core\CronActions;

CronActions::schedule("WPMembership-before-expire", HOUR_IN_SECONDS * 3, 'wpms_notify_expiring_members', '08:00');

CronActions::schedule("WPMembership-check-expired", HOUR_IN_SECONDS * 3, 'wpms_drop_expired_memberships', '09:00');

