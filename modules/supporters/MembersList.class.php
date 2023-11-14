<?php
/**
 * @author    sh1zen
 * @copyright Copyright (C) 2024.
 * @license   http://www.gnu.org/licenses/gpl.html GNU/GPL
 */

namespace WPMembership\modules\supporters;

if (!class_exists('WP_List_Table')) {
    require_once(ABSPATH . 'wp-admin/includes/class-wp-list-table.php');
}

use WPS\core\RequestActions;
use WPS\core\Query;

class MembersList extends \WP_List_Table
{
    private string $action_hook;

    private string $action_page_hook;

    private string $context;

    public function __construct($args = array())
    {
        $this->modes = array(
            'list' => __('List view', 'wpms')
        );

        $this->action_hook = $args['action_hook'] ?? '';
        $this->context = ($args['context'] ?? 'manage');
        $this->action_page_hook = "$this->action_hook-page";

        parent::__construct(
            array(
                'singular' => __('member', 'wpms'),
                'plural'   => __('members', 'wpms'),
                'ajax'     => false,
                'screen'   => get_current_screen() ?? null,
            )
        );
    }

    public function column_subscription($member)
    {
        $member = wpms_get_member($member);

        $level_title = $member->get_sub()->get_level()->title ?: __('None', 'wpms');

        $output = "<strong>$level_title</strong>";

        if ($this->context === 'manage') {

            $row_actions = array();

            if ($member->has_subscription()) {
                $row_actions[] = "<span class='inline'><a href='" . RequestActions::get_url($this->action_page_hook, 'edit') . "&user_id=" . $member->get_user()->ID . "'>" . __('Edit', 'wpms') . "</a></span>";

                if ($member->is_suspended()) {
                    $row_actions[] = "<span class='inline'><a href='" . RequestActions::get_url($this->action_hook, 'resume_sub') . "&user_id=" . $member->get_user()->ID . "'>" . __('Resume', 'wpms') . "</a></span>";
                }
                else {
                    $row_actions[] = "<span class='inline'><a href='" . RequestActions::get_url($this->action_hook, 'suspend_sub') . "&user_id=" . $member->get_user()->ID . "'>" . __('Suspend', 'wpms') . "</a></span>";
                }

                $row_actions[] = "<span class='inline delete'><a href='" . RequestActions::get_url($this->action_hook, 'drop_sub') . "&user_id=" . $member->get_user()->ID . "'>" . __('Drop', 'wpms') . "</a></span>";
            }
            else {
                $row_actions[] = "<span class='inline'><a href='" . RequestActions::get_url($this->action_page_hook, 'add') . "&user_id=" . $member->get_user()->ID . "'>" . __('Add', 'wpms') . "</a></span>";
            }

            $output .= '<br><div class="row-actions">' . implode(' | ', $row_actions) . '</div>';
        }

        return $output;
    }

    public function column_username($member)
    {
        $member = wpms_get_member($member);

        $edit_link = admin_url('user-edit.php?user_id=' . $member->get_user()->ID);

        $output = '<strong><a href="' . esc_url($edit_link) . '" class="row-title">' . ucwords(strtolower(esc_html($member->get_user()->display_name))) . '</a></strong>';

        $row_actions[] = "<span class='edit'><a target='_blank' href='$edit_link'>" . __('Edit', 'wpms') . "</a></span>";

        if (in_array('author', $member->get_user()->roles)) {
            $row_actions[] = "<span class='inline'><a target='_blank' href='" . get_author_posts_url($member->get_user()->ID) . "'>" . __('View', 'wpms') . "</a></span>";
        }

        $output .= '<div class="row-actions">' . implode(' | ', $row_actions) . '</div>';

        return $output;
    }

    public function display_tablenav($which)
    {
        if ('top' == $which) {
            $this->search_box(__('Search', 'wpms'), 'wpms-al-search');
        }
        ?>
        <row class="tablenav <?php echo esc_attr($which); ?>">
            <?php if ($this->has_items()) : ?>
                <row class="alignleft actions bulkactions">
                    <?php $this->bulk_actions($which); ?>
                </row>
            <?php endif; ?>
            <?php
            $this->extra_tablenav($which);
            $this->pagination($which);
            ?>
            <br class="clear"/>
        </row>
        <?php
    }

    public function search_box($text, $input_id)
    {
        $search_data = isset($_REQUEST['s']) ? sanitize_text_field($_REQUEST['s']) : '';

        $input_id = $input_id . '-search-input';
        ?>
        <p class="search-box">
            <label class="screen-reader-text" for="<?php echo $input_id ?>"><?php echo $text; ?>:</label>
            <input type="search" id="<?php echo $input_id ?>" name="s" value="<?php echo esc_attr($search_data); ?>"/>
            <?php submit_button($text, 'button', false, false, array('id' => 'search-submit')); ?>
        </p>
        <?php
    }

    protected function bulk_actions($which = '')
    {
        if (is_null($this->_actions)) {
            $this->_actions = $this->get_bulk_actions();
        }

        if (empty($this->_actions)) {
            return;
        }

        echo '<label for="bulk-action-selector-' . esc_attr($which) . '" class="screen-reader-text">' . __('Select bulk action') . '</label>';
        echo "<select name='bulk-action' id='bulk-action-selector-" . esc_attr($which) . "'>";
        echo '<option value="-1">' . __('Bulk actions') . "</option>";

        foreach ($this->_actions as $key => $value) {
            if (is_array($value)) {
                echo "<optgroup label='" . esc_attr($key) . "'>";

                foreach ($value as $name => $title) {
                    $class = ('edit' === $name) ? ' class="hide-if-no-js"' : '';

                    echo "<option value='" . esc_attr($name) . "' $class>$title</option>";
                }
                echo "</optgroup>";
            }
            else {
                $class = ('edit' === $key) ? ' class="hide-if-no-js"' : '';

                echo '<option value="' . esc_attr($key) . '"' . $class . '>' . $value . "</option>";
            }
        }

        echo "</select>";

        submit_button(__('Apply'), 'action', $this->action_hook, false);
    }

    public function extra_tablenav($which)
    {
        if ('top' === $which) {
            $this->extra_tablenav_header();
        }
        elseif ('bottom' === $which) {
            echo '<br>';
            $this->extra_tablenav_footer();
        }
    }

    private function extra_tablenav_header()
    {
        echo '<div class="alignleft actions">';
        echo '<select name="filter_level">';
        printf('<option value="">%s</option>', __('Filter by subscription', 'wpms'));
        printf('<option value="0"%s>%s</option>', selected($_REQUEST['filter_level'] ?? '', '0', false), __('View Inactive Users', 'wpms'));
        foreach (wpms_get_levels() as $level) {
            printf('<option value="%s"%s>%s</option>', $level->id, selected($_REQUEST['filter_level'] ?? '', $level->id, false), ucwords($level->title));
        }
        echo '</select>';
        submit_button(__('Filter', 'wpms'), 'button', '', false);
        echo '</div>';

        echo '<div class="alignleft actions">';
        echo '<select name="filter_role">';
        printf('<option value="">%s</option>', __('Filter by User Role', 'wpms'));
        foreach (wp_roles()->roles as $role_slug => $role_details) {
            printf('<option value="%s"%s>%s</option>', $role_slug, selected($_REQUEST['filter_role'] ?? '', $role_slug, false), ucwords($role_details['name']));
        }
        echo '</select>';
        submit_button(__('Filter', 'wpms'), 'button', '', false);
        echo '</div>';

        echo '<div class="alignleft actions">';
        echo '<select name="filter_expiring">';
        printf('<option value="">%s</option>', __('Filter Expiring', 'wpms'));
        printf('<option value="7"%s>%s</option>', selected($_REQUEST['filter_expiring'] ?? '0', '7', false), __('7 Days', 'wpms'));
        printf('<option value="30"%s>%s</option>', selected($_REQUEST['filter_expiring'] ?? '0', '30', false), __('30 Days', 'wpms'));
        printf('<option value="60"%s>%s</option>', selected($_REQUEST['filter_expiring'] ?? '0', '60', false), __('60 Days', 'wpms'));
        printf('<option value="90"%s>%s</option>', selected($_REQUEST['filter_expiring'] ?? '0', '90', false), __('90 Days', 'wpms'));
        echo '</select>';
        submit_button(__('Filter', 'wpms'), 'button', '', false);
        echo '</div>';
    }

    public function extra_tablenav_footer()
    {
        if ($this->context != 'manage') {
            return;
        }

        $actions = [
            'csv'        => 'CSV',
            'json'       => 'JSON',
            'xml'        => 'XML',
            'ods'        => 'Spreadsheet',
            'serialized' => 'Serialized',
            'php_array'  => 'PHP ARRAY'
        ];
        ?>
        <div class="alignleft actions recordactions">
            <select name="export-format">
                <option value=""><?php echo esc_attr__('Export File Format', 'wpms'); ?></option>
                <?php foreach ($actions as $action_key => $action_title) : ?>
                    <option value="<?php echo esc_attr($action_key); ?>"><?php echo esc_html($action_title); ?></option>
                <?php endforeach; ?>
            </select>
        </div>
        <button class="button button-primary" type="submit" name="<?php echo $this->action_hook; ?>" value="export">
            <?php _e('Export Data', 'wpms') ?>
        </button>
        <?php
    }

    public function column_default($item, $column_name)
    {
        $item = wpms_get_member($item);

        switch ($column_name) {

            case 'user_name':
                $return = '<span>' . ucwords(strtolower(esc_html($item->get_user()->first_name . ' ' . $item->get_user()->last_name))) . '</span>';
                break;

            case 'email':
                $return = '<span>' . esc_html($item->get_user()->user_email) . '</span>';
                break;

            case 'expire':
                if ($item->get_sub()->is_suspended()) {
                    $return = "<span>" . __('Suspended', 'wpms') . "</span>";
                }
                else {
                    if ($expire = $item->get_sub()->end_time()) {
                        $expire = date("Y-m-d H:i", $expire);
                    }
                    $return = "<span>" . ($expire ?: __('No', 'wpms')) . "</span>";
                }
                break;

            case 'renew_count':
                $return = "<span>" . $item->renew_count() . " / " . $item->get_pays() . " € </span>";
                break;

            case 'posts':
                $return = "<span>" . $item->post_count() . "</span>";
                break;

            default:
                $return = '<span>' . __('None', 'wpms') . '</span>';
        }

        return $return;
    }

    public function get_items($use_limit = false)
    {
        $query = $this->parse_query()->output(ARRAY_A)->action('select');

        $query->columns(['ID', 'display_name', 'user_email'], $query->wpdb()->users);
        $query->columns(['expirydate'], WP_MEMBERSHIP_TABLE_SUBSCRIPTIONS, true);

        $query->groupby([$query->wpdb()->users => 'ID']);

        if (isset($_REQUEST['bulk-elements'])) {

            $items = array_filter($_REQUEST['bulk-elements'], 'is_numeric');

            $this->items = $query->where([$query->wpdb()->users => ['ID' => $items]]);

            return $query->query();
        }

        $offset = ($this->get_pagenum() - 1) * 25;

        if ($use_limit) {
            $query->limit(25);
        }

        return $query->offset($offset)->query();
    }

    private function parse_query($request = ''): Query
    {
        if (empty($request)) {
            $request = $_REQUEST;
        }

        $query = Query::getInstance(OBJECT, true);

        $query->tables($query->wpdb()->users);
        $query->join($query->wpdb()->users, WP_MEMBERSHIP_TABLE_SUBSCRIPTIONS, ['ID' => 'user_id'], 'LEFT JOIN');

        $query->orderby(
            match ($request['orderby'] ?? 'id') {
                'expire' => [WP_MEMBERSHIP_TABLE_SUBSCRIPTIONS => 'expirydate'],
                default => [$query->wpdb()->users => 'ID'],
            },
            $request['order'] ?? 'DESC'
        );

        if (isset($request['filter_level'])) {
            if ($request['filter_level'] == '0') {
                $query->where(
                    ['ID' => Query::getInstance()->select('DISTINCT user_id', WP_MEMBERSHIP_TABLE_SUBSCRIPTIONS)->compile(), 'compare' => 'NOT IN'],
                    'AND',
                    $query->wpdb()->users
                );
            }
            elseif (!empty($request['filter_level'])) {
                $query->where(['level_id' => $request['filter_level']], 'AND', WP_MEMBERSHIP_TABLE_SUBSCRIPTIONS);
            }
        }

        if (!empty($request['filter_role'])) {
            $query->tables($query->wpdb()->usermeta, [$query->wpdb()->usermeta => 'user_id', $query->wpdb()->users => 'ID']);
            $query->where([$query->wpdb()->usermeta => [['meta_key' => 'wp_capabilities'], ['meta_value' => $request['filter_role'], 'compare' => 'LIKE']]]);
        }

        if (!empty($request['filter_expiring'])) {
            $query->where([
                'expirydate' => [wps_time('mysql'), wps_time('mysql', DAY_IN_SECONDS * absint($request['filter_expiring']))], 'compare' => 'BETWEEN'
            ], WP_MEMBERSHIP_TABLE_SUBSCRIPTIONS);
        }

        if (!empty($request['s'])) {
            $query->where(
                [
                    ['user_nicename' => $request['s'], 'compare' => 'LIKE'],
                    ['user_email' => $request['s'], 'compare' => 'LIKE'],
                    ['display_name' => $request['s'], 'compare' => 'LIKE'],
                    ['ID' => $request['s']]
                ],
                'OR',
                $query->wpdb()->users
            );
        }

        return $query;
    }

    public function prepare_items()
    {
        $query = $this->parse_query();

        $this->_column_headers = array($this->get_columns(), [], $this->get_sortable_columns(), $this->get_primary_column_name());

        $offset = ($this->get_pagenum() - 1) * 25;

        $total_items = $query->action('select')->columns('COUNT(*)')->query_one();

        $this->items = $query->limit(25)->offset($offset)->columns('*')->recompile()->query();

        $this->set_pagination_args(array(
            'total_items' => $total_items,
            'per_page'    => 25,
            'total_pages' => ceil($total_items / 25),
        ));
    }

    public function get_columns()
    {
        switch ($this->context) {
            case 'news':
                $columns = [
                    'cb'           => '<input type="checkbox" />',
                    'username'     => __('Username', 'wpms'),
                    'user_name'    => __('Name', 'wpms'),
                    'email'        => __('E-mail', 'wpms'),
                    'subscription' => __('Subscription', 'wpms'),
                    'expire'       => __('Expire', 'wpms'),
                ];
                break;

            default:
                $columns = [
                    'username'     => __('Username', 'wpms'),
                    'user_name'    => __('Name', 'wpms'),
                    'email'        => __('E-mail', 'wpms'),
                    'subscription' => __('Subscription', 'wpms'),
                    'expire'       => __('Expire', 'wpms'),
                    'renew_count'  => __('Renew / Payments', 'wpms'),
                    'posts'        => __('Posts', 'wpms'),
                ];
        }

        return $columns;
    }

    protected function get_sortable_columns()
    {
        return array(
            'expire' => array('expire', 'desc'),
        );
    }

    /**
     * Render the bulk edit checkbox
     *
     * @param array $item
     *
     * @return string
     */
    function column_cb($item)
    {
        return sprintf('<input type="checkbox" name="bulk-elements[]" value="%s" />', $item->ID);
    }

    public function no_items()
    {
        _e('No Users found.', 'wpms');
    }
}