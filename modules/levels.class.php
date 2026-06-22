<?php
/**
 * @author    sh1zen
 * @copyright Copyright (C) 2025.
 * @license   http://www.gnu.org/licenses/gpl.html GNU/GPL
 */

namespace WPMembership\modules;

use WPS\core\RequestActions;
use WPS\core\Ajax;
use WPS\core\addon\Exporter;
use WPS\core\Graphic;
use WPS\core\Query;
use WPS\core\UtilEnv;
use WPS\modules\Module;

use WPMembership\modules\supporters\LevelsList;

class Mod_Levels extends Module
{
    public array $scopes = array('admin-page', 'admin', 'ajax');

    protected string $context = 'wpmc';

    public function restricted_access($context = ''): bool
    {
        return $context === 'ajax' && !current_user_can('manage_options');
    }

    public function actions(): void
    {
        RequestActions::request($this->action_hook, function ($action) {
            $query = Query::getInstance()->tables(WP_MEMBERSHIP_TABLE_LEVELS);

            switch ($action) {

                case 'update':
                case 'add_new':
                    $response = $this->save_level($_REQUEST['new_level'] ?? [], $action === 'update');
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

    public function ajax_handler($args = array()): void
    {
        if (($args['action'] ?? '') !== 'autosave_level') {
            parent::ajax_handler($args);
            return;
        }

        parse_str((string)($args['form_data'] ?? ''), $form_data);

        $request = $form_data['new_level'] ?? [];

        if (!is_array($request) || empty($request['level_id'])) {
            Ajax::response([
                'text' => __('Cannot detect which subscription plan must be saved.', 'members-control'),
            ], 'error');
        }

        $response = $this->save_level($request, true);

        if (!$response) {
            Ajax::response([
                'text' => __('Autosave failed while updating the subscription plan.', 'members-control'),
            ], 'error');
        }

        Ajax::response([
            'text'     => __('Subscription plan autosaved.', 'members-control'),
            'level_id' => absint($request['level_id']),
        ], 'success');
    }

    private function save_level(array $request, bool $is_update): bool
    {
        $query = Query::getInstance()->tables(WP_MEMBERSHIP_TABLE_LEVELS);

        $title = sanitize_text_field($request['title'] ?? '');

        $query->insert(['description' => $request['description'] ?? '']);
        $query->insert(['title' => $title]);
        $query->insert(['active' => isset($request['active']) ? '1' : '0']);
        $query->insert(['slug' => wps_generate_slug($title)]);
        $query->insert(['duration' => (absint($request['duration.unit'] ?: 0)) * (absint($request['duration.digit'] ?? 0)) ?: YEAR_IN_SECONDS]);
        $query->insert(['type' => sanitize_text_field($request['type'] ?: 'finite')]);

        if ($is_update) {
            $query->where(['id' => absint($request['level_id'] ?? 0)]);
        }

        return (bool)$query->query();
    }

    public function render_sub_modules(bool $standalone = true): void
    {
        ?>
        <section class="wps-wrap">
            <block class="wps">
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
        $is_edit = isset($defaults['id']) and $defaults['id'];

        ob_start();
        ?>
        <form method="POST" class="wps wpmc-level-form<?php echo $is_edit ? ' wpmc-level-autosave-form' : ''; ?>" autocapitalize="off" autocomplete="off" <?php echo $is_edit ? 'data-wpmc-autosave="level"' : ''; ?>>
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
                if ($is_edit) {
                    echo "<input type='hidden' name='new_level[level_id]' value='" . esc_attr($defaults['id']) . "'>";
                    echo '<span class="wpmc-autosave-status" aria-live="polite">' . esc_html__('All changes saved', 'members-control') . '</span>';
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
        <form method="GET" class="wps wps-list-table-form wpmc-list-table-form wpmc-levels-table-form" autocomplete="off" autocapitalize="off">
            <input type="hidden" name="page" value="<?php echo esc_attr($_REQUEST['page']); ?>"/>
            <?php if (!empty($_REQUEST['wps-page'])) : ?>
                <input type="hidden" name="wps-page" value="<?php echo esc_attr(sanitize_key(wp_unslash($_REQUEST['wps-page']))); ?>"/>
            <?php endif; ?>
            <?php $table->display(); ?>
            <?php RequestActions::nonce_field($this->action_hook); ?>
        </form>
        <?php
        return ob_get_clean();
    }
}

return __NAMESPACE__;
