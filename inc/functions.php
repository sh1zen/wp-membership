<?php
/**
 * @author    sh1zen
 * @copyright Copyright (C) 2023.
 * @license   http://www.gnu.org/licenses/gpl.html GNU/GPL
 */

use WPMembership\core\PluginInit;

function wpms(): PluginInit
{
    return PluginInit::getInstance();
}