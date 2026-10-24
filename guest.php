<?php
session_start();
require_once 'config/database.php';
require_once 'includes/auth.php';

// Guest access: view reports only.
$_SESSION['guest'] = true;

$error = '';
$reports = [];

try {
    $stmt = $pdo->query(
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
         ORDER BY i.created_at DESC"
    );

    $reports = $stmt->fetchAll();

} catch (PDOException $e) {
    error_log($e->getMessage());
    $error = 'Unable to load incident reports. Please try again later.';
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

    <?php if ($error !== ''): ?>

        <p class="alert alert-error">
            <?= escapeGuest($error) ?>
        </p>

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
