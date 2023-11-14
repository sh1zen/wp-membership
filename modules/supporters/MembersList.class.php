<?php
/**
 * @author    sh1zen
 * @copyright Copyright (C) 2023.
 * @license   http://www.gnu.org/licenses/gpl.html GNU/GPL
 */

namespace WPMembership\modules\supporters;

if (!class_exists('WP_List_Table')) {
    require_once(ABSPATH . 'wp-admin/includes/class-wp-list-table.php');
}

use WPS\core\Actions;
use WPS\core\Query;

class MembersList extends \WP_List_Table
{
    private string $action_hook;

    public function __construct($args = array())
    {
        $this->modes = array(
            'list' => __('List view', 'wpms'),
        );

        $this->action_hook = $args['action_hook'] ?? '';

        parent::__construct(
            array(
                'singular' => __('member', 'wpms'),
                'plural'   => __('members', 'wpms'),
                'ajax'     => false,
                'screen'   => get_current_screen() ?? null,
            )
        );
    }

    public function column_subscription($user)
    {
        $subscription = wpms_get_user_subscription($user);

        $level_title = $subscription ? wpms_get_level($subscription->level_id)->title : __('None', 'wpms');

        $output = "<strong>$level_title</strong>";

        $row_actions = array();

        if ($subscription) {

            if ($subscription->level_id) {
                $row_actions[] = "<span class='inline delete'><a href='" . Actions::get_url($this->action_hook, 'drop') . "&sub_id=$subscription->id" . "'>" . __('Drop', 'wpms') . "</a></span>";
            }
            else {
                $row_actions[] = "<span class='inline'><a href='" . Actions::get_url($this->action_hook, 'add') . "&sub_id=$subscription->id" . "'>" . __('Add', 'wpms') . "</a></span>";
            }
        }
        else {
            $row_actions[] = "<span class='inline'><a href='" . Actions::get_url($this->action_hook, 'add') . "&sub_id=0" . "'>" . __('Add', 'wpms') . "</a></span>";
        }

        $output .= '<br><div class="row-actions">' . implode(' | ', $row_actions) . '</div>';

        return $output;
    }

    public function column_username($user)
    {
        $edit_link = admin_url('user-edit.php?user_id=' . $user->ID);

        $output = '<strong><a href="' . esc_url($edit_link) . '" class="row-title">' . esc_html($user->display_name) . '</a></strong>';

        $row_actions = [
            "<span class='edit'><a href='$edit_link'>" . __('Edit', 'wpms') . "</a></span>"
        ];

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

    public function extra_tablenav($which)
    {
        if ('top' !== $which) {
            if ('bottom' === $which) {
                $this->extra_tablenav_footer();
            }
            return;
        }

        echo '<div class="alignleft actions">';

        echo '<select name="filter_level">';
        printf('<option value="">%s</option>', __('Filter by subscription', 'wpms'));
        printf('<option value="0"%s>%s</option>', selected($_REQUEST['filter_level'] ?? '0', '0', false), __('View Inactive Users', 'wpms'));
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
    }

    public function extra_tablenav_footer()
    {
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
        switch ($column_name) {

            case 'user_name':
                $return = '<span>' . wps_get_user($item->ID)->first_name . ' ' . wps_get_user($item->ID)->last_name . '</span>';
                break;

            case 'email':
                $return = '<span>' . esc_html($item->user_email) . '</span>';
                break;

            case 'expire':
                $return = "<span>" . (wpms_get_user_subscription($item->ID)->expirydate ?? __('No', 'wpms')) . "</span>";
                break;

            case 'renew_count':
                $count = Query::getInstance()->select('COUNT(*)', WP_MEMBERSHIP_TABLE_HISTORY)->where(['user_id' => $item->ID, 'action' => 'join'])->query(true);
                $return = "<span>$count</span>";
                break;

            default:
                $return = '<span>' . esc_html($item->$column_name ?? 'N/B') . '</span>';
        }

        return $return;
    }

    public function get_items($use_limit = false)
    {
        // get requested order and other filters from _wp_http_referer
        parse_str(parse_url($_REQUEST['_wp_http_referer'] ?? '', PHP_URL_QUERY), $request);

        $query = $this->parse_query($request)->output(ARRAY_A);

        $offset = ($this->get_pagenum() - 1) * 25;

        if ($use_limit) {
            $query->limit(25);
        }

        return $query->offset($offset)->action('select')->columns('*')->query();
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

        if (!empty($request['filter_level'])) {
            $query->where([WP_MEMBERSHIP_TABLE_SUBSCRIPTIONS => ['level_id' => $request['filter_level']]]);
        }

        if (!empty($request['filter_role'])) {
            $query->tables($query->wpdb()->usermeta, [$query->wpdb()->usermeta => 'user_id', $query->wpdb()->users => 'ID']);
            $query->where([$query->wpdb()->usermeta => [['meta_key' => 'wp_capabilities'], ['meta_value' => $request['filter_role'], 'compare' => 'LIKE']]]);
        }

        if (!empty($request['s'])) {
            $query->where(
                [
                    $query->wpdb()->users => ['user_nicename' => $request['s'], 'compare' => 'LIKE'],
                    $query->wpdb()->users => ['user_email' => $request['s'], 'compare' => 'LIKE'],
                    $query->wpdb()->users => ['display_name' => $request['s'], 'compare' => 'LIKE'],
                    $query->wpdb()->users => ['ID' => $request['s']]
                ],
                'OR'
            );
        }

        return $query;
    }

    public function prepare_items()
    {
        $query = $this->parse_query();

        $this->_column_headers = array($this->get_columns(), [], $this->get_sortable_columns(), $this->get_primary_column_name());

        $offset = ($this->get_pagenum() - 1) * 25;

        $total_items = $query->action('select')->columns('COUNT(*)')->query(true);

        $this->items = $query->limit(25)->offset($offset)->columns('*')->recompile()->query();

        print_r($query->export());

        $this->set_pagination_args(array(
            'total_items' => $total_items,
            'per_page'    => 25,
            'total_pages' => ceil($total_items / 25),
        ));
    }

    public function get_columns()
    {
        return array(
            'cb'           => '<input type="checkbox">',
            'username'     => __('Username', 'wpms'),
            'user_name'    => __('Name', 'wpms'),
            'email'        => __('E-mail', 'wpms'),
            'subscription' => __('Subscription', 'wpms'),
            'expire'       => __('Expire', 'wpms'),
            'renew_count'  => __('Renew Count', 'wpms'),
            //'paid'         => __('Paid', 'wpms'),
        );
    }

    protected function get_sortable_columns()
    {
        return array(
            'expire' => array('expire', 'desc'),
        );
    }

    public function get_bulk_actions()
    {
        return array(
            'subscriptions' => __('Subscriptions', 'wpms'),
            'drop'          => __('Drop', 'wpms')
        );
    }

    function column_cb($item)
    {
        return sprintf('<input type="checkbox" name="bulk-users-id[]" value="%s" />', $item->ID);
    }

    public function no_items()
    {
        _e('No Users found.', 'wpms');
    }
}