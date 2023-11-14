<?php
/**
 * @author    sh1zen
 * @copyright Copyright (C) 2024.
 * @license   http://www.gnu.org/licenses/gpl.html GNU/GPL
 */

namespace WPMembership\modules;

use WPS\core\RequestActions;
use WPS\core\addon\Exporter;
use WPS\core\Graphic;
use WPS\core\Query;
use WPS\core\UtilEnv;
use WPS\modules\Module;

use WPMembership\modules\supporters\CommunicationList;

class Mod_Communications extends Module
{
    public array $scopes = array('admin-page', 'admin');

    protected string $context = 'wpms';

    public function actions(): void
    {
        RequestActions::request($this->action_hook, function ($action) {

            $query = Query::getInstance()->tables(WP_MEMBERSHIP_TABLE_COMMUNICATIONS);

            switch ($action) {

                case 'update_comm':
                case 'add_new_comm':
                    $request = $_REQUEST['new_comm'];

                    $query->insert(['active' => isset($request['active']) ? '1' : '0']);
                    $query->insert(['level_id' => $request['level_id'] ?: 0]);
                    $query->insert(['subject' => sanitize_text_field($request['subject'] ?? '')]);
                    $query->insert(['message' => $request['message'] ?: '']);
                    $query->insert(['event' => $request['event'] ?: 'signup']);
                    $query->insert(['forward_admin' => isset($request['forward_admin']) ? '1' : '0']);

                    $query->insert(['timegap' => match ($request['event'] ?: 'signup') {
                        'leave', 'drop', 'join', 'signup', 'after_expire' => 0,
                        default => (absint($request['time.unit'] ?: 0)) * (absint($request['time.digit'] ?: 0)),
                    }]);

                    if ($action == 'update_comm') {
                        $query->where(['id' => $request['comm_id']]);
                    }

                    $response = $query->query();

                    break;

                case 'activate':
                    $response = $query->update(['active' => '1'], ['id' => absint($_REQUEST['comm_id'])])->query();
                    break;

                case 'deactivate':
                    $response = $query->update(['active' => '0'], ['id' => absint($_REQUEST['comm_id'])])->query();
                    break;

                case 'delete':
                    $response = $query->delete(['id' => absint($_REQUEST['comm_id'])])->query();
                    break;

                case 'sendme':
                    $response = wpms_user_notify(wps_core()->get_current_user(), $_REQUEST['comm_id']);
                    break;

                case 'export':
                    require_once WPS_ADDON_PATH . 'Exporter.class.php';
                    require_once WPMS_SUPPORTERS . 'CommunicationList.class.php';

                    $table = new CommunicationList(['action_hook' => $this->action_hook]);
                    $exporter = new Exporter();

                    $exporter->format($_REQUEST['export-format'] ?: 'csv')->set_data($table->get_items())->prepare()->download('communications-list');
                    $response = true;
                    break;

                default:
                    $level_ids = array_filter($_REQUEST['bulk-comm-ids'] ?? []);

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
                $response ? __('Action was correctly executed', $this->context) : __('Action execution failed', $this->context)
            );

        }, false, true);
    }

    public function render_sub_modules(): void
    {
        $this->remove_browser_query_args(['bulk-action', 'bulk-comm-ids']);
        ?>
        <section class="wps-wrap">
            <block class="wps">
                <section class='wps-header'><h1><?php _e('Communications', 'wpms'); ?></h1></section>
                <?php

                if (RequestActions::get_request($this->action_hook_page) === 'edit') {
                    echo $this->render_edit();
                }
                else {
                    echo Graphic::generateHTML_tabs_panels(array(

                        array(
                            'id'        => 'wpms-communications-list',
                            'tab-title' => __('List', 'wpfs'),
                            'callback'  => array($this, 'render_list')
                        ),
                        array(
                            'id'        => 'wpms-communications-new',
                            'tab-title' => __('Add New', 'wpfs'),
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
        if (isset($_REQUEST['comm_id'])) {
            $comm = Query::getInstance()->tables(WP_MEMBERSHIP_TABLE_COMMUNICATIONS)->where(['id' => absint($_REQUEST['comm_id'])])->query(true);
        }

        if (empty($comm)) {
            return '<strong>' . __('Not valid Message ID was passed.', 'wpms') . '</strong>';
        }

        $comm_values = [
            'id'            => $comm->id,
            'subject'       => $comm->subject,
            'message'       => $comm->message,
            'level_id'      => ['All' => 0],
            'active'        => UtilEnv::to_boolean($comm->active),
            'forward_admin' => UtilEnv::to_boolean($comm->forward_admin),
            'event'         => match ($comm->event) {
                'signup' => [__("Sign Up", 'wpms') => 'signup'],
                'before_expire' => [__("Before Subscription Expire", 'wpms') => 'before_expire'],
                'after_expire' => [__("After Subscription Expire", 'wpms') => 'after_expire'],
                'leave' => [__("User Leave Membership", 'wpms') => 'leave'],
                'drop' => [__("Membership Drop", 'wpms') => 'drop'],
                default => [__("Join Subscription", 'wpms') => 'join']
            },
            'time.digit'    => 0,
            'time.unit'     => [__("Day", 'wpms') => DAY_IN_SECONDS]
        ];

        foreach (wpms_get_levels() as $level) {
            if ($comm->level_id == $level->id) {
                $comm_values['level_id'] = [ucwords($level->title) => $level->id];
                break;
            }
        }

        $time_units = [
            __("Year", 'wpms')    => YEAR_IN_SECONDS,
            __("Month", 'wpms')   => MONTH_IN_SECONDS,
            __("Day", 'wpms')     => DAY_IN_SECONDS,
            __("Hour", 'wpms')    => HOUR_IN_SECONDS,
            __("Minutes", 'wpms') => MINUTE_IN_SECONDS
        ];

        if ($comm->timegap) {
            foreach ($time_units as $unit => $value) {
                if ($comm->timegap % $value === 0) {
                    $comm_values['time.digit'] = $comm->timegap / $value;
                    $comm_values['time.unit'] = [$unit => $value];
                    break;
                }
            }
        }

        return $this->render_new($comm_values);
    }

    public function render_new($defaults = []): string
    {
        ob_start();
        ?>
        <form method="POST" class="wps" autocapitalize="off" autocomplete="off">
            <?php

            $subscriptions = ['All' => 0];

            foreach (wpms_get_levels() as $level) {
                $subscriptions[ucwords($level->title)] = $level->id;
            }

            $setting_fields = $this->group_setting_fields(
                $this->group_setting_fields(
                    $this->setting_field(__('Subject', 'wpms'), 'subject', 'text', ['value' => $defaults['subject'] ?? '']),
                    $this->setting_field(__('Message', 'wpms'), 'message', 'textarea', ['value' => $defaults['message'] ?? '']),
                    $this->setting_field(__('Message Time', 'wpms'), 'time.digit', 'dropdown', ['value' => $defaults['time.digit'] ?? 0, 'list' => range(0, 365)]),
                    $this->setting_field(__('Time Unit', 'wpms'), 'time.unit', 'dropdown', ['value' => $defaults['time.unit'] ?? [__("Day", 'wpms') => DAY_IN_SECONDS], 'list' => [
                        __("Year", 'wpms')    => YEAR_IN_SECONDS,
                        __("Month", 'wpms')   => MONTH_IN_SECONDS,
                        __("Day", 'wpms')     => DAY_IN_SECONDS,
                        __("Hour", 'wpms')    => HOUR_IN_SECONDS,
                        __("Minutes", 'wpms') => MINUTE_IN_SECONDS
                    ]]),
                    $this->setting_field(__('Event', 'wpms'), 'event', 'dropdown', [
                        'value' => $defaults['event'] ?? [__("Join Subscription", 'wpms') => 'join'],
                        'list'  => [
                            __("Sign Up", 'wpms')                    => 'signup',
                            __("Join Subscription", 'wpms')          => 'join',
                            __("Before Subscription Expire", 'wpms') => 'before_expire',
                            __("After Subscription Expire", 'wpms')  => 'before_expire',
                            __("User Leave Membership", 'wpms')      => 'leave',
                            __("Membership Drop", 'wpms')            => 'drop',
                        ]
                    ]),
                    $this->setting_field(__('For Subscription', 'wpms'), 'level_id', 'dropdown', [
                        'value' => $defaults['level_id'] ?? [__("All", 'wpms') => 0],
                        'list'  => $subscriptions
                    ]),
                    $this->setting_field(__('Active', 'wpms'), 'active', 'checkbox', ['value' => $defaults['active'] ?? true]),
                    $this->setting_field(__('Forward To Admin', 'wpms'), 'forward_admin', 'checkbox', ['value' => $defaults['forward_admin'] ?? true])
                ),
            );

            RequestActions::nonce_field($this->action_hook);
            Graphic::generate_fields($setting_fields, $this->infos(), ['name_prefix' => 'new_comm']);
            ?>
            <row class="wps-custom-action wps-row">
                <?php
                if (isset($defaults['id']) and $defaults['id']) {
                    echo RequestActions::get_action_button($this->action_hook, 'update_comm', __('Update', 'wpms'), 'button-primary');
                    echo "<input type='hidden' name='new_comm[comm_id]' value='" . esc_attr($defaults['id']) . "'>";
                }
                else {
                    echo RequestActions::get_action_button($this->action_hook, 'add_new_comm', __('Add new', 'wpms'), 'button-primary');
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
        require_once WPMS_SUPPORTERS . 'CommunicationList.class.php';

        $table = new CommunicationList(['action_hook' => $this->action_hook]);

        $table->prepare_items();
        ?>
        <block class="wps-boxed--light">
            <form method="GET" class="wps" autocomplete="off" autocapitalize="off">
                <input type="hidden" name="page" value="<?php echo esc_attr($_REQUEST['page']); ?>"/>
                <?php $table->display(); ?>
                <?php RequestActions::nonce_field($this->action_hook); ?>
            </form>
        </block>
        <?php
        return ob_get_clean();
    }

    public function handle_register_communication($user_id): bool
    {
        return wpms_user_notify($user_id, 'signup');
    }

    protected function init(): void
    {
        add_action('user_register', [$this, 'handle_register_communication'], 10, 1);
    }
}

return __NAMESPACE__;