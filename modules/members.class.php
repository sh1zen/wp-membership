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

use WPMembership\modules\supporters\MembersList;

class Mod_Members extends Module
{
    public array $scopes = array('admin-page', 'autoload');

    protected string $context = 'wpms';

    public function actions(): void
    {
        Actions::request($this->action_hook, function ($action) {

            $query = Query::getInstance()->tables(WP_MEMBERSHIP_TABLE_LEVELS);

            switch ($action) {

                case 'add':
                    $response = wpms_add_subscription($_REQUEST['user_id'], $_REQUEST['level_id']);
                    break;

                case 'drop':
                    $response = wpms_memberhsip_drop($_REQUEST['user_id']);
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
                    $response = false;

                    $user_ids = array_filter($_REQUEST['bulk-users-id'] ?? []);

                    if (!empty($user_ids)) {
                        if (strtolower($_REQUEST['bulk-action']) == 'drop') {
                            $response = true;
                            foreach ($user_ids as $user_id) {
                                $response &= wpms_memberhsip_drop($user_id);
                            }
                        }
                    }
            }

            $this->add_notices(
                $response ? 'success' : 'warning',
                $response ? __('Action was correctly executed', $this->context) : __('Action execution failed', $this->context)
            );

        });
    }

    public function render_sub_modules(): void
    {
        ?>
        <section class="wps-wrap">
            <block class="wps">
                <section class='wps-header'><h1><?php _e('Edit Members', 'wpms'); ?></h1></section>
                <?php

                echo Graphic::generateHTML_tabs_panels(array(
                    array(
                        'id'        => 'wpms-members-list',
                        'tab-title' => __('List', 'wpfs'),
                        'callback'  => array($this, 'render_list')
                    )
                ));
                ?>
            </block>
        </section>
        <?php
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
                <?php Actions::nonce_field($this->action_hook); ?>
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