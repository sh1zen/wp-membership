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

            $user_ids = array_filter($_REQUEST['bulk-users-id'] ?? []);

            if (empty($user_ids)) {
                return;
            }

            switch (strtolower($_REQUEST['action2'])) {
                case 'drop':
                    $response = true;
                    foreach ($user_ids as $user_id) {
                        $response &= wpms_memberhsip_drop($user_id);
                    }
                    break;
                default:
                    $response = false;
            }

            if ($response) {
                $this->add_notices('success', __('Action was correctly executed', $this->context));
            }
            else {
                $this->add_notices('warning', __('Action execution failed', $this->context));
            }

        }, true, true);

        Actions::request($this->action_hook, function ($action) {

            $query = Query::getInstance()->tables(WP_MEMBERSHIP_TABLE_LEVELS);

            switch ($action) {

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
                    require_once WPMS_SUPPORTERS . 'MembersList.class.php';

                    $table = new MembersList(['action_hook' => $this->action_hook]);
                    $exporter = new Exporter();

                    $exporter->format($_REQUEST['export-format'] ?: 'csv')->set_data($table->get_items())->prepare()->download('levels-list');
                    $response = true;
                    break;

                default:
                    $response = false;
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