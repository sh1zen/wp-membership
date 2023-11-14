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

use WPMembership\core\MembershipLevel;
use WPS\core\RequestActions;
use WPS\core\Query;
use WPS\core\UtilEnv;

class LevelsList extends \WP_List_Table
{
    private string $action_hook;
    private string $action_page_hook;

    public function __construct($args = array())
    {
        $this->modes = array(
            'list' => __('List view', 'wpms'),
        );

        $this->action_hook = $args['action_hook'] ?? '';
        $this->action_page_hook = "$this->action_hook-page";

        parent::__construct(
            array(
                'singular' => __('level', 'wpms'),
                'plural'   => __('levels', 'wpms'),
                'ajax'     => false,
                'screen'   => get_current_screen() ?? null,
            )
        );
    }

    public function column_title($item)
    {
        $item = new MembershipLevel($item);

        $edit_link = RequestActions::get_url($this->action_page_hook, 'edit') . "&level_id=$item->id";

        $output = '<strong><a href="' . esc_url($edit_link) . '" class="row-title">' . esc_html($item->title) . '</a></strong>';

        $row_actions = array();

        $row_actions[] = "<span class='edit'><a href='$edit_link'>" . __('Edit', 'wpms') . "</a></span>";

        if (!$item->active) {
            $row_actions[] = "<span class='inline'><a href='" . RequestActions::get_url($this->action_hook, 'activate') . "&level_id=$item->id" . "'>" . __('Activate', 'wpms') . "</a></span>";
        }
        else {
            $row_actions[] = "<span class='inline'><a href='" . RequestActions::get_url($this->action_hook, 'deactivate') . "&level_id=$item->id" . "'>" . __('Suspend', 'wpms') . "</a></span>";
        }

        $row_actions[] = "<span class='delete'><a href='" . RequestActions::get_url($this->action_hook, 'delete') . "&level_id=$item->id" . "'>" . __('Delete', 'wpms') . "</a></span>";

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

        submit_button(__('Filter', 'wpms'), 'submit', '', false);

        echo '<select name="filter_active">';
        printf('<option value="">%s</option>', __('View all Levels', 'wpms'));
        printf('<option value="%s"%s>%s</option>', 'yes', selected($_REQUEST['filter_active'] ?? '', 'yes', false), __('View active Levels', 'wpms'));
        printf('<option value="%s"%s>%s</option>', 'no', selected($_REQUEST['filter_active'] ?? '', 'no', false), __('View inactive Levels', 'wpms'));
        echo '</select>';

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
        $item = new MembershipLevel($item);

        switch ($column_name) {

            case 'id':
                $return = "<span>" . $item->id . "</span>";
                break;

            case 'count':
                $return = "<span>" . $item->count() . "</span>";
                break;

            case 'status':
                $return = $item->active ? '<span class="wps--green">Active</span>' : '<span class="wps--red">Suspended</span>';
                break;

            case 'duration':
                $return = '';
                foreach (UtilEnv::convertSecondsToDuration($item->duration) as $item => $value) {
                    $return .= "$value $item ";
                }
                break;

            case 'type':
                $return = "<a href='" . $this->get_filtered_link('filter_type', $item->type) . "'>" . ucwords(esc_html($item->type)) . "</a>";
                break;

            default:
                $return = '<span>' . esc_html($item->$column_name ?? 'N/B') . '</span>';
        }

        return $return;
    }

    private function get_filtered_link($name = '', $value = ''): string
    {
        $base_page_url = menu_page_url($_GET['page'] ?? '', false);

        if (empty($name)) {
            return $base_page_url;
        }

        return add_query_arg($name, $value, $base_page_url);
    }

    public function get_items($use_limit = false)
    {
        $query = $this->parse_query()->output(ARRAY_A);

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

        $query = Query::getInstance();

        $query->tables(WP_MEMBERSHIP_TABLE_LEVELS);

        $query->orderby(
            match ($request['orderby'] ?? 'id') {
                'title' => 'title',
                'count' => 'count',
                default => 'id',
            },
            $request['order'] ?? 'ASC'
        );

        if (!empty($request['filter_active'])) {
            $query->where(['active' => $request['filter_active'] === 'yes' ? '1' : '0']);
        }

        if (!empty($request['filter_type'])) {
            $query->where(['type' => $request['filter_type']]);
        }

        if (!empty($request['s'])) {
            $query->where(
                [
                    ['title' => $request['s'], 'compare' => 'LIKE'],
                    ['description' => $request['s'], 'compare' => 'LIKE'],
                    ['id' => $request['s'], 'compare' => 'LIKE']
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

        $this->set_pagination_args(array(
            'total_items' => $total_items,
            'per_page'    => 25,
            'total_pages' => ceil($total_items / 25),
        ));
    }

    public function get_columns()
    {
        return array(
            'cb'       => '<input type="checkbox">',
            'title'    => __('Title', 'wpms'),
            //'id'       => __('ID', 'wpms'),
            'duration' => __('Duration', 'wpms'),
            'type'     => __('Type', 'wpms'),
            'status'   => __('Status', 'wpms'),
            'count'    => __('Count', 'wpms')
        );
    }

    protected function get_sortable_columns()
    {
        return array(
            'title' => array('title', 'desc'),
            'count' => array('count', 'desc')
        );
    }

    public function get_bulk_actions()
    {
        return array(
            'activate'   => __('Activate', 'wpms'),
            'deactivate' => __('Suspend', 'wpms'),
            'delete'     => __('Delete', 'wpms')
        );
    }

    function column_cb($item)
    {
        return sprintf('<input type="checkbox" name="bulk-levels-id[]" value="%s" />', $item->id);
    }

    public function no_items()
    {
        _e('No Subscriptions Plan Found.', 'wpms');
    }

    protected function bulk_actions($which = '')
    {
        $actions = $this->get_bulk_actions();

        if (empty($actions)) {
            return;
        }

        echo '<label for="bulk-action-selector-' . esc_attr($which) . '" class="screen-reader-text">' . __('Select bulk action') . '</label>';
        echo "<select name='bulk-action' id='bulk-action-selector-" . esc_attr($which) . "'>";
        echo '<option value="-1">' . __('Bulk actions') . "</option>";

        foreach ($actions as $key => $value) {
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
}