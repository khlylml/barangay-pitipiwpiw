<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Login required for My Reports
if (!isset($_SESSION['user_id'])) {
    header('Location: login.php');
    exit;
}

require_once __DIR__ . '/config/database.php';
require_once __DIR__ . '/includes/auth.php';

// Admin and staff should use the report management page
$role = strtolower($_SESSION['role'] ?? 'resident');

if (isStaffRole($role)) {
    header('Location: admin/reports.php');
    exit;
}

$userId = (int) $_SESSION['user_id'];

// Tabs: one status per tab ("Resolved" also includes older "Completed").
$tabs = [
    '' => ['label' => 'All', 'statuses' => []],
    'Pending' => ['label' => 'Pending', 'statuses' => ['Pending']],
    'Verified' => ['label' => 'Verified', 'statuses' => ['Verified']],
    'In Progress' => ['label' => 'In Progress', 'statuses' => ['In Progress']],
    'Resolved' => ['label' => 'Resolved', 'statuses' => ['Resolved', 'Completed']],
];

$activeTab = (string) ($_GET['status'] ?? '');

if (!array_key_exists($activeTab, $tabs)) {
    $activeTab = '';
}

// Count this resident's reports per status.
$counts = [];
$countStmt = $pdo->prepare(
    'SELECT status, COUNT(*) AS total FROM incidents
     WHERE reporter_id = ? GROUP BY status'
);
$countStmt->execute([$userId]);

foreach ($countStmt->fetchAll(PDO::FETCH_ASSOC) as $row) {
    $counts[$row['status']] = (int) $row['total'];
}

function tabTotal(array $tab, array $counts, string $key): int
{
    if ($key === '') {
        return array_sum($counts);
    }

    $total = 0;

    foreach ($tab['statuses'] as $status) {
        $total += $counts[$status] ?? 0;
    }

    return $total;
}

// Get only the reports submitted by the logged-in resident
$params = [$userId];
$statusFilter = '';

if ($tabs[$activeTab]['statuses']) {
    $statusFilter = ' AND i.status IN (' . implode(
        ',',
        array_fill(0, count($tabs[$activeTab]['statuses']), '?')
    ) . ')';
    $params = array_merge($params, $tabs[$activeTab]['statuses']);
}

$sql = "
    SELECT
        i.incident_id,
        i.incident_date,
        i.status,
        i.created_at,
        i.location_description,
        t.type_name
    FROM incidents AS i
    LEFT JOIN incident_types AS t
        ON i.incident_type_id = t.type_id
    WHERE i.reporter_id = ?" . $statusFilter . "
    ORDER BY i.created_at DESC
";

$stmt = $pdo->prepare($sql);
$stmt->execute($params);
$reports = $stmt->fetchAll(PDO::FETCH_ASSOC);

function residentStatusClass(?string $status): string
{
    switch ($status) {
        case 'Resolved':
        case 'Completed':
            return 'status-resolved';
        case 'Verified':
            return 'status-verified';
        case 'In Progress':
            return 'status-progress';
        default:
            return 'status-pending';
    }
}

$pageTitle = 'My Reports';
require_once __DIR__ . '/includes/header.php';
?>

<section class="page-heading">
    <h1>My Reports</h1>
    <p>View your submitted incidents and check their current status.</p>

</section>

<nav class="status-tabs" aria-label="Report status">
    <?php foreach ($tabs as $key => $tab): ?>
        <a
            href="my-reports.php<?= $key !== '' ? '?status=' . rawurlencode($key) : '' ?>"
            class="<?= $key === $activeTab ? 'active' : '' ?>"
            <?= $key === $activeTab ? 'aria-current="page"' : '' ?>
        >
            <?= htmlspecialchars($tab['label']) ?>
            <span class="tab-count"><?= tabTotal($tab, $counts, $key) ?></span>
        </a>
    <?php endforeach; ?>
</nav>

<?php if (!empty($reports)): ?>

    <div class="table-wrap">
        <table class="data-table">
            <thead>
                <tr>
                    <th>Report ID</th>
                    <th>Type</th>
                    <th>Date</th>
                    <th>Location</th>
                    <th>Status</th>
                    <th>Details</th>
                </tr>
            </thead>

            <tbody>
                <?php foreach ($reports as $report): ?>
                    <tr>
                        <td>
                            #<?= (int) $report['incident_id'] ?>
                        </td>

                        <td>
                            <?= htmlspecialchars(
                                $report['type_name'] ?? 'Other',
                                ENT_QUOTES,
                                'UTF-8'
                            ) ?>
                        </td>

                        <td>
                            <?= htmlspecialchars(
                                $report['incident_date'] ?? '',
                                ENT_QUOTES,
                                'UTF-8'
                            ) ?>
                        </td>

                        <td>
                            <?= htmlspecialchars(
                                $report['location_description'] ?? 'Not specified',
                                ENT_QUOTES,
                                'UTF-8'
                            ) ?>
                        </td>

                        <td>
                            <span class="status <?= residentStatusClass($report['status'] ?? 'Pending') ?>">
                                <?= htmlspecialchars(
                                    $report['status'] ?? 'Pending',
                                    ENT_QUOTES,
                                    'UTF-8'
                                ) ?>
                            </span>
                        </td>

                        <td>
                            <a href="report-details.php?id=<?= (int) $report['incident_id'] ?>">
                                View Details
                            </a>
                        </td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>

<?php else: ?>

    <section class="empty-state">
        <h2>No Reports Here</h2>
        <p>
            <?= $activeTab === ''
                ? "You haven't submitted any incident reports yet."
                : 'You have no reports with this status.' ?>
        </p>
        <p>Reports you submit will appear here so you can track their status.</p>
    </section>

<?php endif; ?>

<?php require_once __DIR__ . '/includes/footer.php'; ?>