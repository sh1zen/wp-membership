<?php
/**
 * @author    sh1zen
 * @copyright Copyright (C) 2024.
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
    public static ?string $name = 'News Letter';

    public array $scopes = array('admin-page', 'admin');

    protected string $context = 'wpms';

    public function actions(): void
    {
        RequestActions::request($this->action_hook, function ($action) {

            $response = false;

            if ($action == 'send-emails' and !empty($_REQUEST['nwsl-subject']) and !empty($_REQUEST['nwsl-message'])) {

                require_once WPMS_SUPPORTERS . 'MembersList.class.php';

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
                $response ? __('Action was correctly executed', $this->context) : __('Action execution failed', $this->context)
            );

        }, false, true);
    }

    public function render_sub_modules(): void
    {
        ?>
        <section class="wps-wrap">
            <block class="wps">
                <section class='wps-header'><h1><?php _e('News Letter', 'wpms'); ?></h1></section>
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

        $table = new MembersList(['action_hook' => $this->action_hook, 'context' => 'news']);

        $table->prepare_items();
        ?>
        <block class="wps-boxed--light">
            <form method="GET" class="wps" autocomplete="off" autocapitalize="off">
                <input type="hidden" name="page" value="<?php echo esc_attr($_REQUEST['page']); ?>"/>
                <?php $table->display(); ?>
                <?php RequestActions::nonce_field($this->action_hook); ?>
                <input class="wps" type="text" name="nwsl-subject"
                       value="<?php echo esc_attr($_REQUEST['nwsl-subject'] ?? ''); ?>"
                       placeholder="<?php _e('E-mail subject', 'wpopt'); ?>">
                <br>
                <textarea class="wps" name="nwsl-message" rows="10"
                          placeholder="<?php _e('Message', 'wpopt'); ?>"><?php echo esc_attr($_REQUEST['nwsl-message'] ?? ''); ?></textarea>
                <br>
                <block class="wps-gridRow" style="justify-content: center">
                    <?php echo RequestActions::get_action_button($this->action_hook, "send-emails", __('Send now', 'wpopt'), 'wps button-primary') ?>
                </block>
            </form>
        </block>
        <?php
        return ob_get_clean();
    }
}

return __NAMESPACE__;