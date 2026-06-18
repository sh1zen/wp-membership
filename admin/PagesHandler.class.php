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
        add_filter('wps_wpmc_core_settings_pages', array($this, 'register_core_setting_pages'), 10, 2);
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

        $this->add_plugin_submenu_pages('members-control', 'wpmc', 'members-control');

        foreach (wps('wpmc')->moduleHandler->get_modules(array('scopes' => 'admin-page'), true) as $module) {

            add_submenu_page(null, 'WPMC' . $module['name'], $module['name'], 'customize', $module['slug'], array($this, 'render_module'));
        }

        add_submenu_page(null, __('WPMC Settings', 'members-control'), __('Settings', 'members-control'), 'manage_options', 'wpmc-settings', array($this, 'render_core_settings'));
    }

    private function add_plugin_submenu_pages(string $parent_slug, string $context, string $text_domain): void
    {
        add_submenu_page(
            $parent_slug,
            __('Dashboard', $text_domain),
            __('Dashboard', $text_domain),
            'customize',
            $parent_slug,
            array($this, 'render_main')
        );

        $labels = array(
            'modules_handler' => __('Modules', $text_domain),
            'cron'            => __('Cron Manager', $text_domain),
            'settings'        => __('Settings', $text_domain),
            'tracking'        => __('Tracking', $text_domain),
        );

        foreach (wps($context)->settings->get_core_setting_pages() as $page) {
            if (!isset($labels[$page['id']])) {
                continue;
            }

            add_submenu_page(
                $parent_slug,
                $labels[$page['id']],
                $labels[$page['id']],
                'manage_options',
                $parent_slug . '&wps-page=setting-' . $page['id'],
                array($this, 'render_main')
            );
        }
    }

    public function register_core_setting_pages(array $pages, $settings = null): array
    {
        $definitions = array(
            'modules_handler' => array(
                'label'       => __('Modules', 'members-control'),
                'icon'        => 'grid',
                'description' => __('Choose which Members Control tools are available in the workspace.', 'members-control'),
            ),
            'settings'        => array(
                'label'       => __('Settings', 'members-control'),
                'icon'        => 'settings',
                'description' => __('Manage shared Members Control options and transfer tools.', 'members-control'),
            ),
        );

        foreach ($definitions as $module_slug => $definition) {
            $object = wps('wpmc')->moduleHandler->get_module_instance($module_slug);

            if (!$object) {
                continue;
            }

            $pages[] = array_merge($definition, array(
                'id'       => $module_slug,
                'callback' => array($object, 'render_settings'),
            ));
        }

        return $pages;
    }

    private function enqueue_scripts(): void
    {
        wp_enqueue_style('wpmc_css');
        wp_enqueue_script('vendor-wps-js');
        wp_enqueue_script('wpmc_admin_js');
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

        foreach (wps('wpmc')->moduleHandler->get_modules(array('scopes' => 'admin-page'), true) as $module) {
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
        $script_asset = UtilEnv::resolve_asset(WPMC_ABSPATH, 'assets/admin.js', wps_core()->online);

        wp_register_style("wpmc_css", $style_asset['url'], ['vendor-wps-css'], $style_asset['version'] ?: WPMC_VERSION);
        wp_register_script("wpmc_admin_js", $script_asset['url'], ['vendor-wps-js', 'jquery'], $script_asset['version'] ?: WPMC_VERSION, true);

        wps_localize([
            'saved'              => __('Settings Saved', 'members-control'),
            'error'              => __('Request fail', 'members-control'),
            'success'            => __('Request succeed', 'members-control'),
            'autosaving'         => __('Autosaving...', 'members-control'),
            'autosaved'          => __('All changes saved', 'members-control'),
            'autosave_failed'    => __('Autosave failed', 'members-control'),
            'wpmc_ajax_nonce'    => wp_create_nonce('wpmc-ajax-nonce'),
            'text_close_warning' => __('Members Control is running an action. If you leave now, it may not be completed.', 'members-control'),
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
        $this->render_admin_app();
    }

    private function render_admin_app(): void
    {
        $route = $this->get_app_route();
        $route_label = $this->get_app_route_label($route);

        Graphic::render_admin_app([
            'title'      => 'Members Control',
            'page_title' => $route === 'dashboard' ? __('Members Control Dashboard', 'members-control') : $route_label,
            'version'    => 'v' . WPMC_VERSION,
            'context'    => 'wpmc',
            'active'     => $route,
            'breadcrumb' => $route_label,
            'status'     => __('Healthy', 'members-control'),
            'brand_icon' => 'user',
            'nav'        => $this->get_app_nav(),
            'help'       => '<strong>' . esc_html__('Need help?', 'members-control') . '</strong><p><a href="https://wordpress.org/support/plugin/members-control/" target="_blank" rel="noopener noreferrer">' . esc_html__('Open support', 'members-control') . '</a></p>',
            'content'    => function () use ($route) {
                $this->render_app_content($route);
            },
        ]);
    }

    private function get_app_route(): string
    {
        return isset($_GET['wps-page']) ? sanitize_key(wp_unslash($_GET['wps-page'])) : 'dashboard';
    }

    private function get_app_nav(): array
    {
        $modules = array();

        foreach (wps('wpmc')->moduleHandler->get_modules(array('scopes' => 'admin-page'), true) as $module) {
            $modules[] = array(
                'id'    => 'module-' . $module['slug'],
                'label' => $module['name'],
                'icon'  => 'user',
                'url'   => wps_admin_route_url('wpmc', 'module-' . $module['slug']),
            );
        }

        $settings_items = $this->get_settings_nav_items('wpmc');
        $nav = array(
            array(
                'label' => __('Overview', 'members-control'),
                'items' => array_merge(
                    array(array('id' => 'dashboard', 'label' => __('Dashboard', 'members-control'), 'icon' => 'gauge', 'url' => wps_admin_route_url('wpmc'))),
                    $this->get_core_settings_nav_items('wpmc')
                ),
            ),
        );

        if (!empty($settings_items)) {
            $nav[] = array(
                'label' => __('Settings', 'members-control'),
                'items' => $settings_items,
            );
        }

        $nav[] = array(
            'label' => __('Members', 'members-control'),
            'items' => $modules,
        );

        return $nav;
    }

    private function get_app_route_label(string $route): string
    {
        if ($route === 'core-settings') {
            return __('Settings', 'members-control');
        }

        if (str_starts_with($route, 'setting-')) {
            $setting_id = substr($route, 8);

            foreach (wps('wpmc')->settings->get_core_setting_pages() as $page) {
                if ($page['id'] === $setting_id) {
                    return $page['label'];
                }
            }
        }

        if (str_starts_with($route, 'module-setting-')) {
            $setting_id = substr($route, 15);

            foreach (wps('wpmc')->settings->get_module_setting_pages() as $page) {
                if ($page['id'] === $setting_id) {
                    return $page['label'];
                }
            }
        }

        if (str_starts_with($route, 'module-')) {
            $slug = substr($route, 7);

            foreach (wps('wpmc')->moduleHandler->get_modules(array('scopes' => 'admin-page'), true) as $module) {
                if ($module['slug'] === $slug) {
                    return $module['name'];
                }
            }
        }

        return __('Dashboard', 'members-control');
    }

    private function render_app_content(string $route): void
    {
        if ($route === 'core-settings') {
            $this->render_first_core_setting_page('wpmc');
            return;
        }

        if (str_starts_with($route, 'setting-')) {
            wps('wpmc')->settings->render_core_setting_page(substr($route, 8), false);
            return;
        }

        if (str_starts_with($route, 'module-setting-')) {
            wps('wpmc')->settings->render_module_setting_page(substr($route, 15), false);
            return;
        }

        if (str_starts_with($route, 'module-')) {
            $object = wps('wpmc')->moduleHandler->get_module_instance(substr($route, 7));

            if ($object) {
                $this->render_legacy_app_panel(function () use ($object) {
                    $object->render_admin_page(false);
                });
                return;
            }
        }

        $this->render_app_dashboard();
    }

    private function render_app_dashboard(): void
    {
        $active_members = wpmc_stats_count_members();
        $possible_members = wpmc_stats_count_possible_members(['author']);
        ?>
        <section class="wps-app-panel">
            <h1><?php esc_html_e('Dashboard', 'members-control'); ?></h1>
            <div class="wps-app-grid">
                <div class="wps-app-card"><strong><?php esc_html_e('Active members', 'members-control'); ?></strong><small><?php echo esc_html($active_members); ?></small></div>
                <div class="wps-app-card"><strong><?php esc_html_e('Possible members', 'members-control'); ?></strong><small><?php echo esc_html($possible_members); ?></small></div>
                <div class="wps-app-card"><strong><?php esc_html_e('Roles', 'members-control'); ?></strong><small><?php echo esc_html(count(count_users()['avail_roles'] ?? array())); ?></small></div>
            </div>
        </section>
        <section class="wps-app-panel">
            <h2><?php esc_html_e('Membership tools', 'members-control'); ?></h2>
            <div class="wps-app-grid">
                <?php foreach (wps('wpmc')->moduleHandler->get_modules(array('scopes' => 'admin-page'), true) as $module) : ?>
                    <a class="wps-app-card" href="<?php echo esc_url(wps_admin_route_url('wpmc', 'module-' . $module['slug'])); ?>">
                        <strong><?php echo esc_html($module['name']); ?></strong>
                        <small><?php esc_html_e('Open this tool inside the Members Control workspace.', 'members-control'); ?></small>
                    </a>
                <?php endforeach; ?>
            </div>
        </section>
        <?php
    }

    private function get_settings_nav_items(string $context): array
    {
        $items = array();

        foreach (wps($context)->settings->get_module_setting_pages() as $page) {
            $items[] = array(
                'id'    => 'module-setting-' . $page['id'],
                'label' => $page['label'],
                'icon'  => $page['icon'],
                'url'   => wps_admin_route_url($context, 'module-setting-' . $page['id']),
            );
        }

        return $items;
    }

    private function get_core_settings_nav_items(string $context): array
    {
        $items = array();

        foreach (wps($context)->settings->get_core_setting_pages() as $page) {
            $items[] = array(
                'id'    => 'setting-' . $page['id'],
                'label' => $page['label'],
                'icon'  => $page['icon'],
                'url'   => wps_admin_route_url($context, 'setting-' . $page['id']),
            );
        }

        return $items;
    }

    private function render_first_core_setting_page(string $context): void
    {
        $pages = wps($context)->settings->get_core_setting_pages();
        $page_id = $pages[0]['id'] ?? '';

        wps($context)->settings->render_core_setting_page($page_id, false);
    }

    private function render_legacy_app_panel(callable $callback): void
    {
        ob_start();
        call_user_func($callback);
        $content = ob_get_clean();

        echo $content;
    }
}
