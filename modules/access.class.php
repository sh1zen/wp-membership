<?php
/**
 * @author    sh1zen
 * @copyright Copyright (C) 2025.
 * @license   http://www.gnu.org/licenses/gpl.html GNU/GPL
 */

namespace WPMembership\modules;

use WPMembership\core\MembershipLevel;
use WPS\core\Graphic;
use WPS\core\Query;
use WPS\core\UtilEnv;
use WPS\core\Utility;
use WPS\modules\Module;

class Mod_Access extends Module
{
    public static ?string $name = 'Access';

    public array $scopes = array('admin-page', 'settings', 'autoload');

    protected string $context = 'wpmc';

    private array $performer_response = array();

    public function render_sub_modules(bool $standalone = true): void
    {
        ?>
        <section class="wps-wrap">
            <div id="wps-ajax-message" class="wps-notice"></div>
            <?php
            if (!empty($this->performer_response)) {

                echo '<div id="message" class="wps-notice">';

                foreach ($this->performer_response as $response) {
                    list($text, $status) = $response;

                    echo "<p class='{$status}'> {$text} </p>";
                }

                echo '</div>';
            }
            ?>
            <block class="wps">
                <form id="wps-options" action="options.php" method="post">
                    <input type="hidden" name="<?php echo wps('wpmc')->settings->get_context() . "[change]" ?>"
                           value="<?php echo $this->slug; ?>">
                    <?php

                    settings_fields('wpmc-settings');

                    echo Graphic::generateHTML_tabs_panels(array(
                            array(
                                    'id'        => 'acc-general',
                                    'tab-title' => __('Config', 'members-control'),
                                    'callback'  => array($this, 'render_settings'),
                                    'args'      => array('general')
                            ),
                            array(
                                    'id'        => 'acc-special',
                                    'tab-title' => __('Special pages', 'members-control'),
                                    'callback'  => array($this, 'render_settings'),
                                    'args'      => array('special')
                            ),
                            array(
                                    'id'        => 'acc-post-type',
                                    'tab-title' => __('Post types', 'members-control'),
                                    'callback'  => array($this, 'render_settings'),
                                    'args'      => array(get_post_types(array('public' => true)))
                            ),
                            array(
                                    'id'        => 'acc-archives',
                                    'tab-title' => __('Archives', 'members-control'),
                                    'callback'  => array($this, 'render_settings'),
                                    'args'      => array('archives')
                            ),
                            array(
                                    'id'        => 'acc-tax',
                                    'tab-title' => __('Taxonomy', 'members-control'),
                                    'callback'  => array($this, 'render_settings'),
                                    'args'      => array(get_taxonomies(array('public' => true)))
                            ),
                    ));
                    ?>
                    <p class="wps-submit">
                        <input type="submit" class="button-primary" value="<?php _e('Save Changes', 'members-control') ?>"/>
                    </p>
                </form>
            </block>
        </section>
        <?php
    }

    /**
     * overwrite the base render setting gui for settings
     */
    public function render_settings($filter = ''): string
    {
        $_setting_fields = $this->setting_fields($filter);

        ob_start();

        if (!empty($_setting_fields)) {

            ?>
            <block class="wps-options">
                <?php Graphic::generate_fields($_setting_fields, $this->infos(), array('name_prefix' => wps('wpmc')->settings->get_context())); ?>
            </block>
            <?php
        }

        return ob_get_clean();
    }

    private function build_modifiers($context): array
    {
        static $levels;

        if (!isset($levels)) {
            $levels = Query::getInstance()->tables(WP_MEMBERSHIP_TABLE_LEVELS)->orderby('id', $request['order'] ?? 'ASC')->where(['active' => '1'])->query();

            $levels = array_map(fn($level) => new MembershipLevel($level), $levels);
        }

        $export = array();

        $export[] = $this->setting_field(__("Status", 'members-control'), "{$context}.active", 'checkbox', ['default_value' => false]);

        foreach ($levels as $level) {
            $export[] = $this->setting_field($level->title, "{$context}.levels.{$level->id}", 'checkbox', ['parent' => "$context.active", 'default_value' => true]);
        }

        return $export;
    }

    protected function setting_fields($filter = ''): array
    {
        static $fields;

        if (!isset($fields)) {

            $fields = [];

            $fields['general'] = $this->group_setting_fields(
                    $this->setting_field(__('Config:', 'members-control'), false, 'separator'),
                    $this->setting_field(__('No Subscription', 'members-control'), 'no_member', 'text', ['default_value' => wp_login_url()]),
                    $this->setting_field(__('Suspended', 'members-control'), 'suspended', 'text', ['default_value' => wp_login_url()]),
                    $this->setting_field(__('Restricted level', 'members-control'), 'restricted', 'text', ['default_value' => wp_login_url()]),
            );

            $fields['special'] = $this->group_setting_fields(
                    $this->group_setting_fields(
                            $this->setting_field(__('Home:', 'members-control'), false, 'separator'),
                            ...$this->build_modifiers("home")
                    ),
                    $this->group_setting_fields(
                            $this->setting_field(__('Search page:', 'members-control'), false, 'separator'),
                            ...$this->build_modifiers("search")
                    ),
                    $this->group_setting_fields(
                            $this->setting_field(__('Admin:', 'members-control'), false, 'separator'),
                            ...$this->build_modifiers("admin")
                    ),
            );

            $fields['archives'] = $this->group_setting_fields(
                    $this->group_setting_fields(
                            $this->setting_field(__('Date:', 'members-control'), false, 'separator'),
                            ...$this->build_modifiers("archives.date")
                    ),
                    $this->group_setting_fields(
                            $this->setting_field(__('Author:', 'members-control'), false, 'separator'),
                            ...$this->build_modifiers("archives.author")
                    ),
            );

            //settings for each taxonomy
            foreach (get_taxonomies(array('public' => true), 'objects') as $tax_type_object) {

                $field_name = "tax.$tax_type_object->name";

                $fields[$tax_type_object->name] = $this->group_setting_fields(

                        $this->group_setting_fields(
                                $this->setting_field(ucwords($tax_type_object->label) . ($tax_type_object->_builtin ? "" : " ({$tax_type_object->name})"), false, 'separator'),
                                ...$this->build_modifiers($field_name)
                        ),
                );
            }

            //settings for each post type
            foreach (get_post_types(array('public' => true), 'objects') as $post_type_object) {

                $post_type = $post_type_object->name;

                if ($post_type_object->has_archive) {

                    $archive_name = ucwords($post_type_object->label) . ($post_type_object->_builtin ? "" : " ({$post_type_object->name})");

                    $fields['archives'][] =
                            $this->group_setting_fields(
                                    /* translators: 1: archive name. */
                                    $this->setting_field(sprintf(__('%s archive:', 'members-control'), $archive_name), false, 'separator'),
                                    ...$this->build_modifiers("archives.$post_type")
                            );
                }

                $fields[$post_type] = $this->group_setting_fields(

                        $this->group_setting_fields(
                                $this->setting_field(ucwords($post_type_object->label) . ($post_type_object->_builtin ? "" : " ($post_type_object->name)"), false, 'separator'),
                                ...$this->build_modifiers("post_type.$post_type")
                        )
                );
            }
        }

        return $this->group_setting_sections($fields, $filter);
    }

    public function restricted_access($context = 'settings'): bool
    {
        switch ($context) {

            case 'settings':
            case 'render-admin':
            case 'ajax':
                return !current_user_can('manage_options');

            default:
                return false;
        }
    }

    private function restrict_access($user, $levels): void
    {
        $options = wps('wpmc')->settings;

        $member = wpmc_get_member($user);

        if (!$member) {
            Utility::getInstance()->rewriter->redirect($options->get('access.general.no_member'));
            exit(0);
        }

        if ($member->is_suspended()) {
            Utility::getInstance()->rewriter->redirect($options->get('access.general.suspended'));
            exit(0);
        }

        if (!$levels[$member->get_level()->id ?? -1] ?? true){
            Utility::getInstance()->rewriter->redirect($options->get('access.general.restricted'));
            exit(0);
        }
    }

    public function access_checker(): void
    {
        $options = wps('wpmc')->settings;

        if (!$options->get("modules_handler.access", false)) {
            return;
        }

        $user = wp_get_current_user();

        if ($user and $user->has_cap("administrator")) {
            return;
        }

        if ((is_home() || is_front_page()) and $options->get('access.home.active')) {
            $levels = $options->get('access.home.levels', false) ?: [];
            $this->restrict_access($user, $levels);

        }
        elseif (is_search() and $options->get('access.search.active')) {
            $levels = $options->get('access.search.levels', false) ?: [];
            $this->restrict_access($user, $levels);

        }
        elseif (is_admin() and $options->get('access.admin.active')) {
            $levels = $options->get('access.admin.levels', false) ?: [];
            $this->restrict_access($user, $levels);

        }
        elseif (is_author() and $options->get('access.archives.author.active')) {
            $levels = $options->get('access.archives.author.levels', false) ?: [];
            $this->restrict_access($user, $levels);

        }
        elseif (is_date() and $options->get('access.archives.date.active')) {
            $levels = $options->get('access.archives.date.levels', false) ?: [];
            $this->restrict_access($user, $levels);

        }
        elseif (is_category() || is_tag() || is_tax()) {
            $term = get_queried_object();
            if ($options->get("access.tax.$term->name")) {
                $levels = $options->get("access.tax.$term->name", false) ?: [];
                $this->restrict_access($user, $levels);
            }
        }
        elseif (is_singular()) {
            $post_type = get_post_type();
            if ($options->get("access.post_type.$post_type.active")) {
                $levels = $options->get("access.post_type.$post_type.levels", false) ?: [];
                $this->restrict_access($user, $levels);
            }
        }
        elseif (is_post_type_archive()) {
            $post_type = get_query_var('post_type');
            if ($options->get("access.archives.$post_type.active")) {
                $levels = $options->get("access.archives.$post_type.levels", false) ?: [];
                $this->restrict_access($user, $levels);
            }
        }
    }

    protected function init(): void
    {
        add_action("wp", [$this, 'access_checker']);
    }
}

return __NAMESPACE__;
