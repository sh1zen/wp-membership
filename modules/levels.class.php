<?php
/**
 * @author    sh1zen
 * @copyright Copyright (C) 2023.
 * @license   http://www.gnu.org/licenses/gpl.html GNU/GPL
 */

namespace WPMembership\modules;

use WPS\core\Actions;
use WPS\core\addon\Exporter;
use WPS\core\Graphic;
use WPS\core\Query;
use WPS\modules\Module;

use WPMembership\modules\supporters\LevelsList;

class Mod_Levels extends Module
{
    public array $scopes = array('admin-page', 'autoload');

    protected string $context = 'wpms';

    public function actions(): void
    {
        Actions::request($this->action_hook, function ($action) {

            $query = Query::getInstance()->tables(WP_MEMBERSHIP_TABLE_LEVELS);

            switch ($action) {
                case 'add_new':

                    $request = $_REQUEST['new_level'];

                    $title = sanitize_text_field($request['title'] ?? '');

                    $query->insert(['description' => $request['description'] ?? '']);
                    $query->insert(['title' => $title]);
                    $query->insert(['active' => isset($request['active']) ? '1' : '0']);
                    $query->insert(['slug' => wps_generate_slug($title)]);
                    $query->insert(['duration' => (absint($request['duration.unit'] ?: 0)) * (absint($request['duration.digit'] ?? 0) ?: YEAR_IN_SECONDS)]);
                    $query->insert(['type' => sanitize_text_field($request['type'] ?: 'finite')]);

                    $response = $query->query();
                    break;

                case 'activate':
                    $response = $query->update(['active' => '1'], ['id' => absint($_REQUEST['level_id'])])->query();
                    break;

                case 'deactivate':
                    $response = $query->update(['active' => '0'], ['id' => absint($_REQUEST['level_id'])])->query();
                    break;

                case 'delete':
                    $response = $query->delete(['id' => absint($_REQUEST['level_id'])])->query();
                    break;

                case 'export':
                    require_once WPS_ADDON_PATH . 'Exporter.class.php';
                    require_once WPMS_SUPPORTERS . 'LevelsList.class.php';

                    $table = new LevelsList(['action_hook' => $this->action_hook]);
                    $exporter = new Exporter();

                    $exporter->format($_REQUEST['export-format'] ?: 'csv')->set_data($table->get_items())->prepare()->download('levels-list');
                    $response = true;
                    break;

                default:
                    $level_ids = array_filter($_REQUEST['bulk-levels-id'] ?? []);

                    $response = false;

                    if (!empty($level_ids)) {

                        switch (strtolower($_REQUEST['action2'])) {
                            case 'activate':
                                $response = $query->update(['active' => '1'], ['id' => $level_ids])->query();
                                break;

                            case 'deactivate':
                                $response = $query->update(['active' => '0'], ['id' => $level_ids])->query();
                                break;

                            case 'delete':
                                $response = $query->delete(['id' => $level_ids])->query();
                                break;
                        }
                    }
            }

            return [
                'wps-status' => $response ? 'success' : 'warning',
                'wps-notice' => $response ? __('Action was correctly executed', $this->context) : __('Action execution failed', $this->context)
            ];

        }, false, true);
    }

    public function render_admin_page(): void
    {
        ?>
        <section class="wps-wrap">
            <block class="wps">
                <section class='wps-header'><h1><?php _e('Subscription plans', 'wpms'); ?></h1></section>
                <?php

                echo Graphic::generateHTML_tabs_panels(array(

                    array(
                        'id'        => 'wpms-subscriptions-list',
                        'tab-title' => __('List', 'wpfs'),
                        'callback'  => array($this, 'render_list')
                    ),
                    array(
                        'id'        => 'wpms-subscriptions-new',
                        'tab-title' => __('Add New', 'wpfs'),
                        'callback'  => array($this, 'render_new')
                    )
                ));
                ?>
            </block>
        </section>
        <?php
    }

    public function render_new(): string
    {
        ob_start();
        ?>
        <form method="POST" class="wps" autocapitalize="off" autocomplete="off">
            <?php

            $setting_fields = $this->group_setting_fields(
                $this->group_setting_fields(
                    $this->setting_field(__('Title', 'wpms'), 'title', 'text'),
                    $this->setting_field(__('Description', 'wpms'), 'description', 'textarea'),
                    $this->setting_field(__('Duration', 'wpms'), 'duration.digit', 'dropdown', ['default_value' => '1', 'list' => range(0, 365)]),
                    $this->setting_field(__('Unit', 'wpms'), 'duration.unit', 'dropdown', ['default_value' => [__("Year", 'wpms') => YEAR_IN_SECONDS], 'list' => [
                        __("Year", 'wpms')  => YEAR_IN_SECONDS,
                        __("Month", 'wpms') => MONTH_IN_SECONDS,
                        __("Day", 'wpms')   => DAY_IN_SECONDS,
                        __("Hour", 'wpms')  => HOUR_IN_SECONDS,
                    ]]),
                    $this->setting_field(__('Type', 'wpms'), 'type', 'dropdown', ['default_value' => [__("Finite", 'wpms') => 'finite'], 'list' => [
                        __("Finite", 'wpms')     => 'finite',
                        __("Indefinite", 'wpms') => 'indefinite',
                        __("Serial", 'wpms')     => 'serial',
                    ]]),
                    $this->setting_field(__('Active', 'wpms'), 'active', 'checkbox', ['default_value' => true]),
                ),
            );

            Graphic::nonce_field($this->action_hook);
            Graphic::generate_fields($setting_fields, $this->infos(), ['name_prefix' => 'new_level']);

            ?>
            <row class="wps-custom-action wps-row">
                <?php echo Actions::get_action_button($this->action_hook, 'add_new', __('Add new', 'wpms'), 'button-primary'); ?>
            </row>
        </form>
        <?php
        return ob_get_clean();
    }

    public function render_list(): string
    {
        ob_start();
        require_once WPMS_SUPPORTERS . 'LevelsList.class.php';

        $table = new LevelsList(['action_hook' => $this->action_hook]);

        $table->prepare_items();
        ?>
        <block class="wps-boxed--light">
            <form method="GET" class="wps" autocomplete="off" autocapitalize="off">
                <input type="hidden" name="page" value="<?php echo esc_attr($_REQUEST['page']); ?>"/>
                <?php $table->display(); ?>
                <?php Graphic::nonce_field($this->action_hook); ?>
            </form>
        </block>
        <?php
        return ob_get_clean();
    }

    protected function init(): void
    {
        $this->path = __DIR__;
    }
}

return __NAMESPACE__;