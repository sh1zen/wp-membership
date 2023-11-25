<?php
/**
 * @author    sh1zen
 * @copyright Copyright (C) 2023.
 * @license   http://www.gnu.org/licenses/gpl.html GNU/GPL
 */

namespace WPMembership\modules;

use WPS\core\RequestActions;
use WPS\core\addon\Exporter;
use WPS\core\Graphic;
use WPS\core\Query;
use WPS\modules\Module;

use WPMembership\modules\supporters\MembersList;

class Mod_Members extends Module
{
    public array $scopes = array('admin-page',  'admin');

    protected string $context = 'wpms';

    public function actions(): void
    {
        RequestActions::request($this->action_hook, function ($action) {

            $response = false;

            switch ($action) {

                case 'update_sub':
                case 'add_new_sub':
                    $request = $_REQUEST['membership'];
                    $response = wpms_membership_update($request['user_id'], $request['level_id'], $request['paid']);
                    break;

                case 'drop_sub':
                    $response = wpms_membership_drop($_REQUEST['user_id']);
                    break;

                case 'renew_sub':
                    $response = wpms_membership_renew($_REQUEST['user_id']);
                    break;

                case 'export':
                    require_once WPS_ADDON_PATH . 'Exporter.class.php';
                    require_once WPMS_SUPPORTERS . 'MembersList.class.php';

                    $table = new MembersList(['action_hook' => $this->action_hook]);
                    $exporter = new Exporter();

                    $exporter->format($_REQUEST['export-format'] ?: 'csv')->set_data($table->get_items())->prepare()->download('levels-list');
                    $response = true;
                    break;

                default:

                    $user_ids = array_filter($_REQUEST['bulk-users-id'] ?? []);

                    if (!empty($user_ids)) {
                        if (strtolower($_REQUEST['bulk-action']) == 'drop') {
                            $response = true;
                            foreach ($user_ids as $user_id) {
                                $user = wps_get_user($user_id);
                                $response &= wpms_membership_drop($user);
                                if ($response) {
                                    wpms_user_notify($user, 'drop');
                                }
                                else {
                                    break;
                                }
                            }
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
        ?>
        <section class="wps-wrap">
            <block class="wps">
                <section class='wps-header'><h1><?php _e('Edit Members', 'wpms'); ?></h1></section>
                <?php
                if (RequestActions::get_request($this->action_hook_page, true) === 'add' or RequestActions::get_request($this->action_hook_page, true) === 'edit') {
                    echo $this->render_edit_membership();
                }
                else {
                    echo Graphic::generateHTML_tabs_panels(array(
                        array(
                            'id'        => 'wpms-members-list',
                            'tab-title' => __('List', 'wpfs'),
                            'callback'  => array($this, 'render_list')
                        )
                    ));
                }
                ?>
            </block>
        </section>
        <?php
    }

    private function render_edit_membership()
    {
        if (isset($_REQUEST['user_id'])) {
            $user = wps_get_user($_REQUEST['user_id']);
        }

        if (empty($user)) {
            return '<strong>' . __('Not valid User ID was passed.', 'wpms') . '</strong>';
        }

        $current_sub = wpms_user_get_subscription($user);

        $defaults = [
            'level_id' => $current_sub->level_id,
            'level'    => [__("None", 'wpms') => 0]
        ];

        $defaults['paid'] = $defaults['level_id'] ? Query::getInstance()->select('paid', WP_MEMBERSHIP_TABLE_HISTORY)->where(
            ['level_id' => $defaults['level_id'], 'user_id' => $user->ID]
        )->orderby('id', 'DESC')->limit(1)->query(true) : 0;

        ob_start();
        ?>
        <form method="POST" class="wps" autocapitalize="off" autocomplete="off">
            <?php

            $subscriptions = [__("None", 'wpms') => 0];
            foreach (wpms_get_levels() as $level) {
                $subscriptions[ucwords($level->title)] = $level->id;

                if ($level->id === $defaults['level_id']) {
                    $defaults['level'] = [ucwords($level->title) => $level->id];
                }
            }

            $setting_fields = $this->group_setting_fields(
                $this->group_setting_fields(
                    $this->setting_field(__('Add Subscription', 'wpms'), 'level_id', 'dropdown', [
                        'value' => $defaults['level'],
                        'list'  => $subscriptions
                    ]),
                    $this->setting_field(__('Paid', 'wpms'), 'paid', 'number', ['value' => $defaults['paid']]),
                ),
            );

            RequestActions::nonce_field($this->action_hook);
            Graphic::generate_fields($setting_fields, $this->infos(), ['name_prefix' => 'membership']);
            ?>
            <row class="wps-custom-action wps-row">
                <?php
                echo "<input type='hidden' name='membership[user_id]' value='" . esc_attr($user->ID) . "'>";
                if ($current_sub->id) {
                    echo RequestActions::get_action_button($this->action_hook, 'update_sub', __('Update', 'wpms'), 'button-primary');
                }
                else {
                    echo RequestActions::get_action_button($this->action_hook, 'add_new_sub', __('Subscribe', 'wpms'), 'button-primary');
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
        require_once WPMS_SUPPORTERS . 'MembersList.class.php';

        $table = new MembersList(['action_hook' => $this->action_hook]);

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

    protected function init(): void
    {
        $this->path = __DIR__;
    }
}

return __NAMESPACE__;