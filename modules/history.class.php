<?php
/**
 * @author    sh1zen
 * @copyright Copyright (C) 2025.
 * @license   http://www.gnu.org/licenses/gpl.html GNU/GPL
 */

namespace WPMembership\modules;

use WPS\core\Graphic;
use WPS\modules\Module;

use WPMembership\modules\supporters\HistoryList;

class Mod_History extends Module
{
    public array $scopes = array('admin-page');

    protected string $context = 'wpmc';

    public function render_sub_modules(bool $standalone = true): void
    {
        ?>
        <section class="wps-wrap">
            <block class="wps">
                <?php

                echo Graphic::generateHTML_tabs_panels(array(
                    array(
                        'id'          => 'wpmc-history-list',
                        'tab-title'   => __('List', 'members-control'),
                        'callback'    => array($this, 'render_list'),
                        'panel-flush' => true
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
        require_once WPMC_SUPPORTERS . 'HistoryList.class.php';

        $table = new HistoryList(['action_hook' => $this->action_hook]);

        $table->prepare_items();
        ?>
        <form method="GET" class="wps wps-list-table-form wpmc-list-table-form" autocomplete="off" autocapitalize="off">
            <input type="hidden" name="page" value="<?php echo esc_attr($_REQUEST['page']); ?>"/>
            <?php $table->display(); ?>
        </form>
        <?php
        return ob_get_clean();
    }
}

return __NAMESPACE__;
