<?php
session_start();

require_once __DIR__ . '/../includes/auth.php';

if (!isset($_SESSION['user_id']) || !isStaffRole(currentRole())) {
    header('Location: ../login.php');
    exit;
}

require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/notifications.php';
require_once __DIR__ . '/../includes/pager.php';

function escapeNotice($value): string
{
    return htmlspecialchars((string) ($value ?? ''), ENT_QUOTES, 'UTF-8');
}

$userId = (int) $_SESSION['user_id'];
$filter = ($_GET['filter'] ?? '') === 'unread' ? 'unread' : 'all';
$perPage = 15;
$page = max(1, (int) ($_GET['page'] ?? 1));
$error = '';
$data = ['unread_count' => 0, 'items' => []];

try {
    $data = fetchNotifications($pdo, $userId);
} catch (Throwable $e) {
    error_log('Notifications page error: ' . $e->getMessage());
    $error = 'Unable to load notifications right now. Please try again later.';
}

$items = $filter === 'unread'
    ? array_values(array_filter($data['items'], static fn(array $item) => !$item['read']))
    : $data['items'];

$totalItems = count($items);
$totalPages = max(1, (int) ceil($totalItems / $perPage));
$page = min($page, $totalPages);
$pageItems = array_slice($items, ($page - 1) * $perPage, $perPage);

function noticePageLink(int $target): string
{
    $query = $_GET;
    $query['page'] = $target;

    return '?' . http_build_query($query);
}

$basePath = '../';
$pageTitle = 'Notifications';

require_once __DIR__ . '/../includes/header.php';
?>

<section class="page-heading">
    <h1>Notifications</h1>
    <p>New incident reports from residents in the last 30 days. Each official has their own read status.</p>
</section>

<?php if ($error !== ''): ?>
    <p class="alert alert-error"><?= escapeNotice($error) ?></p>
<?php endif; ?>

<div class="notif-page" data-notif-page>

    <p class="alert alert-success" data-notif-refresh hidden>
        New reports have come in. <a href="notifications.php<?= $filter === 'unread' ? '?filter=unread' : '' ?>">Refresh the list</a>
    </p>

    <div class="notif-toolbar">
        <nav class="status-tabs" aria-label="Notification filter">
            <a href="notifications.php" class="<?= $filter === 'all' ? 'active' : '' ?>">
                All <span class="tab-count"><?= count($data['items']) ?></span>
            </a>
            <a href="notifications.php?filter=unread" class="<?= $filter === 'unread' ? 'active' : '' ?>">
                Unread <span class="tab-count"><?= (int) $data['unread_count'] ?></span>
            </a>
        </nav>

        <?php if ($data['unread_count'] > 0): ?>
            <button type="button" class="button button-outline" data-ack-all>Mark all as read</button>
        <?php endif; ?>
    </div>

    <?php if (empty($pageItems)): ?>
        <p class="empty-reports">
            <?= $filter === 'unread' ? 'You have no unread notifications.' : 'No notifications yet.' ?>
        </p>
    <?php else: ?>
        <div class="table-wrap">
            <table class="data-table notif-table">
                <thead>
                    <tr>
                        <th>Alert</th>
                        <th>Report</th>
                        <th>Location</th>
                        <th>Reported</th>
                        <th>AI suggestion</th>
                        <th>Status</th>
                        <th>Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($pageItems as $item): ?>
                        <tr class="<?= $item['read'] ? 'is-read' : 'is-unread' ?>">
                            <td>
                                <span class="notif-pill priority-<?= escapeNotice($item['priority']) ?>">
                                    <?= escapeNotice($item['priority_label']) ?>
                                </span>
                                <?php if (!$item['read']): ?>
                                    <span class="notif-new">New</span>
                                <?php endif; ?>
                            </td>
                            <td>
                                #<?= (int) $item['incident_id'] ?><br>
                                <strong><?= escapeNotice($item['type']) ?></strong>
                            </td>
                            <td><?= escapeNotice($item['location'] !== '' ? $item['location'] : 'Not specified') ?></td>
                            <td><?= escapeNotice($item['reported_at']) ?></td>
                            <td>
                                <?php if ($item['ai_type']): ?>
                                    <?= escapeNotice($item['ai_type']) ?>
                                    (<?= (int) $item['ai_confidence'] ?>%)
                                    <?php if ($item['raised_by_ai']): ?>
                                        <br><small>Alert raised by AI</small>
                                    <?php endif; ?>
                                <?php else: ?>
                                    None
                                <?php endif; ?>
                            </td>
                            <td>
                                <span class="status <?= statusBadgeClass($item['status']) ?>">
                                    <?= escapeNotice($item['status']) ?>
                                </span>
                            </td>
                            <td class="row-actions">
                                <a href="../report-details.php?id=<?= (int) $item['incident_id'] ?>">View Report</a>
                                <?php if (!$item['read']): ?>
                                    <button type="button" class="link-button"
                                            data-ack-id="<?= (int) $item['incident_id'] ?>">
                                        Mark as read
                                    </button>
                                <?php endif; ?>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>

        <?= renderPager($page, $totalPages, 'noticePageLink', ' (' . $totalItems . ' notifications)') ?>
    <?php endif; ?>

</div>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
