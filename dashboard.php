<?php
session_start();

require_once __DIR__ . '/config/database.php';
require_once __DIR__ . '/includes/auth.php';

// Resident dashboard requires login
if (!isset($_SESSION['user_id'])) {
    header('Location: login.php');
    exit;
}

// Admin and staff have their own dashboard
$role = strtolower($_SESSION['role'] ?? 'resident');

if (isStaffRole($role)) {
    header('Location: admin/dashboard.php');
    exit;
}

$userId = (int) $_SESSION['user_id'];
$firstName = $_SESSION['first_name'] ?? 'Resident';

$totalReports = 0;
$pendingReports = 0;
$inProgressReports = 0;
$completedReports = 0;
$recentReports = [];

function escapeOutput($value)
{
    return htmlspecialchars((string) $value, ENT_QUOTES, 'UTF-8');
}

try {
    // Get resident's report summary
    $sql = "SELECT
                COUNT(*) AS total,
                COALESCE(SUM(CASE
                    WHEN status = 'Pending' THEN 1 ELSE 0
                END), 0) AS pending,
                COALESCE(SUM(CASE
                    WHEN status = 'In Progress' THEN 1 ELSE 0
                END), 0) AS in_progress,
                COALESCE(SUM(CASE
                    WHEN status IN ('Resolved', 'Completed')
                    THEN 1 ELSE 0
                END), 0) AS completed
            FROM incidents
            WHERE reporter_id = ?";

    $stmt = $pdo->prepare($sql);
    $stmt->execute([$userId]);
    $summary = $stmt->fetch();

    if ($summary) {
        $totalReports = (int) $summary['total'];
        $pendingReports = (int) $summary['pending'];
        $inProgressReports = (int) $summary['in_progress'];
        $completedReports = (int) $summary['completed'];
    }

    // Get the resident's five most recent reports
    $sql = "SELECT
                i.incident_id,
                i.description,
                i.status,
                i.incident_date,
                i.location_description,
                t.type_name
            FROM incidents AS i
            LEFT JOIN incident_types AS t
                ON i.incident_type_id = t.type_id
            WHERE i.reporter_id = ?
            ORDER BY i.created_at DESC
            LIMIT 5";

    $stmt = $pdo->prepare($sql);
    $stmt->execute([$userId]);
    $recentReports = $stmt->fetchAll();

} catch (PDOException $e) {
    error_log('Resident dashboard database error: ' . $e->getMessage());
    $pageError = 'Unable to load your reports right now. Please try again later.';
}

$pageTitle = 'Resident Dashboard';
require __DIR__ . '/includes/header.php';
?>

<div class="dashboard-page">

    <section class="dashboard-welcome">
        <h1>Welcome, <?= escapeOutput($firstName) ?>!</h1>
        <p>
            Here you can submit an incident report and monitor
            the status of your reports.
        </p>
    </section>

    <?php if (!empty($pageError)): ?>
        <p class="alert alert-error">
            <?= escapeOutput($pageError) ?>
        </p>
    <?php endif; ?>

    <section class="dashboard-summary" aria-label="Report summary">
        <div class="summary-item">
            <p>Total Reports</p>
            <strong><?= $totalReports ?></strong>
        </div>

        <div class="summary-item">
            <p>Pending</p>
            <strong><?= $pendingReports ?></strong>
        </div>

        <div class="summary-item">
            <p>In Progress</p>
            <strong><?= $inProgressReports ?></strong>
        </div>

        <div class="summary-item">
            <p>Completed / Resolved</p>
            <strong><?= $completedReports ?></strong>
        </div>
    </section>

    <section>
        <h2>My Recent Reports</h2>

        <?php if (empty($recentReports)): ?>
            <p class="empty-reports">
                You haven't submitted any incident reports yet.
            </p>
        <?php else: ?>

            <div class="reports-table-wrap">
                <table class="reports-table">
                    <thead>
                        <tr>
                            <th>Report ID</th>
                            <th>Incident Type</th>
                            <th>Date</th>
                            <th>Status</th>
                            <th>Details</th>
                        </tr>
                    </thead>

                    <tbody>
                        <?php foreach ($recentReports as $report): ?>
                            <tr>
                                <td>
                                    #<?= (int) $report['incident_id'] ?>
                                </td>

                                <td>
                                    <?= escapeOutput(
                                        $report['type_name'] ?? 'Uncategorized'
                                    ) ?>
                                </td>

                                <td>
                                    <?= escapeOutput($report['incident_date']) ?>
                                </td>

                                <td>
                                    <span class="status <?= statusBadgeClass($report['status']) ?>">
                                        <?= escapeOutput($report['status']) ?>
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

            <p class="view-all-link">
                <a href="my-reports.php">View all my reports</a>
            </p>

        <?php endif; ?>
    </section>

</div>

<?php require __DIR__ . '/includes/footer.php'; ?>
