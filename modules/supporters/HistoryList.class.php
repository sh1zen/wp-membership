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

use WPS\core\Query;

class HistoryList extends \WP_List_Table
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
                'singular' => __('history', 'wpms'),
                'plural'   => __('history', 'wpms'),
                'ajax'     => false,
                'screen'   => get_current_screen() ?? null,
            )
        );
    }

    public function display_tablenav($which)
    {
        ?>
        <row class="tablenav <?php echo esc_attr($which); ?>">
            <?php
            $this->extra_tablenav($which);
            $this->pagination($which);
            ?>
            <br class="clear"/>
        </row>
        <?php
    }

    public function extra_tablenav($which)
    {
        if ('bottom' === $which) {
            $this->extra_tablenav_footer();
        }
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

            case 'user':

                $user = wps_get_user($item->user_id);
                if ($user) {
                    $return = '<strong><a href="' . esc_url(admin_url('user-edit.php?user_id=' . $user->ID)) . '" class="row-title">' . ucwords(strtolower(esc_html($user->display_name))) . '</a></strong>';
                }
                else {
                    $return = "<span>" . esc_html($item->user_id) . "</span>";
                }
                break;

            case 'level':
                $return = '<span>' . wpms_get_level($item->level_id)->title . '</span>';
                break;

            case 'time':
                $return = '<span>' . esc_html($item->timestamp ?? '') . '</span>';
                break;

            default:
                $return = '<span>' . esc_html(ucwords($item->$column_name ?? 'N/B')) . '</span>';
        }

        return $return;
    }

    public function get_items($use_limit = false)
    {
        $query = Query::getInstance(ARRAY_A)->tables(WP_MEMBERSHIP_TABLE_HISTORY);

        $offset = ($this->get_pagenum() - 1) * 25;

        if ($use_limit) {
            $query->limit(25);
        }

        return $query->offset($offset)->action('select')->columns('*')->query();
    }

    public function prepare_items()
    {
        $query = Query::getInstance()->tables(WP_MEMBERSHIP_TABLE_HISTORY)->orderby('timestamp', 'DESC');

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
            'user'   => __('User', 'wpms'),
            'level'  => __('Subscription', 'wpms'),
            'action' => __('Action', 'wpms'),
            'time'   => __('Time', 'wpms')
        );
    }

    public function no_items()
    {
        _e('No subscriptions found.', 'wpms');
    }
}