<?php
/**
 * @author    sh1zen
 * @copyright Copyright (C) 2024.
 * @license   http://www.gnu.org/licenses/gpl.html GNU/GPL
 */

namespace WPMembership\core;

use WPS\core\UtilEnv;

/**
 * Main class, used to set up the plugin
 */
class PluginInit
{
    private static ?PluginInit $_instance;

    /**
     * Holds the plugin base name
     */
    public string $plugin_basename;

    /**
     * Holds the plugin base url
     */
    public string $plugin_base_url;

    public ?PagesHandler $pages_handler;

    private function __construct()
    {
        $this->plugin_basename = UtilEnv::plugin_basename(WPMS_FILE);
        $this->plugin_base_url = UtilEnv::path_to_url(WPMS_ABSPATH);

        if (is_admin()) {

            $this->register_actions();
        }

        $this->load_textdomain();

        wps_maybe_upgrade('wpms', WPMS_VERSION, WPMS_ADMIN . "upgrades/");
    }

    private function register_actions()
    {
        // Plugin Activation/Deactivation.
        register_activation_hook(WPMS_FILE, array($this, 'plugin_activation'));
        register_deactivation_hook(WPMS_FILE, array($this, 'plugin_deactivation'));

        add_filter("plugin_action_links_$this->plugin_basename", array($this, 'extra_plugin_link'), 10, 2);
        add_filter('plugin_row_meta', array($this, 'donate_link'), 10, 4);
    }

    /**
     * Loads text domain for the plugin.
     */
    private function load_textdomain(): void
    {
        $locale = apply_filters('wpms_plugin_locale', get_locale(), 'wpms');

        $mo_file = "wpms-$locale.mo";

        if (load_textdomain('wpms', WP_LANG_DIR . '/plugins/wp-membership/' . $mo_file)) {
            return;
        }

        load_textdomain('wpms', UtilEnv::normalize_path(WPMS_ABSPATH . 'languages/', true) . $mo_file);
    }

    public static function getInstance(): PluginInit
    {
        if (!isset(self::$_instance)) {
            return self::Initialize();
        }

        return self::$_instance;
    }

    public static function Initialize(): PluginInit
    {
        self::$_instance = new self();

        /**
         * Keep Ajax requests fast:
         * if doing ajax : load only ajax handler and return
         */
        if (wp_doing_ajax()) {

            /**
             * Instancing all modules that need to interact in the Ajax process
             */
            wps('wpms')->moduleHandler->setup_modules('ajax');
        }
        elseif (wp_doing_cron()) {

            /**
             * Instancing all modules that need to interact in the cron process
             */
            wps('wpms')->moduleHandler->setup_modules('cron');
        }
        elseif (is_admin()) {

            require_once WPMS_ADMIN . 'PagesHandler.class.php';

            /**
             * Load the admin pages handler and store it here
             */
            self::$_instance->pages_handler = new PagesHandler();

            /**
             * Instancing all modules that need to interact in admin area
             */
            wps('wpms')->moduleHandler->setup_modules('admin');
        }
        else {

            /**
             * Instancing all modules that need to interact only on the web-view
             */
            wps('wpms')->moduleHandler->setup_modules('web-view');
        }

        /**
         * Instancing all modules that need to be always loaded
         */
        wps('wpms')->moduleHandler->setup_modules('autoload');

        return self::$_instance;
    }

    /**
     * What to do when the plugin on plugin activation
     *
     * @param boolean $network_wide Is network wide.
     * @return void
     */
    public function plugin_activation($network_wide)
    {
        if (is_multisite() and $network_wide) {
            $ms_sites = (array)get_sites();

            foreach ($ms_sites as $ms_site) {
                switch_to_blog($ms_site->blog_id);
                $this->activate();
                restore_current_blog();
            }
        }
        else {
            $this->activate();
        }
    }

    private function activate()
    {
        wps('wpms')->settings->activate();

        /**
         * Hook for the plugin activation
         */
        do_action('wpms-activate');
    }

    /**
     * What to do when the plugin on plugin deactivation
     *
     * @param boolean $network_wide Is network wide.
     * @return void
     */
    public function plugin_deactivation($network_wide)
    {
        if (is_multisite() and $network_wide) {
            $ms_sites = (array)get_sites();

            foreach ($ms_sites as $ms_site) {
                switch_to_blog($ms_site->blog_id);
                $this->deactivate();
                restore_current_blog();
            }
        }
        else {
            $this->deactivate();
        }
    }

    private function deactivate()
    {
        /**
         * Hook for the plugin deactivation
         */
        do_action('wpms-deactivate');
    }

    /**
     * Add donate link to plugin description in /wp-admin/plugins.php
     *
     * @param array $plugin_meta
     * @param string $plugin_file
     * @param string $plugin_data
     * @param string $status
     * @return array
     */
    public function donate_link($plugin_meta, $plugin_file, $plugin_data, $status): array
    {
        if ($plugin_file == $this->plugin_basename) {
            $plugin_meta[] = '&hearts; <a target="_blank" href="https://www.paypal.com/donate/?business=dev.sh1zen%40outlook.it&item_name=Thank+you+in+advanced+for+the+kind+donations.+You+will+sustain+me+developing+WP-Membership.&currency_code=EUR">' . __('Buy me a beer', 'wpms') . ' :o)</a>';
        }

        return $plugin_meta;
    }

    /**
     * Add link to settings in Plugins list page
     *
     * @wp-hook plugin_action_links
     * @param $links
     * @param $file
     * @return mixed
     */
    public function extra_plugin_link($links, $file)
    {
        $links[] = sprintf(
            '<a href="%s">%s</a>',
            admin_url('admin.php?page=wpms-modules-settings'),
            __('Settings', 'wpms')
        );

        return $links;
    }
}