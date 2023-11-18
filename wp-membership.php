<?php
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

const WPMS_ABSPATH = __DIR__ . '/';
const WPMS_INCPATH = WPMS_ABSPATH . 'inc/';
const WPMS_MODULES = WPMS_ABSPATH . 'modules/';
const WPMS_ADMIN = WPMS_ABSPATH . 'admin/';

const WPMS_SUPPORTERS = WPMS_MODULES . 'supporters/';
const WPMS_VENDORS = WPMS_ABSPATH . 'vendors/';

// setup constants
require_once WPMS_INCPATH . 'constants.php';

// essential
require_once WPMS_INCPATH . 'functions.php';


// wps-framework commons
if (!defined('WPS_FRAMEWORK')) {
    if (!file_exists(WPMS_VENDORS . 'wps-framework/loader.php')) {
        return;
    }
    require_once WPMS_VENDORS . 'wps-framework/loader.php';
}

wps(
    'wpms',
    [
        'modules_path' => WPMS_MODULES,
    ],
    [
        'cache'         => true,
        'cron'          => true,
        'moduleHandler' => true,
    ]
);

// initializer class
require_once WPMS_ADMIN . 'PluginInit.class.php';

/**
 * Initialize the plugin.
 */
WPMembership\core\PluginInit::Initialize();

require_once WPMS_ABSPATH . 'ext_interface/wpms_functions.php';