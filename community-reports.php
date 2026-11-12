<?php
session_start();

require_once __DIR__ . '/config/database.php';
require_once __DIR__ . '/includes/auth.php';
require_once __DIR__ . '/includes/community-feed.php';
require_once __DIR__ . '/includes/pager.php';
require_once __DIR__ . '/includes/auto-archive.php';
autoArchiveResolvedReports($pdo);

// Logged-in users only. Staff already have the full Manage Reports page.
if (!isset($_SESSION['user_id'])) {
    header('Location: login.php');
    exit;
}

if (isStaffRole($_SESSION['role'] ?? '')) {
    header('Location: admin/reports.php');
    exit;
}

$error = '';
$reports = [];
$perPage = 10;
$page = max(1, (int) ($_GET['page'] ?? 1));
$totalReports = 0;
$totalPages = 1;

try {
    $totalReports = (int) $pdo->query("SELECT COUNT(*) FROM incidents WHERE status <> 'Archived'")->fetchColumn();
    $totalPages = max(1, (int) ceil($totalReports / $perPage));
    $page = min($page, $totalPages);
    $offset = ($page - 1) * $perPage;

    $reports = $pdo->query(
        "SELECT
            i.incident_id,
            i.incident_date,
            i.status,
            t.type_name,
            i.location_description
         FROM incidents i
         LEFT JOIN incident_types t
            ON i.incident_type_id = t.type_id
         WHERE i.status <> 'Archived'
         ORDER BY i.created_at DESC
         LIMIT " . (int) $perPage . " OFFSET " . (int) $offset
    )->fetchAll();
} catch (PDOException $e) {
    error_log($e->getMessage());
    $error = 'Unable to load community reports. Please try again later.';
}

function communityPageLink(int $target): string
{
    $query = $_GET;
    $query['page'] = $target;

    return '?' . http_build_query($query);
}

function escapeCommunity($value): string
{
    return htmlspecialchars((string) ($value ?? ''), ENT_QUOTES, 'UTF-8');
}

$pageTitle = 'Community Reports';
require __DIR__ . '/includes/header.php';
?>

<div class="guest-page">

    <section class="guest-heading">
        <h1>Community Reports</h1>
        <p>See what has been reported in Barangay Pitipiwpiw and how each report is progressing.</p>
    </section>

    <div class="guest-notice">
        <strong>Public summary:</strong>
        Only the incident type, date, location and status are shown.
        Names and details of reporters are kept private.
    </div>

    <?php if ($error !== ''): ?>

        <p class="alert alert-error"><?= escapeCommunity($error) ?></p>

    <?php elseif (empty($reports)): ?>

        <div class="guest-empty">
            <p>No incident reports are available yet.</p>
        </div>

    <?php else: ?>

        <div class="feed-panel">
            <?= renderCommunityFeed($reports) ?>
        </div>

        <?= renderPager($page, $totalPages, 'communityPageLink', ' (' . $totalReports . ' reports)') ?>

    <?php endif; ?>

</div>

<?php require __DIR__ . '/includes/footer.php'; ?>
