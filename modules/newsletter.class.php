<?php
/**
 * @author    sh1zen
 * @copyright Copyright (C) 2025.
 * @license   http://www.gnu.org/licenses/gpl.html GNU/GPL
 */

namespace WPMembership\modules;

use WPS\core\RequestActions;
use WPS\core\Graphic;
use WPS\core\StringHelper;
use WPS\core\TextReplacer;
use WPS\modules\Module;

use WPMembership\modules\supporters\MembersList;

class Mod_NewsLetter extends Module
{
    public static ?string $name = 'Newsletter';

    public array $scopes = array('admin-page', 'admin');

    protected string $context = 'wpmc';

    public function actions(): void
    {
        RequestActions::request($this->action_hook, function ($action) {

            $response = false;

            if ($action == 'send-emails' and !empty($_REQUEST['nwsl-subject']) and !empty($_REQUEST['nwsl-message'])) {

                require_once WPMC_SUPPORTERS . 'MembersList.class.php';

                $table = new MembersList(['action_hook' => $this->action_hook, 'context' => 'news']);

                $users_email = array_filter(array_column($table->get_items(), 'user_email'));

                $response = wps_multi_mail(
                    $users_email,
                    StringHelper::sanitize_text(
                        TextReplacer::replace($_REQUEST['nwsl-subject'])
                    ),
                    StringHelper::sanitize_text(
                        TextReplacer::replace($_REQUEST['nwsl-message']),
                        true
                    ),
                );
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
                <section class='wps-header'><h1><?php _e('Newsletter', 'members-control'); ?></h1></section>
                <?php
                echo Graphic::generateHTML_tabs_panels(array(
                    array(
                        'id'          => 'wpmc-members-list',
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
        require_once WPMC_SUPPORTERS . 'MembersList.class.php';

        $table = new MembersList(['action_hook' => $this->action_hook, 'context' => 'news']);

        $table->prepare_items();
        ?>
        <form method="GET" class="wps wps-list-table-form wpmc-list-table-form wpmc-newsletter-form" autocomplete="off" autocapitalize="off">
            <input type="hidden" name="page" value="<?php echo esc_attr($_REQUEST['page']); ?>"/>
            <?php $table->display(); ?>
            <?php RequestActions::nonce_field($this->action_hook); ?>
            <div class="wpmc-newsletter-composer">
                <input class="wps" type="text" name="nwsl-subject"
                       value="<?php echo esc_attr($_REQUEST['nwsl-subject'] ?? ''); ?>"
                       placeholder="<?php _e('E-mail subject', 'members-control'); ?>">
                <textarea class="wps" name="nwsl-message" rows="10"
                          placeholder="<?php _e('Message', 'members-control'); ?>"><?php echo esc_attr($_REQUEST['nwsl-message'] ?? ''); ?></textarea>
                <div class="wpmc-newsletter-actions">
                    <?php echo RequestActions::get_action_button($this->action_hook, "send-emails", __('Send now', 'members-control'), 'wps button-primary') ?>
                </div>
            </div>
        </form>
        <?php
        return ob_get_clean();
    }
}

return __NAMESPACE__;
