<?php

namespace WPMembership\modules\supporters;

trait ListPaginationTrait
{
    protected function pagination($which)
    {
        if (empty($this->_pagination_args)) {
            return;
        }

        $total_items = (int) ($this->_pagination_args['total_items'] ?? 0);
        $total_pages = (int) ($this->_pagination_args['total_pages'] ?? 0);

        if ($total_items <= 0 || $total_pages <= 1) {
            return;
        }

        $current = max(1, min((int) $this->get_pagenum(), $total_pages));
        $base_url = remove_query_arg('paged');

        $page_url = static function (int $page) use ($base_url): string {
            return esc_url(add_query_arg('paged', max(1, $page), $base_url));
        };

        $nav_button = static function (string $class, string $label, string $symbol, ?string $url): string {
            if ($url === null) {
                return '';
            }

            return '<a class="' . esc_attr($class) . ' button" href="' . $url . '"><span class="screen-reader-text">' . esc_html($label) . '</span><span aria-hidden="true">' . esc_html($symbol) . '</span></a>';
        };

        $first_url = $current > 1 ? $page_url(1) : null;
        $prev_url = $current > 1 ? $page_url($current - 1) : null;
        $next_url = $current < $total_pages ? $page_url($current + 1) : null;
        $last_url = $current < $total_pages ? $page_url($total_pages) : null;
        ?>
        <div class="tablenav-pages <?php echo esc_attr($which); ?>">
            <span class="displaying-num"><?php echo esc_html(sprintf($total_items === 1 ? '%s elemento' : '%s elementi', number_format_i18n($total_items))); ?></span>
            <span class="pagination-links">
                <?php echo $nav_button('first-page', __('First page', 'members-control'), '<<', $first_url); ?>
                <?php echo $nav_button('prev-page', __('Previous page', 'members-control'), '<', $prev_url); ?>
                <span class="paging-input wpmc-pagination-status" aria-label="<?php echo esc_attr(sprintf(__('Page %1$s of %2$s', 'members-control'), number_format_i18n($current), number_format_i18n($total_pages))); ?>">
                    <span class="current-page" aria-current="page"><?php echo esc_html(number_format_i18n($current)); ?></span>
                    <span class="wpmc-page-separator" aria-hidden="true">...</span>
                    <span class="wpmc-total-pages"><?php echo esc_html(number_format_i18n($total_pages)); ?></span>
                </span>
                <?php echo $nav_button('next-page', __('Next page', 'members-control'), '>', $next_url); ?>
                <?php echo $nav_button('last-page', __('Last page', 'members-control'), '>>', $last_url); ?>
            </span>
        </div>
        <?php
    }
}
