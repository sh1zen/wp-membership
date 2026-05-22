<?php
/**
 * Plugin Name: Members Control
 * Plugin URI: https://github.com/sh1zen/members-control/
 * Description: The ultimate WordPress solution to manage, protect, and grow your members with powerful levels, access control, and developer-ready tools.
 * Author: sh1zen
 * Author URI: https://sh1zen.github.io/
 * Text Domain: members-control
 * Domain Path: /languages
 * License: GPLv2 or later
 * Version: 1.0.1
 */

const WPMC_VERSION = '1.0.3';
const WPMC_FILE = __FILE__;

// load workers
require_once __DIR__ . '/inc/wps_and_constants.php';
require_once WPMC_INCPATH . 'functions.php';
require_once WPMC_INCPATH . 'actions.php';

// initializer class
require_once WPMC_ADMIN . 'PluginInit.class.php';

/**
 * Initialize the plugin.
 */
WPMembership\core\PluginInit::Initialize();
