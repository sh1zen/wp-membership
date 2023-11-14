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

use WPMembership\modules\supporters\CommunicationList;

class Mod_Communications extends Module
{
    public array $scopes = array('autoload', 'admin-page');

    protected string $context = 'wpms';

    public function actions(): void
    {
        Actions::request($this->action_hook, function ($action) {

            $query = Query::getInstance()->tables(WP_MEMBERSHIP_TABLE_COMMUNICATIONS);

            switch ($action) {
                case 'add_new':
                    $request = $_REQUEST['new_comm'];

                    $query->insert(['active' => isset($request['active']) ? '1' : '0']);
                    $query->insert(['level_id' => $request['level_id'] ?: 0]);
                    $query->insert(['subject' => sanitize_text_field($request['subject'] ?? '')]);
                    $query->insert(['message' => $request['message'] ?: '']);
                    $query->insert(['event' => $request['event'] ?: 'signup']);
                    $query->insert(['timegap' => (absint($request['time.unit'] ?: 0)) * (absint($request['time.digit'] ?: 0))]);

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
                    $response = wpms_send_message(wps_utils()->cu, $_REQUEST['comm_id']);
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

        }, true, true);
    }

    public function render_admin_page(): void
    {
        ?>
        <section class="wps-wrap">
            <block class="wps">
                <section class='wps-header'><h1><?php _e('Communications', 'wpms'); ?></h1></section>
                <?php

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

            $subscriptions = ['All' => 0];

            foreach (wpms_get_levels() as $level) {
                $subscriptions[ucwords($level->title)] = $level->id;
            }

            $setting_fields = $this->group_setting_fields(
                $this->group_setting_fields(
                    $this->setting_field(__('Subject', 'wpms'), 'subject', 'text'),
                    $this->setting_field(__('Message', 'wpms'), 'message', 'textarea'),
                    $this->setting_field(__('Message Time', 'wpms'), 'time.digit', 'dropdown', ['default_value' => '0', 'list' => range(0, 365)]),
                    $this->setting_field(__('Time Unit', 'wpms'), 'time.unit', 'dropdown', ['default_value' => [__("Day", 'wpms') => DAY_IN_SECONDS], 'list' => [
                        __("Year", 'wpms')    => YEAR_IN_SECONDS,
                        __("Month", 'wpms')   => MONTH_IN_SECONDS,
                        __("Day", 'wpms')     => DAY_IN_SECONDS,
                        __("Hour", 'wpms')    => HOUR_IN_SECONDS,
                        __("Minutes", 'wpms') => MINUTE_IN_SECONDS
                    ]]),
                    $this->setting_field(__('Event', 'wpms'), 'event', 'dropdown', ['default_value' => [__("Join Subscription", 'wpms') => 'join'], 'list' => [
                        __("Sign Up", 'wpms')                 => 'signup',
                        __("Join Subscription", 'wpms')       => 'join',
                        __("Before Subscription Expire", 'wpms') => 'before',
                        __("After Subscription Expire", 'wpms')  => 'after'
                    ]]),
                    $this->setting_field(__('For Subscription', 'wpms'), 'level_id', 'dropdown', ['default_value' => [__("All", 'wpms') => 0], 'list' => $subscriptions]),
                    $this->setting_field(__('Active', 'wpms'), 'active', 'checkbox', ['default_value' => true])
                ),
            );

            Graphic::nonce_field($this->action_hook);
            Graphic::generate_fields($setting_fields, $this->infos(), ['name_prefix' => 'new_comm']);

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
        require_once WPMS_SUPPORTERS . 'CommunicationList.class.php';

        $table = new CommunicationList(['action_hook' => $this->action_hook]);

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