<?php
session_start();
require_once 'config/database.php';
require_once 'includes/auth.php';

// Guest access: view reports only.
$_SESSION['guest'] = true;

$error = '';
$reports = [];
$types = [];
$counts = ['total' => 0, 'pending' => 0, 'progress' => 0, 'resolved' => 0];

// Hotlines shown to guests. Add your real barangay numbers here;
// a line with an empty number is not shown.
$hotlines = [
    'National Emergency Hotline' => '911',
    'Barangay Hall'              => '',
    'Health Center'              => '',
    'Police Station'             => '',
    'Fire Station'               => '',
];

$perPage = 10;
$page = max(1, (int) ($_GET['page'] ?? 1));
$statusFilter = (string) ($_GET['status'] ?? '');
$typeFilter = (int) ($_GET['type'] ?? 0);
$search = trim((string) ($_GET['q'] ?? ''));

$statusOptions = ['Pending', 'Verified', 'In Progress', 'Resolved'];
if (!in_array($statusFilter, $statusOptions, true)) {
    $statusFilter = '';
}

$totalRows = 0;
$totalPages = 1;

try {
    $types = $pdo->query('SELECT type_id, type_name FROM incident_types ORDER BY type_name')->fetchAll();

    foreach ($pdo->query(
        "SELECT status, COUNT(*) AS total FROM incidents WHERE status <> 'Archived' GROUP BY status"
    )->fetchAll() as $row) {
        $counts['total'] += (int) $row['total'];
        if ($row['status'] === 'Pending') { $counts['pending'] += (int) $row['total']; }
        if ($row['status'] === 'In Progress') { $counts['progress'] += (int) $row['total']; }
        if (in_array($row['status'], ['Resolved', 'Completed'], true)) { $counts['resolved'] += (int) $row['total']; }
    }

    $where = ["i.status <> 'Archived'"];
    $params = [];

    if ($statusFilter === 'Resolved') {
        $where[] = "i.status IN ('Resolved', 'Completed')";
    } elseif ($statusFilter !== '') {
        $where[] = 'i.status = ?';
        $params[] = $statusFilter;
    }

    if ($typeFilter > 0) {
        $where[] = 'i.incident_type_id = ?';
        $params[] = $typeFilter;
    }

    if ($search !== '') {
        $where[] = '(i.location_description LIKE ? OR t.type_name LIKE ?)';
        $like = '%' . $search . '%';
        array_push($params, $like, $like);
    }

    $whereSql = 'WHERE ' . implode(' AND ', $where);

    $countStmt = $pdo->prepare(
        "SELECT COUNT(*) FROM incidents i
         LEFT JOIN incident_types t ON i.incident_type_id = t.type_id $whereSql"
    );
    $countStmt->execute($params);
    $totalRows = (int) $countStmt->fetchColumn();
    $totalPages = max(1, (int) ceil($totalRows / $perPage));
    $page = min($page, $totalPages);
    $offset = ($page - 1) * $perPage;

    $stmt = $pdo->prepare(
        "SELECT
            i.incident_id,
            i.incident_date,
            i.status,
            i.created_at,
            t.type_name,
            i.location_description
         FROM incidents i
         LEFT JOIN incident_types t
            ON i.incident_type_id = t.type_id
         $whereSql
         ORDER BY i.created_at DESC
         LIMIT $perPage OFFSET $offset"
    );
    $stmt->execute($params);
    $reports = $stmt->fetchAll();

} catch (PDOException $e) {
    error_log($e->getMessage());
    $error = 'Unable to load incident reports. Please try again later.';
}

function pageLink(int $target): string
{
    $query = $_GET;
    $query['page'] = $target;
    $query['guest'] = '1';
    return 'guest.php?' . http_build_query($query);
}

$pageTitle = 'Guest - Incident Reports';
require 'includes/header.php';

function escapeGuest($value): string
{
    return htmlspecialchars((string) ($value ?? ''), ENT_QUOTES, 'UTF-8');
}
?>
<div class="guest-page">

    <section class="guest-heading">
        <h1>Incident Reports</h1>
        <p>Welcome, Guest! You can view reported incidents and their current status.</p>
    </section>

    <div class="guest-notice">
        <strong>Guest Access:</strong>
        You can view incident summaries and statuses only.
        Log in or register to submit and manage your own reports.
    </div>

    <section class="guest-stats" aria-label="Report summary">
        <div><strong><?= $counts['total'] ?></strong><span>Total</span></div>
        <div><strong><?= $counts['pending'] ?></strong><span>Pending</span></div>
        <div><strong><?= $counts['progress'] ?></strong><span>In Progress</span></div>
        <div><strong><?= $counts['resolved'] ?></strong><span>Resolved</span></div>
    </section>

    <form method="GET" class="guest-filter" role="search">
        <input type="hidden" name="guest" value="1">

        <label class="sr-only" for="q">Search</label>
        <input type="search" id="q" name="q" value="<?= escapeGuest($search) ?>"
               placeholder="Search location or type">

        <label class="sr-only" for="status">Status</label>
        <select id="status" name="status">
            <option value="">All statuses</option>
            <?php foreach ($statusOptions as $option): ?>
                <option value="<?= escapeGuest($option) ?>"
                    <?= $statusFilter === $option ? 'selected' : '' ?>><?= escapeGuest($option) ?></option>
            <?php endforeach; ?>
        </select>

        <label class="sr-only" for="type">Incident type</label>
        <select id="type" name="type">
            <option value="0">All types</option>
            <?php foreach ($types as $type): ?>
                <option value="<?= (int) $type['type_id'] ?>"
                    <?= $typeFilter === (int) $type['type_id'] ? 'selected' : '' ?>><?= escapeGuest($type['type_name']) ?></option>
            <?php endforeach; ?>
        </select>

        <button type="submit" class="button button-primary">Filter</button>
    </form>

    <?php if ($error !== ''): ?>

        <p class="alert alert-error">
            <?= escapeGuest($error) ?>
        </p>

    <?php elseif (empty($reports)): ?>

        <div class="guest-empty">
            <p>No incident reports found.</p>
        </div>

    <?php else: ?>

        <div class="guest-table-wrap">
            <table class="guest-table">
                <thead>
                    <tr>
                        <th>Report No.</th>
                        <th>Incident Type</th>
                        <th>Date</th>
                        <th>Location</th>
                        <th>Status</th>
                        <th>Action</th>
                    </tr>
                </thead>

                <tbody>
                    <?php foreach ($reports as $report): ?>
                        <tr>
                            <td>
                                #<?= (int) $report['incident_id'] ?>
                            </td>

                            <td>
                                <?= escapeGuest(
                                    $report['type_name'] ?? 'Uncategorized'
                                ) ?>
                            </td>

                            <td>
                                <?= escapeGuest($report['incident_date']) ?>
                            </td>

                            <td>
                                <?= escapeGuest(
                                    $report['location_description']
                                ) ?>
                            </td>

                            <td>
                                <span class="status <?= statusBadgeClass($report['status']) ?>">
                                    <?= escapeGuest($report['status']) ?>
                                </span>
                            </td>

                            <td>
                                <a
                                    class="guest-view-link"
                                    href="report-details.php?id=<?= (int) $report['incident_id'] ?>&amp;guest=1"
                                >
                                    View Details
                                </a>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>

    <?php endif; ?>

    <?php if ($totalPages > 1): ?>
        <nav class="guest-pager" aria-label="Pages">
            <?php if ($page > 1): ?>
                <a href="<?= escapeGuest(pageLink($page - 1)) ?>">&larr; Previous</a>
            <?php endif; ?>
            <span>Page <?= $page ?> of <?= $totalPages ?></span>
            <?php if ($page < $totalPages): ?>
                <a href="<?= escapeGuest(pageLink($page + 1)) ?>">Next &rarr;</a>
            <?php endif; ?>
        </nav>
    <?php endif; ?>

    <section class="guest-hotlines" aria-label="Emergency hotlines">
        <h2>Emergency hotlines</h2>
        <p>In an emergency, call first. This system is for reporting and tracking, not for urgent response.</p>
        <ul>
            <?php foreach ($hotlines as $label => $number): ?>
                <?php if ($number !== ''): ?>
                    <li><span><?= escapeGuest($label) ?></span> <strong><?= escapeGuest($number) ?></strong></li>
                <?php endif; ?>
            <?php endforeach; ?>
        </ul>
    </section>

    <div class="guest-actions">
        <a class="button button-primary" href="login.php">
            Log In
        </a>

        <a class="button button-secondary" href="register.php">
            Register
        </a>

        <a class="button button-secondary" href="index.php">
            Back to Home
        </a>
    </div>

</div>

<?php require 'includes/footer.php'; ?>
