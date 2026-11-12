<?php

require_once __DIR__ . '/auth.php';
require_once __DIR__ . '/reports.php';

function communityFeedIcon(string $statusClass): string
{
    $paths = [
        'status-pending' => '<circle cx="12" cy="12" r="9"/><polyline points="12 7 12 12 15 14"/>',
        'status-verified' => '<path d="M12 3l7 3v5c0 5-3.5 8-7 10-3.5-2-7-5-7-10V6z"/><polyline points="9 12 11 14 15 10"/>',
        'status-progress' => '<path d="M21 12a9 9 0 1 1-3-6.7"/><polyline points="21 4 21 9 16 9"/>',
        'status-resolved' => '<circle cx="12" cy="12" r="9"/><polyline points="8 12 11 15 16 9"/>',
    ];

    $path = $paths[$statusClass] ?? $paths['status-pending'];

    return '<svg viewBox="0 0 24 24" width="22" height="22" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">' . $path . '</svg>';
}

function renderCommunityFeed(array $reports): string
{
    $e = static fn($value): string => htmlspecialchars((string) ($value ?? ''), ENT_QUOTES, 'UTF-8');

    $html = '<ul class="feed-list">';

    foreach ($reports as $report) {
        $statusClass = statusBadgeClass($report['status']);
        $time = strtotime((string) $report['incident_date']);
        $date = $time ? date('M j, Y', $time) : (string) $report['incident_date'];
        $location = trim((string) ($report['location_description'] ?? ''));

        $html .= '<li class="feed-item feed-' . $e(substr($statusClass, 7)) . '">';
        $html .= '<span class="feed-icon">' . communityFeedIcon($statusClass) . '</span>';
        $html .= '<div class="feed-main">';
        $html .= '<strong>' . $e($report['type_name'] ?? 'Uncategorized') . '</strong>';
        $html .= '<span class="feed-meta">';
        $html .= '<svg viewBox="0 0 24 24" width="14" height="14" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M12 21s-6-5.2-6-10a6 6 0 0 1 12 0c0 4.8-6 10-6 10z"/><circle cx="12" cy="11" r="2"/></svg>';
        $html .= $e($location !== '' ? $location : 'Location not specified') . ' &middot; ' . $e($date);
        $html .= '</span>';
        $html .= '</div>';
        $html .= '<span class="status ' . $e($statusClass) . '">' . $e(normalizeStatus($report['status'])) . '</span>';
        $html .= '</li>';
    }

    return $html . '</ul>';
}
