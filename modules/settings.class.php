<?php
/**
 * @author    sh1zen
 * @copyright Copyright (C) 2025.
 * @license   http://www.gnu.org/licenses/gpl.html GNU/GPL
 */

namespace WPMembership\modules;

use WPS\core\Ajax;
use WPS\core\RequestActions;
use WPS\core\addon\Exporter;
use WPS\core\Graphic;
use WPS\core\Rewriter;
use WPS\modules\Module;

class Mod_Settings extends Module
{
    public array $scopes = array('core-settings', 'admin', 'ajax');

    protected string $context = 'wpmc';

    public function restricted_access($context = ''): bool
    {
        switch ($context) {

            case 'ajax':
            case 'settings':
                return !current_user_can('manage_options');

            default:
                return false;
        }
    }

    public function actions(): void
    {
        RequestActions::request($this->action_hook, function ($action) {

            $response = false;

            switch ($action) {

                case 'reset_options':
                    $response = wps('wpmc')->settings->reset();
                    Rewriter::reload();
                    break;

                case 'restore_options':
                    $response = wps('wpmc')->moduleHandler->upgrade();
                    break;

                case 'export_options':

                    require_once WPS_ADDON_PATH . 'Exporter.class.php';

                    $exporter = new Exporter();

                    $exporter->set_raw(wps('wpmc')->settings->export());
                    $exporter->format('text');
                    $exporter->download('wpmc-export.conf');

                    unset($exporter);

                    break;

                case 'import_options':
                    $response = wps('wpmc')->settings->import($this->read_import_configuration());
                    $response &= wps('wpmc')->moduleHandler->upgrade();
                    break;
            }

            if ($response) {
                $this->add_notices('success', __('Action was correctly executed', 'members-control'));
            }
            else {
                $this->add_notices('warning', __('Action execution failed', 'members-control'));
            }
        });
    }

    public function ajax_handler($args = array()): void
    {
        if (($args['action'] ?? '') !== 'autosave_settings') {
            parent::ajax_handler($args);
            return;
        }

        parse_str((string)($args['form_data'] ?? ''), $form_data);

        $context = wps('wpmc')->settings->get_context();
        $payload = $form_data[$context] ?? [];
        $module_slug = is_array($payload) ? sanitize_key($payload['change'] ?? '') : '';

        if (!$module_slug && !empty($form_data['option_panel'])) {
            $module_slug = sanitize_key(preg_replace('#^settings-#', '', (string)$form_data['option_panel']));
        }

        if (!$module_slug && is_array($payload) && $this->looks_like_modules_handler_payload($payload)) {
            $module_slug = 'modules_handler';
        }

        if (!$module_slug || !is_array($payload)) {
            Ajax::response([
                'text' => __('Cannot detect which module must be saved.', 'members-control'),
            ], 'error');
        }

        $module = wps('wpmc')->moduleHandler->get_module_instance($module_slug);

        if (is_null($module) || $module->restricted_access('settings')) {
            Ajax::response([
                'text' => __('Invalid module settings payload.', 'members-control'),
            ], 'error');
        }

        $valid = $module->validate_settings($payload);
        $saved = wps('wpmc')->settings->get($module->slug, []) == $valid || wps('wpmc')->settings->update($module->slug, $valid, true);

        if (!$saved) {
            Ajax::response([
                'text' => __('Autosave failed while updating settings.', 'members-control'),
            ], 'error');
        }

        Ajax::response([
            'text'   => __('Settings autosaved.', 'members-control'),
            'module' => $module->slug,
        ], 'success');
    }

    private function looks_like_modules_handler_payload(array $payload): bool
    {
        foreach (wps('wpmc')->moduleHandler->get_modules('all', false) as $module) {
            if (array_key_exists($module['slug'], $payload)) {
                return true;
            }
        }

        return false;
    }

    private function read_import_configuration(): string
    {
        $uploaded_file = $_FILES['conf_file'] ?? null;

        if (is_array($uploaded_file) && (int)($uploaded_file['error'] ?? UPLOAD_ERR_NO_FILE) === UPLOAD_ERR_OK) {
            $tmp_name = (string)($uploaded_file['tmp_name'] ?? '');

            if ($tmp_name !== '' && is_uploaded_file($tmp_name)) {
                return (string)file_get_contents($tmp_name);
            }
        }

        return (string)($_REQUEST['conf_data'] ?? '');
    }

    protected function print_footer(): string
    {
        ob_start();
        ?>
        <form method="POST" autocapitalize="off" autocomplete="off" enctype="multipart/form-data">

            <?php RequestActions::nonce_field($this->action_hook); ?>

            <block class="wps-gridRow wps-settings-setup wps-settings-transfer wpmc-settings-transfer">
                <row class="wps-custom-action wps-settings-actions wps-row">
                    <button type="submit" name="<?php echo esc_attr($this->action_hook); ?>" value="reset_options" class="wps wps-button wpmc-btn is-danger">
                        <span class="dashicons dashicons-trash"></span>
                        <span><?php esc_html_e('Reset Plugin options', 'members-control'); ?></span>
                    </button>
                    <button type="submit" name="<?php echo esc_attr($this->action_hook); ?>" value="restore_options" class="wps wps-button wpmc-btn is-neutral">
                        <span class="dashicons dashicons-update-alt"></span>
                        <span><?php esc_html_e('Restore Plugin options', 'members-control'); ?></span>
                    </button>
                    <button type="submit" name="<?php echo esc_attr($this->action_hook); ?>" value="export_options" class="wps wps-button wpmc-btn is-info">
                        <span class="dashicons dashicons-upload"></span>
                        <span><?php esc_html_e('Export Plugin options', 'members-control'); ?></span>
                    </button>
                </row>
                <row class="wps-custom-action wps-settings-import wps-row">
                    <label class="wps-import-dropzone" for="wpmc-conf-file">
                        <span class="wps-import-dropzone-icon"><?php echo Graphic::icon('external'); ?></span>
                        <span class="wps-import-dropzone-copy">
                            <strong><?php esc_html_e('Drop configuration file here', 'members-control'); ?></strong>
                            <small><?php esc_html_e('or choose the exported .conf file from your computer.', 'members-control'); ?></small>
                        </span>
                        <input id="wpmc-conf-file" class="wps-import-file" type="file" name="conf_file" accept=".conf,text/plain">
                    </label>
                    <textarea class="wps-import-legacy-field screen-reader-text" name="conf_data" aria-hidden="true" tabindex="-1"></textarea>
                    <span class="wps-import-divider"><span><?php esc_html_e('or', 'members-control'); ?></span></span>
                    <div class="wps-import-action">
                        <button type="submit" name="<?php echo esc_attr($this->action_hook); ?>" value="import_options" class="wps wps-button wpmc-btn is-info">
                            <span class="dashicons dashicons-upload"></span>
                            <span><?php esc_html_e('Import Plugin options', 'members-control'); ?></span>
                        </button>
                        <p><?php esc_html_e('Import settings from a .conf file to apply saved configuration.', 'members-control'); ?></p>
                    </div>
                </row>
            </block>
        </form>
        <?php
        return ob_get_clean();
    }
}

return __NAMESPACE__;
