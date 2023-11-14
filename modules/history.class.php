<?php
/**
 * @author    sh1zen
 * @copyright Copyright (C) 2023.
 * @license   http://www.gnu.org/licenses/gpl.html GNU/GPL
 */

namespace WPMembership\modules;

use WPS\core\Graphic;
use WPS\modules\Module;

use WPMembership\modules\supporters\History;

class Mod_History extends Module
{
    public array $scopes = array('admin-page', 'autoload');

    protected string $context = 'wpms';

    public function render_admin_page(): void
    {
        ?>
        <section class="wps-wrap">
            <block class="wps">
                <section class='wps-header'><h1><?php _e('Subscriptions History', 'wpms'); ?></h1></section>
                <?php

                echo Graphic::generateHTML_tabs_panels(array(
                    array(
                        'id'        => 'wpms-history-list',
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
        require_once WPMS_SUPPORTERS . 'History.class.php';

        $table = new History(['action_hook' => $this->action_hook]);

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