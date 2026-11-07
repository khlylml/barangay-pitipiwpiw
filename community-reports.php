<?php
session_start();

require_once __DIR__ . '/config/database.php';
require_once __DIR__ . '/includes/auth.php';

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

// Public summary only: no reporter name, no description, no photos.
try {
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
         LIMIT 100"
    )->fetchAll();
} catch (PDOException $e) {
    error_log($e->getMessage());
    $error = 'Unable to load community reports. Please try again later.';
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

        <div class="guest-table-wrap">
            <table class="guest-table">
                <thead>
                    <tr>
                        <th>Report No.</th>
                        <th>Incident Type</th>
                        <th>Date</th>
                        <th>Location</th>
                        <th>Status</th>
                    </tr>
                </thead>

                <tbody>
                    <?php foreach ($reports as $report): ?>
                        <tr>
                            <td>#<?= (int) $report['incident_id'] ?></td>
                            <td><?= escapeCommunity($report['type_name'] ?? 'Uncategorized') ?></td>
                            <td><?= escapeCommunity($report['incident_date']) ?></td>
                            <td><?= escapeCommunity($report['location_description']) ?></td>
                            <td>
                                <span class="status <?= statusBadgeClass($report['status']) ?>">
                                    <?= escapeCommunity($report['status']) ?>
                                </span>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>

    <?php endif; ?>

</div>

<?php require __DIR__ . '/includes/footer.php'; ?>
