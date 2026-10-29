<?php
session_start();

require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/auth.php';

// Only staff, admin and super admin can access this page.
if (!isset($_SESSION['user_id']) || !isStaffRole(currentRole())) {
    header('Location: ../login.php');
    exit;
}

$counts = [
    'total' => 0,
    'Pending' => 0,
    'Verified' => 0,
    'In Progress' => 0,
    'Resolved' => 0,
    'Archived' => 0,
];
$recent = [];
$userCount = null;
$error = '';

try {
    foreach ($pdo->query(
        'SELECT status, COUNT(*) AS total FROM incidents GROUP BY status'
    )->fetchAll() as $row) {
        $status = $row['status'] === 'Completed' ? 'Resolved' : $row['status'];

        if (isset($counts[$status])) {
            $counts[$status] += (int) $row['total'];
        }

        if ($status !== 'Archived') {
            $counts['total'] += (int) $row['total'];
        }
    }

    $recent = $pdo->query(
        "SELECT i.incident_id, i.incident_date, i.status,
                i.location_description, t.type_name
         FROM incidents i
         LEFT JOIN incident_types t ON i.incident_type_id = t.type_id
         ORDER BY i.created_at DESC
         LIMIT 6"
    )->fetchAll();

    if (isSuperAdmin()) {
        $userCount = (int) $pdo->query('SELECT COUNT(*) FROM users')->fetchColumn();
    }
} catch (PDOException $e) {
    error_log('Admin dashboard error: ' . $e->getMessage());
    $error = 'Unable to load the dashboard statistics. Please check your database connection.';
}

function escapeDashboard($value): string
{
    return htmlspecialchars((string) ($value ?? ''), ENT_QUOTES, 'UTF-8');
}

$basePath = '../';
$pageTitle = 'Admin Dashboard';

require __DIR__ . '/../includes/header.php';
?>

<section class="page-heading">
    <h1>Incident Management Dashboard</h1>
    <p>
        Welcome, <?= escapeDashboard($_SESSION['first_name'] ?? 'Staff') ?>!
        Review submitted incident reports and monitor their progress.
    </p>
</section>

<?php if ($error !== ''): ?>
    <p class="alert alert-error" role="alert"><?= escapeDashboard($error) ?></p>
<?php endif; ?>

<section class="stat-grid" aria-label="Incident report statistics">
    <a class="stat-card" href="reports.php">
        <span>Total Reports</span>
        <strong><?= $counts['total'] ?></strong>
    </a>

    <a class="stat-card" href="reports.php?status=Pending">
        <span>Pending</span>
        <strong><?= $counts['Pending'] ?></strong>
    </a>

    <a class="stat-card" href="reports.php?status=Verified">
        <span>Verified</span>
        <strong><?= $counts['Verified'] ?></strong>
    </a>

    <a class="stat-card" href="reports.php?status=In%20Progress">
        <span>In Progress</span>
        <strong><?= $counts['In Progress'] ?></strong>
    </a>

    <a class="stat-card" href="reports.php?status=Resolved">
        <span>Resolved</span>
        <strong><?= $counts['Resolved'] ?></strong>
    </a>

    <a class="stat-card" href="reports.php?status=Archived">
        <span>Archived</span>
        <strong><?= $counts['Archived'] ?></strong>
    </a>

    <?php if ($userCount !== null): ?>
        <a class="stat-card" href="users.php">
            <span>User Accounts</span>
            <strong><?= $userCount ?></strong>
        </a>
    <?php endif; ?>
</section>

<section class="section-block">
    <h2>Latest Reports</h2>

    <div class="table-wrap">
        <table class="data-table">
            <thead>
                <tr>
                    <th>ID</th>
                    <th>Incident Type</th>
                    <th>Date</th>
                    <th>Location</th>
                    <th>Status</th>
                    <th>Action</th>
                </tr>
            </thead>

            <tbody>
                <?php if ($recent): ?>
                    <?php foreach ($recent as $report): ?>
                        <tr>
                            <td>#<?= (int) $report['incident_id'] ?></td>
                            <td><?= escapeDashboard($report['type_name'] ?? 'Other') ?></td>
                            <td><?= escapeDashboard($report['incident_date']) ?></td>
                            <td><?= escapeDashboard($report['location_description']) ?></td>
                            <td>
                                <span class="status <?php
                                    echo statusBadgeClass($report['status']);
                                ?>">
                                    <?= escapeDashboard($report['status']) ?>
                                </span>
                            </td>
                            <td class="row-actions">
                                <a href="update-status.php?id=<?= (int) $report['incident_id'] ?>">
                                    Update Status
                                </a>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                <?php else: ?>
                    <tr><td colspan="6">No incident reports yet.</td></tr>
                <?php endif; ?>
            </tbody>
        </table>
    </div>

    <p class="view-all-link">
        <a href="reports.php">Manage all incident reports</a>
    </p>
</section>

<?php require __DIR__ . '/../includes/footer.php'; ?>
