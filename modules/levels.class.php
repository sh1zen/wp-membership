<?php
/**
 * @author    sh1zen
 * @copyright Copyright (C) 2025.
 * @license   http://www.gnu.org/licenses/gpl.html GNU/GPL
 */

namespace WPMembership\modules;

use WPS\core\RequestActions;
use WPS\core\addon\Exporter;
use WPS\core\Graphic;
use WPS\core\Query;
use WPS\core\UtilEnv;
use WPS\modules\Module;

use WPMembership\modules\supporters\LevelsList;

class Mod_Levels extends Module
{
    public array $scopes = array('admin-page', 'admin');

    protected string $context = 'wpmc';

    public function actions(): void
    {
        RequestActions::request($this->action_hook, function ($action) {
            $query = Query::getInstance()->tables(WP_MEMBERSHIP_TABLE_LEVELS);

            switch ($action) {

                case 'update':
                case 'add_new':
                    $request = $_REQUEST['new_level'];

                    $title = sanitize_text_field($request['title'] ?? '');

                    $query->insert(['description' => $request['description'] ?? '']);
                    $query->insert(['title' => $title]);
                    $query->insert(['active' => isset($request['active']) ? '1' : '0']);
                    $query->insert(['slug' => wps_generate_slug($title)]);
                    $query->insert(['duration' => (absint($request['duration.unit'] ?: 0)) * (absint($request['duration.digit'] ?? 0)) ?: YEAR_IN_SECONDS]);
                    $query->insert(['type' => sanitize_text_field($request['type'] ?: 'finite')]);

                    if ($action == 'update') {
                        $query->where(['id' => $request['level_id']]);
                    }

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
                    require_once WPMC_SUPPORTERS . 'LevelsList.class.php';

                    $table = new LevelsList(['action_hook' => $this->action_hook]);
                    $exporter = new Exporter();

                    $exporter->format($_REQUEST['export-format'] ?: 'csv')->set_data($table->get_items())->prepare()->download('levels-list');
                    $response = true;
                    break;

                default:
                    $level_ids = array_filter($_REQUEST['bulk-levels-id'] ?? []);

                    $response = false;

                    if (!empty($level_ids)) {

                        switch (strtolower($_REQUEST['bulk-action'])) {
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

            $this->add_notices(
                $response ? 'success' : 'warning',
                $response ? __('Action was correctly executed', 'members-control') : __('Action execution failed', 'members-control')
            );

        }, false, true);
    }

    public function render_sub_modules(): void
    {
        ?>
        <section class="wps-wrap">
            <block class="wps">
                <section class='wps-header'><h1><?php _e('Subscription plans', 'members-control'); ?></h1></section>
                <?php
                if (RequestActions::get_request($this->action_hook_page) === 'edit') {
                    echo $this->render_edit();
                }
                else {
                    echo Graphic::generateHTML_tabs_panels(array(

                        array(
                            'id'          => 'wpmc-subscriptions-list',
                            'tab-title'   => __('List', 'members-control'),
                            'callback'    => array($this, 'render_list'),
                            'panel-flush' => true
                        ),
                        array(
                            'id'        => 'wpmc-subscriptions-new',
                            'tab-title' => __('Add New', 'members-control'),
                            'callback'  => array($this, 'render_new')
                        )
                    ));
                }
                ?>
            </block>
        </section>
        <?php
    }

    public function render_edit(): string
    {
        if (isset($_REQUEST['level_id'])) {
            $level = Query::getInstance()->tables(WP_MEMBERSHIP_TABLE_LEVELS)->where(['id' => absint($_REQUEST['level_id'])])->query(true);
        }

        if (empty($level)) {
            return '<strong>' . __('Not valid Level ID was passed.', 'members-control') . '</strong>';
        }

        $level_edit_values = [
            'id'             => $level->id,
            'title'          => $level->title,
            'description'    => $level->description,
            'type'           => match ($level->type) {
                'indefinite' => [__("Indefinite", 'members-control') => 'indefinite'],
                'serial' => [__("Serial", 'members-control') => 'serial'],
                default => [__("Finite", 'members-control') => 'finite']
            },
            'duration.digit' => 0,
            'duration.unit'  => [__("Day", 'members-control') => DAY_IN_SECONDS],
            'active'         => UtilEnv::to_boolean($level->active)
        ];

        $time_units = [
            __("Year", 'members-control')    => YEAR_IN_SECONDS,
            __("Month", 'members-control')   => MONTH_IN_SECONDS,
            __("Day", 'members-control')     => DAY_IN_SECONDS,
            __("Hour", 'members-control')    => HOUR_IN_SECONDS,
            __("Minutes", 'members-control') => MINUTE_IN_SECONDS
        ];

        if ($level->duration) {
            foreach ($time_units as $unit => $value) {
                if ($level->duration % $value === 0) {
                    $level_edit_values['duration.digit'] = $level->duration / $value;
                    $level_edit_values['duration.unit'] = [$unit => $value];
                    break;
                }
            }
        }

        return $this->render_new($level_edit_values);
    }

    public function render_new($defaults = []): string
    {
        ob_start();
        ?>
        <form method="POST" class="wps" autocapitalize="off" autocomplete="off">
            <?php

            $setting_fields = $this->group_setting_fields(
                $this->group_setting_fields(
                    $this->setting_field(__('Title', 'members-control'), 'title', 'text', ['value' => $defaults['title'] ?? '']),
                    $this->setting_field(__('Description', 'members-control'), 'description', 'textarea', ['value' => $defaults['description'] ?? '']),
                    $this->setting_field(__('Duration', 'members-control'), 'duration.digit', 'dropdown', ['value' => $defaults['duration.digit'] ?? '1', 'list' => range(0, 365)]),
                    $this->setting_field(__('Unit', 'members-control'), 'duration.unit', 'dropdown', ['value' => $defaults['duration.unit'] ?? [__("Year", 'members-control') => YEAR_IN_SECONDS], 'list' => [
                        __("Year", 'members-control')  => YEAR_IN_SECONDS,
                        __("Month", 'members-control') => MONTH_IN_SECONDS,
                        __("Day", 'members-control')   => DAY_IN_SECONDS,
                        __("Hour", 'members-control')  => HOUR_IN_SECONDS,
                    ]]),
                    $this->setting_field(__('Type', 'members-control'), 'type', 'dropdown', ['value' => $defaults['type'] ?? [__("Finite", 'members-control') => 'finite'], 'list' => [
                        __("Finite", 'members-control')     => 'finite',
                        __("Indefinite", 'members-control') => 'indefinite',
                        __("Serial", 'members-control')     => 'serial',
                    ]]),
                    $this->setting_field(__('Active', 'members-control'), 'active', 'checkbox', ['value' => $defaults['active'] ?? true]),
                ),
            );

            RequestActions::nonce_field($this->action_hook);
            Graphic::generate_fields($setting_fields, $this->infos(), ['name_prefix' => 'new_level']);

            ?>
            <row class="wps-custom-action wps-row">
                <?php
                if (isset($defaults['id']) and $defaults['id']) {
                    echo RequestActions::get_action_button($this->action_hook, 'update', __('Update', 'members-control'), 'button-primary');
                    echo "<input type='hidden' name='new_level[level_id]' value='" . esc_attr($defaults['id']) . "'>";
                }
                else {
                    echo RequestActions::get_action_button($this->action_hook, 'add_new', __('Add new', 'members-control'), 'button-primary');
                }
                ?>
            </row>
        </form>
        <?php
        return ob_get_clean();
    }

    public function render_list(): string
    {
        ob_start();
        require_once WPMC_SUPPORTERS . 'LevelsList.class.php';

        $table = new LevelsList(['action_hook' => $this->action_hook]);

        $table->prepare_items();
        ?>
        <form method="GET" class="wps wps-list-table-form wpmc-list-table-form" autocomplete="off" autocapitalize="off">
            <input type="hidden" name="page" value="<?php echo esc_attr($_REQUEST['page']); ?>"/>
            <?php $table->display(); ?>
            <?php RequestActions::nonce_field($this->action_hook); ?>
        </form>
        <?php
        return ob_get_clean();
    }
}

return __NAMESPACE__;
