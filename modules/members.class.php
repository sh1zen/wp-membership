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
use WPS\modules\Module;

use WPMembership\modules\supporters\MembersList;

class Mod_Members extends Module
{
    public array $scopes = array('admin-page', 'admin');

    protected string $context = 'wpmc';

    public function actions(): void
    {
        RequestActions::request($this->action_hook, function ($action) {

            $response = false;

            switch ($action) {

                case 'update_sub':
                case 'add_new_sub':
                    $request = $_REQUEST['membership'];

                    if ($request['level_id'] == 0) {
                        $response = wpmc_membership_drop($request['user_id']);
                    }
                    else {
                        $response = wpmc_membership_update($request['user_id'], $request['level_id'], $request['paid']);
                        wpmc_membership_extend($request['user_id'], $request['gift_days']);
                    }
                    break;

                case 'resume_sub':
                case 'suspend_sub':
                    $response = wpmc_membership_suspend($_REQUEST['user_id']);
                    break;

                case 'drop_sub':
                    $response = wpmc_membership_drop($_REQUEST['user_id']);
                    break;

                case 'export':
                    require_once WPS_ADDON_PATH . 'Exporter.class.php';
                    require_once WPMC_SUPPORTERS . 'MembersList.class.php';

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
                                wpmc_membership_drop($user_id);
                            }
                        }
                    }
            }

            $this->add_notices(
                $response ? 'success' : 'warning',
                $response ? __('Action was correctly executed', 'members-control') : __('Action execution failed', 'members-control')
            );

        }, false, true);
    }

    public function render_sub_modules(bool $standalone = true): void
    {
        ?>
        <section class="wps-wrap">
            <block class="wps">
                <?php
                if (RequestActions::get_request($this->action_hook_page, true) === 'add' or RequestActions::get_request($this->action_hook_page, true) === 'edit') {
                    echo $this->render_edit_membership();
                }
                else {
                    echo Graphic::generateHTML_tabs_panels(array(
                        array(
                            'id'          => 'wpmc-members-list',
                            'tab-title'   => __('List', 'members-control'),
                            'callback'    => array($this, 'render_list'),
                            'panel-flush' => true
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
            return '<strong>' . __('Not valid User ID was passed.', 'members-control') . '</strong>';
        }

        $member = wpmc_get_member($user);

        $defaults = [
            'level_id'  => $member->get_sub()->level_id,
            'level'     => [__("None", 'members-control') => 0],
            'paid'      => $member->get_pays(),
            'gift_days' => $member->gift_days()
        ];

        ob_start();
        ?>
        <form method="POST" class="wps wpmc-membership-form" autocapitalize="off" autocomplete="off">
            <?php

            $subscriptions = [__("None", 'members-control') => 0];
            foreach (wpmc_get_levels() as $level) {
                $subscriptions[ucwords($level->title)] = $level->id;

                if ($level->id === $defaults['level_id']) {
                    $defaults['level'] = [ucwords($level->title) => $level->id];
                }
            }

            $setting_fields = $this->group_setting_fields(
                $this->group_setting_fields(
                    $this->setting_field(__('Add Subscription', 'members-control'), 'level_id', 'dropdown', [
                        'value' => $defaults['level'],
                        'list'  => $subscriptions
                    ]),
                    $this->setting_field(__('Paid', 'members-control'), 'paid', 'number', ['value' => $defaults['paid']]),
                    $this->setting_field(__('Gift Days', 'members-control'), 'gift_days', 'number', ['value' => $defaults['gift_days']]),
                ),
            );

            RequestActions::nonce_field($this->action_hook);
            echo '<div class="wpmc-membership-fields">';
            Graphic::generate_fields($setting_fields, $this->infos(), ['name_prefix' => 'membership']);
            echo '</div>';
            ?>
            <row class="wps-custom-action wps-row">
                <?php
                echo "<input type='hidden' name='membership[user_id]' value='" . esc_attr($user->ID) . "'>";
                if ($member->has_subscription()) {
                    echo RequestActions::get_action_button($this->action_hook, 'update_sub', __('Update', 'members-control'), 'button-primary');
                }
                else {
                    echo RequestActions::get_action_button($this->action_hook, 'add_new_sub', __('Subscribe', 'members-control'), 'button-primary');
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
        require_once WPMC_SUPPORTERS . 'MembersList.class.php';

        $table = new MembersList(['action_hook' => $this->action_hook, 'context' => 'manage']);

        $table->prepare_items();
        ?>
        <form method="GET" class="wps wps-list-table-form wpmc-list-table-form wpmc-members-table-form" autocomplete="off" autocapitalize="off">
            <input type="hidden" name="page" value="<?php echo esc_attr($_REQUEST['page']); ?>"/>
            <?php $table->display(); ?>
            <?php RequestActions::nonce_field($this->action_hook); ?>
        </form>
        <?php
        return ob_get_clean();
    }
}

return __NAMESPACE__;
