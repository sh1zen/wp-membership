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
use WPS\core\StringHelper;
use WPS\core\UtilEnv;

class CommunicationList extends \WP_List_Table
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
                'singular' => __('communication', 'wpms'),
                'plural'   => __('communications', 'wpms'),
                'ajax'     => false,
                'screen'   => get_current_screen() ?? null,
            )
        );
    }

    public function column_subject($item)
    {
        $edit_link = RequestActions::get_url($this->action_page_hook, 'edit') . "&comm_id=$item->id";

        $output = '<strong><a href="' . esc_url($edit_link) . '" class="row-title">' . esc_html($item->subject) . '</a></strong>';

        $row_actions = array();

        $row_actions[] = "<span class='edit'><a href='$edit_link'>" . __('Edit', 'wpms') . "</a></span>";

        if (!UtilEnv::to_boolean($item->active)) {
            $row_actions[] = "<span class='inline'><a href='" . RequestActions::get_url($this->action_hook, 'activate') . "&comm_id=$item->id" . "'>" . __('Activate', 'wpms') . "</a></span>";
        }
        else {
            $row_actions[] = "<span class='inline'><a href='" . RequestActions::get_url($this->action_hook, 'deactivate') . "&comm_id=$item->id" . "'>" . __('Suspend', 'wpms') . "</a></span>";
        }

        $row_actions[] = "<span class='inline'><a href='" . RequestActions::get_url($this->action_hook, 'sendme') . "&comm_id=$item->id" . "'>" . __('Send Me', 'wpms') . "</a></span>";

        $row_actions[] = "<span class='delete'><a href='" . RequestActions::get_url($this->action_hook, 'delete') . "&comm_id=$item->id" . "'>" . __('Delete', 'wpms') . "</a></span>";

        $output .= '<br><div class="row-actions">' . implode(' | ', $row_actions) . '</div>';

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

        submit_button(__('Filter', 'wpms'), 'button', 'aal-filter', false);

        echo '<select name="filter_active">';
        printf('<option value="">%s</option>', __('View All Messages', 'wpms'));
        printf('<option value="%s"%s>%s</option>', 'yes', selected($_REQUEST['filter_active'] ?? '', 'yes', false), __('View Active Communications', 'wpms'));
        printf('<option value="%s"%s>%s</option>', 'no', selected($_REQUEST['filter_active'] ?? '', 'no', false), __('View Inactive Communications', 'wpms'));
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
        switch ($column_name) {

            case 'level_name':
                $return = "<span>" . (wpms_get_level($item->level_id)->title ?: __('All', 'wpms')) . "</span>";
                break;

            case 'message':
                $return = '<span>' . esc_html(StringHelper::truncate($item->message ?: '', 100, '...')) . '</span>';
                break;

            case 'status':
                $return = UtilEnv::to_boolean($item->active) ? "<span class='wps--green'>Active</span>" : "<span class='wps--red'>Inactive</span>";
                break;

            case 'period':
                $return = '';
                foreach (UtilEnv::convertSecondsToDuration(absint($item->timegap)) as $key => $value) {
                    $return .= "$value $key ";
                }

                $return .= ' ' . match ($item->event) {
                        'signup' => __("Sign Up", 'wpms'),
                        'before_expire' => __("Before Subscription Expire", 'wpms'),
                        'after_expire' => __("After Subscription Expire", 'wpms'),
                        'leave' => __("User Leave Membership", 'wpms'),
                        'drop' => __("Membership Drop", 'wpms'),
                        'join' => __("Join Subscription", 'wpms'),
                        default => '',
                    };

                $return = "<span>" . $return . "</span>";
                break;

            default:
                $return = '<span>' . esc_html($item->$column_name ?? 'N/B') . '</span>';
        }

        return $return;
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

        $query = Query::getInstance(OBJECT, true);

        $query->tables(WP_MEMBERSHIP_TABLE_COMMUNICATIONS);

        $query->orderby(
            match ($request['orderby'] ?? 'id') {
                'level_name' => [WP_MEMBERSHIP_TABLE_LEVELS => 'title'],
                default => [WP_MEMBERSHIP_TABLE_COMMUNICATIONS => 'id'],
            },
            $request['order'] ?? 'DESC'
        );

        if (!empty($request['filter_active'])) {
            $query->where([WP_MEMBERSHIP_TABLE_COMMUNICATIONS => ['active' => $request['filter_active'] === 'yes' ? '1' : '0']]);
        }

        if (!empty($request['s'])) {
            $query->where(
                [
                    ['subject' => $request['s'], 'compare' => 'LIKE'],
                    ['message' => $request['s'], 'compare' => 'LIKE'],
                    ['level_id' => $request['s'], 'compare' => '=']
                ],
                'OR',
                WP_MEMBERSHIP_TABLE_COMMUNICATIONS
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
            'cb'         => '<input type="checkbox">',
            'subject'    => __('Subject', 'wpms'),
            'level_name' => __('Subscription', 'wpms'),
            'message'    => __('Message', 'wpms'),
            'period'     => __('Period', 'wpms'),
            'status'     => __('Status', 'wpms'),
        );
    }

    protected function get_sortable_columns()
    {
        return array(
            'level_name' => array('level_name', 'desc')
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
        return sprintf('<input type="checkbox" name="bulk-comm-ids[]" value="%s" />', $item->id);
    }

    public function no_items()
    {
        _e('No communications found.', 'wpms');
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
}