<?php
session_start();

require_once __DIR__ . '/../includes/auth.php';

// Only staff, admin and super admin can access this page.
if (!isset($_SESSION['user_id']) || !isStaffRole(currentRole())) {
    header('Location: ../login.php');
    exit;
}

require_once __DIR__ . '/../config/database.php';

function escapeReports($value): string
{
    return htmlspecialchars((string) ($value ?? ''), ENT_QUOTES, 'UTF-8');
}

// Each tab shows one status. "Resolved" also includes older "Completed" rows.
$tabs = [
    '' => ['label' => 'All', 'statuses' => []],
    'Pending' => ['label' => 'Pending', 'statuses' => ['Pending']],
    'Verified' => ['label' => 'Verified', 'statuses' => ['Verified']],
    'In Progress' => ['label' => 'In Progress', 'statuses' => ['In Progress']],
    'Resolved' => ['label' => 'Resolved', 'statuses' => ['Resolved', 'Completed']],
    'Archived' => ['label' => 'Archived', 'statuses' => ['Archived']],
];

$activeTab = (string) ($_GET['status'] ?? '');

if (!array_key_exists($activeTab, $tabs)) {
    $activeTab = '';
}

$search = trim((string) ($_GET['q'] ?? ''));

$reports = [];
$counts = [];
$databaseError = false;

try {
    // Count reports per status for the tab badges.
    foreach ($pdo->query(
        'SELECT status, COUNT(*) AS total FROM incidents GROUP BY status'
    )->fetchAll() as $row) {
        $counts[$row['status']] = (int) $row['total'];
    }

    $where = [];
    $params = [];

    if ($activeTab === '') {
        // "All" leaves out archived reports; they have their own tab.
        $where[] = "i.status <> 'Archived'";
    }

    if ($tabs[$activeTab]['statuses']) {
        $placeholders = implode(
            ',',
            array_fill(0, count($tabs[$activeTab]['statuses']), '?')
        );
        $where[] = "i.status IN ($placeholders)";
        $params = array_merge($params, $tabs[$activeTab]['statuses']);
    }

    if ($search !== '') {
        $where[] = "(u.first_name LIKE ? OR u.last_name LIKE ?
                     OR i.location_description LIKE ? OR t.type_name LIKE ?)";
        $like = '%' . $search . '%';
        array_push($params, $like, $like, $like, $like);
    }

    $sql = "
        SELECT
            i.incident_id,
            i.incident_date,
            i.status,
            i.created_at,
            i.location_description,
            t.type_name,
            u.first_name,
            u.last_name,
            l.latitude,
            l.longitude
        FROM incidents i
        LEFT JOIN incident_types t
            ON i.incident_type_id = t.type_id
        LEFT JOIN users u
            ON i.reporter_id = u.user_id
        LEFT JOIN incident_locations l
            ON l.incident_id = i.incident_id
        " . ($where ? 'WHERE ' . implode(' AND ', $where) : '') . "
        ORDER BY i.created_at DESC
    ";

    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);
    $reports = $stmt->fetchAll();

} catch (PDOException $e) {
    error_log($e->getMessage());
    $databaseError = true;
}

function tabCount(array $tab, array $counts, string $key): int
{
    if ($key === '') {
        return array_sum($counts) - ($counts['Archived'] ?? 0);
    }

    $total = 0;

    foreach ($tab['statuses'] as $status) {
        $total += $counts[$status] ?? 0;
    }

    return $total;
}

function statusClass(?string $status): string
{
    switch ($status) {
        case 'Resolved':
        case 'Completed':
            return 'status-resolved';
        case 'Archived':
            return 'status-archived';
        case 'Verified':
            return 'status-verified';
        case 'In Progress':
            return 'status-progress';
        default:
            return 'status-pending';
    }
}

$basePath = '../';
$pageTitle = 'Manage Incident Reports';

require_once __DIR__ . '/../includes/header.php';
?>

<section class="page-heading">
    <h1>Manage Incident Reports</h1>
    <p>Review submitted incidents and update their progress.</p>
</section>

<?php if (($_GET['updated'] ?? '') === '1'): ?>
    <p class="alert alert-success">
        The incident status has been updated.
    </p>
<?php endif; ?>

<nav class="status-tabs" aria-label="Report status">
    <?php foreach ($tabs as $key => $tab): ?>
        <?php
        $query = array_filter(
            ['status' => $key, 'q' => $search],
            static fn($value) => $value !== ''
        );
        ?>
        <a
            href="reports.php<?= $query ? '?' . escapeReports(http_build_query($query)) : '' ?>"
            class="<?= $key === $activeTab ? 'active' : '' ?>"
            <?= $key === $activeTab ? 'aria-current="page"' : '' ?>
        >
            <?= escapeReports($tab['label']) ?>
            <span class="tab-count"><?= tabCount($tab, $counts, $key) ?></span>
        </a>
    <?php endforeach; ?>
</nav>

<form method="GET" class="search-bar" role="search">
    <?php if ($activeTab !== ''): ?>
        <input type="hidden" name="status" value="<?= escapeReports($activeTab) ?>">
    <?php endif; ?>

    <label class="sr-only" for="q">Search reports</label>
    <input
        type="search"
        id="q"
        name="q"
        value="<?= escapeReports($search) ?>"
        placeholder="Search reporter, type or location"
    >
    <button type="submit" class="button button-primary">Search</button>
</form>

<?php if ($databaseError): ?>
    <p class="alert alert-error">
        Unable to load incident reports. Please try again later.
    </p>
<?php else: ?>

    <div class="table-wrap">
        <table class="data-table">
            <thead>
                <tr>
                    <th>ID</th>
                    <th>Reporter</th>
                    <th>Incident Type</th>
                    <th>Date</th>
                    <th>Location</th>
                    <th>Status</th>
                    <th>Action</th>
                </tr>
            </thead>

            <tbody>
                <?php if ($reports): ?>
                    <?php foreach ($reports as $report): ?>
                        <tr>
                            <td>#<?= (int) $report['incident_id'] ?></td>

                            <td>
                                <?= escapeReports(trim(
                                    ($report['first_name'] ?? '') . ' ' .
                                    ($report['last_name'] ?? '')
                                )) ?>
                            </td>

                            <td><?= escapeReports($report['type_name'] ?? 'Other') ?></td>

                            <td><?= escapeReports($report['incident_date']) ?></td>

                            <td>
                                <?= escapeReports(
                                    $report['location_description'] ?? 'Not specified'
                                ) ?>
                            </td>

                            <td>
                                <span class="status <?= statusClass($report['status']) ?>">
                                    <?= escapeReports($report['status'] ?? 'Pending') ?>
                                </span>
                            </td>

                            <td class="row-actions">
                                <a href="../report-details.php?id=<?= (int) $report['incident_id'] ?>">
                                    View
                                </a>

                                <a href="update-status.php?id=<?= (int) $report['incident_id'] ?>">
                                    Update Status
                                </a>

                                <?php if (in_array($report['status'], ['Resolved', 'Completed'], true)): ?>
                                    <button type="button" class="link-button"
                                            data-archive="<?= (int) $report['incident_id'] ?>">
                                        Archive
                                    </button>
                                <?php endif; ?>

                                <?php if ($report['latitude'] !== null && $report['longitude'] !== null): ?>
                                    <a
                                        href="https://www.google.com/maps/dir/?api=1&amp;destination=<?= rawurlencode($report['latitude'] . ',' . $report['longitude']) ?>"
                                        target="_blank"
                                        rel="noopener noreferrer"
                                    >
                                        Navigate
                                    </a>
                                <?php endif; ?>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                <?php else: ?>
                    <tr>
                        <td colspan="7">No incident reports found.</td>
                    </tr>
                <?php endif; ?>
            </tbody>
        </table>
    </div>

<?php endif; ?>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
