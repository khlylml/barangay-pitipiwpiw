<?php

function renderPager(int $page, int $totalPages, callable $link, string $extraInfo = ''): string
{
    if ($totalPages <= 1) {
        return '';
    }

    $e = static fn(string $value): string => htmlspecialchars($value, ENT_QUOTES, 'UTF-8');

    $button = static function (string $label, int $target, string $title, bool $enabled) use ($link, $e): string {
        if (!$enabled) {
            return '<span class="pager-btn is-disabled" aria-disabled="true">' . $label . '</span>';
        }

        return '<a class="pager-btn" href="' . $e($link($target)) . '" title="' . $e($title) . '">' . $label . '</a>';
    };

    $html = '<nav class="pager" aria-label="Pages">';
    $html .= $button('&laquo; First', 1, 'First page', $page > 1);

    if ($totalPages > 10) {
        $html .= $button('-10', max(1, $page - 10), 'Back 10 pages', $page > 1);
    }

    $html .= $button('&lsaquo; Prev', $page - 1, 'Previous page', $page > 1);

    for ($n = max(1, $page - 1); $n <= min($totalPages, $page + 1); $n++) {
        if ($n === $page) {
            $html .= '<span class="pager-btn is-current" aria-current="page">' . $n . '</span>';
        } else {
            $html .= '<a class="pager-btn" href="' . $e($link($n)) . '">' . $n . '</a>';
        }
    }

    $html .= $button('Next &rsaquo;', $page + 1, 'Next page', $page < $totalPages);

    if ($totalPages > 10) {
        $html .= $button('+10', min($totalPages, $page + 10), 'Forward 10 pages', $page < $totalPages);
    }

    $html .= $button('Last &raquo;', $totalPages, 'Last page', $page < $totalPages);
    $html .= '</nav>';
    $html .= '<p class="pager-info">Page ' . $page . ' of ' . $totalPages . $e($extraInfo) . '</p>';

    return $html;
}
