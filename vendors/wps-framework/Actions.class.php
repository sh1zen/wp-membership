<?php
/**
 * @author    sh1zen
 * @copyright Copyright (C) 2023.
 * @license   http://www.gnu.org/licenses/gpl.html GNU/GPL
 */

namespace WPS\core;

/**
 * Allow to easy schedule a callback action
 */
class Actions
{
    public static string $nonce_action = 'wps-action-ajax';

    public static string $nonce_name = 'wps_nonce';

    private static bool $suspend = false;

    /**
     * A name for this cron.
     */
    public string $hook;

    /**
     * How often to run this cron in seconds.
     */
    public int $interval;

    /**
     * @var Closure|string|null $callback Optional. Anonymous function, function name or null to override with your own handle() method.
     */
    public $callback;

    /**
     * How often the event should subsequently recur. See wp_get_schedules().
     */
    public string $recurrence;

    public int $timestamp;

    private function __construct($hook, $interval, $callback = null, $time = 0)
    {
        $this->hook = trim($hook);
        $this->interval = absint($interval);
        $this->callback = $callback;

        if (empty($this->interval) or empty($this->hook)) {
            return;
        }

        if (!$time) {
            $this->timestamp = time();
        }
        else {

            $next_run_local = strtotime($time, wps_time('timestamp'));

            if (false === $next_run_local) {
                return;
            }

            $this->timestamp = $next_run_local;
        }

        $this->recurrence = "wps_cron_{$this->interval}_seconds";

        $this->schedule_event();

        // schedules handler
        add_filter('cron_schedules', [$this, 'add_schedule']);

        // cron handler
        add_action($this->hook, [$this, 'handle']);
    }

    private function schedule_event()
    {
        $crons = _get_cron_array();

        if (!wps_utils()->is_upgrading()) {
            foreach ($crons as $timestamp => $cron) {
                if (isset($cron[$this->hook])) {
                    return $timestamp;
                }
            }
        }

        // reschedule all events with current hook
        foreach ($crons as $timestamp => $cron) {
            if (is_array($cron)) {
                unset($crons[$timestamp][$this->hook]);
            }
        }

        if (!isset($crons[$this->timestamp])) {
            $crons[$this->timestamp] = [];
        }

        $crons[$this->timestamp][$this->hook] = [];

        /**
         * key must be md5(serialize($args)) to allow wp_reschedule correctly works in case of missing scheduling
         * so it is 40cd750bba9870f18aada2478b24840a
         */
        $crons[$this->timestamp][$this->hook]['40cd750bba9870f18aada2478b24840a'] = array(
            'schedule' => $this->recurrence,
            'args'     => [],
            'interval' => $this->interval,
        );

        $crons = array_filter($crons);

        uksort($crons, 'strnatcasecmp');

        return _set_cron_array($crons);
    }

    /**
     * Request action structure:
     *  For the callback:
     *      wps-action => hook
     *      hook => callback action
     *  Internals
     *      self::$nonce_name => wp_create_nonce(self::$nonce_action)
     */
    public static function request($hook, callable $callback = null, $short_circuit = false, $remove_query_args = false)
    {
        if (!self::is_valid_request($hook, $short_circuit) or self::$suspend) {
            return;
        }

        if (!empty($_REQUEST[self::$nonce_name]) and !UtilEnv::verify_nonce(self::$nonce_action, $_REQUEST[self::$nonce_name])) {

            if (!wp_doing_ajax()) {
                return;
            }

            Ajax::response([
                'body'  => __('It seems that you are not allowed to do this request.', 'wps'),
                'title' => __('Request error', 'wps')
            ], 'error');
        }

        $response = call_user_func($callback, $_REQUEST[$hook] ?? '');

        if (!wp_doing_ajax()) {

            if ($remove_query_args) {
                self::remove_query_args(is_array($response) ? $response : []);
            }
            return;
        }

        Ajax::response([
            'body'  => $response ?: __('It seems that you are not allowed to do this request.', 'wps'),
            'title' => $response ? __('Request response', 'wps') : __('Request error', 'wps')
        ], $response ? 'success' : 'error');
    }

    public static function is_valid_request($hook, $short_circuit = false): bool
    {
        if (!isset($_REQUEST['wps-action']) or $_REQUEST['wps-action'] !== $hook) {
            return false;
        }

        if (!$short_circuit and !isset($_REQUEST[$hook])) {
            return false;
        }

        return true;
    }

    public static function remove_query_args($query_args = []): void
    {
        $rewriter = Rewriter::getClone();

        $hook = $rewriter->get_query_arg('wps-action');

        if ($hook) {
            $rewriter->remove_query_arg($hook);
            $rewriter->remove_query_arg('wps-action');
            $rewriter->remove_query_arg(self::$nonce_name);

            if (!empty($query_args)) {
                foreach ($query_args as $query => $value) {
                    $rewriter->set_query_arg($query, $value, true);
                }
            }

            $rewriter->redirect($rewriter->get_uri());
        }
    }

    public static function suspend(): void
    {
        self::$suspend = true;
    }

    public static function get_request($hook, $short_circuit = false): string
    {
        if (!self::is_valid_request($hook, $short_circuit)) {
            return '';
        }

        return esc_html($_REQUEST[$hook] ?? '');
    }

    public static function get_url($hook, $value, $ajax = false, $display = false): string
    {
        $rewriter = Rewriter::getClone();

        $rewriter->remove_query_arg('_wp_http_referer');

        if ($ajax) {
            $rewriter->set_base(admin_url('admin-ajax.php'));
        }

        $rewriter->set_query_arg('wps-action', $hook);
        $rewriter->set_query_arg($hook, $value);
        $rewriter->set_query_arg(self::$nonce_name, wp_create_nonce(self::$nonce_action));

        $url = $rewriter->get_uri();

        if ($display) {
            echo $url;
        }

        return $url;
    }

    public static function get_action_button($hook, $action, $text, $classes = 'button-secondary')
    {
        return Graphic::generate_field(array(
            'id'      => $hook,
            'value'   => $action,
            'name'    => $text,
            'classes' => $classes,
            'context' => 'button'
        ), false);
    }

    public static function schedule($hook, $interval, $callback = null, $time = 0): void
    {
        new static($hook, $interval, $callback, $time);
    }

    public static function nonce_field($hook, $referrer = false, $display = true): string
    {
        $fields = wp_nonce_field(self::$nonce_action, self::$nonce_name, $referrer, false);
        $fields .= "<input type='hidden' name='wps-action' value='$hook'/>";

        if ($display) {
            echo $fields;
        }

        return $fields;
    }

    public function unschedule_event(): bool
    {
        $crons = get_option('cron') ?: [];

        foreach ($crons as $timestamp => $cron) {
            if (is_array($cron)) {
                unset($crons[$timestamp][$this->hook]);
            }
        }

        $crons = array_filter($crons);

        uksort($crons, 'strnatcasecmp');

        return update_option('cron', $crons);
    }

    public function handle(): void
    {
        if (is_callable($this->callback)) {
            call_user_func($this->callback);
        }

        if (wps_utils()->debug) {
            wps_log("cron execution " . wps_time('mysql') . ": $this->hook > $this->timestamp");
        }
    }

    public function add_schedule($schedules)
    {
        if (isset($schedules[$this->recurrence])) {
            return $schedules;
        }

        $schedules[$this->recurrence] = [
            'interval' => $this->interval,
            'display'  => 'Every ' . $this->interval . ' seconds',
        ];

        return $schedules;
    }
}