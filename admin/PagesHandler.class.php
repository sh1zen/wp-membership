<?php
/**
 * @author    sh1zen
 * @copyright Copyright (C) 2025.
 * @license   http://www.gnu.org/licenses/gpl.html GNU/GPL
 */

namespace WPMembership\core;

use WPS\core\Graphic;
use WPS\core\UtilEnv;

/**
 * Creates the menu page for the plugin.
 *
 * Provides the functionality necessary for rendering the page corresponding
 * to the menu with which this page is associated.
 */
class PagesHandler
{
    public function __construct()
    {
        add_action('admin_menu', array($this, 'add_plugin_pages'));
        add_action('admin_enqueue_scripts', array($this, 'register_assets'), 20, 0);
        add_filter('admin_body_class', array($this, 'admin_body_classes'));
    }

    public function add_plugin_pages(): void
    {
        add_menu_page(
            'Members Control',
            'Members Control',
            'customize',
            'members-control',
            array($this, 'render_main'),
            'dashicons-groups'
        );

        /**
         * Modules - sub pages
         */
        foreach (wps('wpmc')->moduleHandler->get_modules(array('scopes' => 'admin-page')) as $module) {

            add_submenu_page('members-control', 'WPMC' . $module['name'], $module['name'], 'customize', $module['slug'], array($this, 'render_module'));
        }

        /**
         * Plugin core settings
         */
        add_submenu_page('members-control', __('WPMC Settings', 'members-control'), __('Settings', 'members-control'), 'manage_options', 'wpmc-settings', array($this, 'render_core_settings'));
    }

    private function enqueue_scripts(): void
    {
        wp_enqueue_style('wpmc_css');
        wp_enqueue_script('vendor-wps-js');
    }

    public function render_core_settings(): void
    {
        $this->enqueue_scripts();

        wps('wpmc')->settings->render_core_settings();
    }

    public function admin_body_classes(string $classes): string
    {
        $page = isset($_GET['page']) ? sanitize_key(wp_unslash($_GET['page'])) : '';

        if (!$this->is_members_control_admin_screen($page)) {
            return $classes;
        }

        return trim($classes . ' wps-admin-screen wpmc-admin-screen');
    }

    private function is_members_control_admin_screen(string $page): bool
    {
        if ($page === 'members-control' || $page === 'wpmc-settings') {
            return true;
        }

        foreach (wps('wpmc')->moduleHandler->get_modules(array('scopes' => 'admin-page')) as $module) {
            if (!empty($module['slug']) && $page === $module['slug']) {
                return true;
            }
        }

        return false;
    }

    public function render_module(): void
    {
        $module_slug = sanitize_text_field($_GET['page']);

        $object = wps('wpmc')->moduleHandler->get_module_instance($module_slug);

        if (is_null($object)) {
            return;
        }

        $this->enqueue_scripts();

        $object->render_admin_page();
    }

    public function register_assets(): void
    {
        $style_asset = UtilEnv::resolve_asset(WPMC_ABSPATH, 'assets/style.css', wps_core()->online);

        wp_register_style("wpmc_css", $style_asset['url'], ['vendor-wps-css'], $style_asset['version'] ?: WPMC_VERSION);

        wps_localize([
            'saved'   => __('Settings Saved', 'members-control'),
            'error'   => __('Request fail', 'members-control'),
            'success' => __('Request succeed', 'members-control'),
        ]);
    }

    /**
     * This function renders the contents of the page associated with the menu
     * that invokes the render method. In the context of this plugin, this is the
     * menu class.
     */
    public function render_main(): void
    {
        $this->enqueue_scripts();
        $active_members = wpmc_stats_count_members();
        $possible_members = wpmc_stats_count_possible_members(['author']);
        $donation_url = $this->get_donation_url();
        $review_url = $this->get_review_url();
        ?>
        <section class="wps-wrap-flex wps-wrap wps-home wpmc-dashboard">
            <section class="wps">
                <block class="wps">
                    <block class="wps-header">
                        <h1>Members Control Dashboard</h1>
                    </block>
                    <div class="wpmc-stats-grid">
                        <div class="wpmc-stat-card">
                            <span><?php _e('Active members', 'members-control'); ?></span>
                            <strong><?php echo esc_html($active_members); ?></strong>
                        </div>
                        <div class="wpmc-stat-card">
                            <span><?php _e('Possible members', 'members-control'); ?></span>
                            <strong><?php echo esc_html($possible_members); ?></strong>
                        </div>
                    </div>
                    <div class="wpmc-list-card">
                        <h2><?php _e('Members by role:', 'members-control'); ?></h2>
                        <ul class="wps">
                            <?php
                            foreach (count_users()['avail_roles'] ?? [] as $role => $count) {
                                echo "<li class='wps'><strong>" . esc_html(ucwords($role)) . "</strong><span>" . esc_html($count) . "</span></li>";
                            }
                            ?>
                        </ul>
                    </div>
                    <div class="wpmc-list-card">
                        <h2><?php _e('Members by levels:', 'members-control'); ?></h2>
                        <ul class="wps">
                            <?php
                            foreach (wpmc_get_levels() as $level) {
                                echo "<li class='wps'><strong>" . esc_html(ucwords($level->title)) . "</strong><span>" . esc_html($level->count()) . "</span></li>";
                            }
                            ?>
                        </ul>
                    </div>
                </block>
            </section>
            <aside class="wps wpmc-sidebar">
                <section class="wps-box wpmc-support-card-primary">
                    <div class="wps-donation-wrap">
                        <span class="wpmc-support-icon"><?php echo Graphic::icon('star', 'wpmc-support-icon-svg'); ?></span>
                        <div class="wps-donation-title"><?php _e('Members Control helps you grow', 'members-control'); ?></div>
                        <p class="wpmc-muted"><?php _e('Support maintenance, fixes, and new features with a donation, or help more users discover the plugin with a 5-star review.', 'members-control'); ?></p>
                        <div class="wpmc-inline-actions wpmc-support-cta">
                            <a class="wps wps-button wpmc-btn is-success" href="<?php echo esc_url($donation_url); ?>" target="_blank" rel="noopener noreferrer"><?php echo Graphic::icon('heart-fill', 'wpmc-btn-icon'); ?><?php _e('Donate with PayPal', 'members-control'); ?></a>
                            <a class="wps wps-button wpmc-btn is-neutral" href="<?php echo esc_url($review_url); ?>" target="_blank" rel="noopener noreferrer"><?php echo Graphic::icon('star-outline', 'wpmc-btn-icon'); ?><?php _e('Leave a 5-star review', 'members-control'); ?></a>
                        </div>
                    </div>
                </section>
                <section class="wps-box">
                    <h3><?php echo Graphic::icon('headphones', 'wpmc-sidebar-title-icon'); ?><?php _e('Need help?', 'members-control'); ?></h3>
                    <ul class="wps wpmc-link-list">
                        <li>
                            <a href="https://translate.wordpress.org/projects/wp-plugins/members-control/"><?php echo Graphic::icon('translate', 'wpmc-link-icon'); ?><?php _e('Help me translating', 'members-control'); ?></a>
                        </li>
                    </ul>
                </section>
                <section class="wps-box">
                    <h3><?php echo Graphic::icon('box', 'wpmc-sidebar-title-icon'); ?>Members Control</h3>
                    <ul class="wps wpmc-link-list">
                        <li>
                            <a href="https://github.com/sh1zen/members-control/"><?php echo Graphic::icon('code', 'wpmc-link-icon'); ?><?php _e('Source code', 'members-control'); ?></a>
                        </li>
                        <li>
                            <a href="https://sh1zen.github.io/"><?php echo Graphic::icon('user', 'wpmc-link-icon'); ?><?php _e('About me', 'members-control'); ?></a>
                        </li>
                    </ul>
                </section>
            </aside>
        </section>
        <?php
    }

    private function get_donation_url(): string
    {
        return 'https://www.paypal.com/donate?business=dev.sh1zen%40outlook.it&item_name=Thank+you+in+advanced+for+the+kind+donations.+You+will+sustain+me+developing+MembersControl.&currency_code=EUR';
    }

    private function get_review_url(): string
    {
        return 'https://wordpress.org/support/plugin/members-control/reviews/?filter=5';
    }
}
