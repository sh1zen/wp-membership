<?php
/**
 * @author    sh1zen
 * @copyright Copyright (C) 2024.
 * @license   http://www.gnu.org/licenses/gpl.html GNU/GPL
 */

/**
 * Plugin Name: WP Membership
 * Plugin URI: https://github.com/sh1zen/wp-membership
 * Description: WordPress Membership for developers
 * Author: sh1zen
 * Author URI: https://sh1zen.github.io/
 * Text Domain: wpms
 * Domain Path: /languages
 * Version: 1.0.0
 */

const WPMS_VERSION = '1.0.0';
const WPMS_FILE = __FILE__;

// load workers
require_once __DIR__ . '/inc/wps_and_constants.php';
require_once WPMS_INCPATH . 'functions.php';
require_once WPMS_INCPATH . 'actions.php';

// initializer class
require_once WPMS_ADMIN . 'PluginInit.class.php';

/**
 * Initialize the plugin.
 */
WPMembership\core\PluginInit::Initialize();
