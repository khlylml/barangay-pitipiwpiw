<?php
// Report Confirmation page: shown right after a report is submitted.
// It reads the saved report from the database, so the details are always real.
session_start();

require_once __DIR__ . '/config/database.php';
require_once __DIR__ . '/includes/auth.php';
require_once __DIR__ . '/includes/reports.php';

if (!isset($_SESSION['user_id'])) {
    header('Location: login.php');
    exit;
}

$incidentId = filter_input(INPUT_GET, 'id', FILTER_VALIDATE_INT);

if (!$incidentId || $incidentId < 1) {
    header('Location: dashboard.php');
    exit;
}

function escapeConfirm($value): string
{
    return htmlspecialchars((string) ($value ?? ''), ENT_QUOTES, 'UTF-8');
}

$report = null;
$photo = '';

try {
    $stmt = $pdo->prepare(
        'SELECT i.incident_id, i.reporter_id, i.status, i.created_at,
                i.incident_date, i.location_description,
                t.type_name, l.latitude, l.longitude
         FROM incidents i
         LEFT JOIN incident_types t ON i.incident_type_id = t.type_id
         LEFT JOIN incident_locations l ON l.incident_id = i.incident_id
         WHERE i.incident_id = ? LIMIT 1'
    );
    $stmt->execute([$incidentId]);
    $report = $stmt->fetch();

    $evidence = $pdo->prepare(
        'SELECT photo_path FROM incident_evidence
         WHERE incident_id = ? AND photo_path IS NOT NULL LIMIT 1'
    );
    $evidence->execute([$incidentId]);
    $path = (string) $evidence->fetchColumn();

    if (preg_match('#^uploads/incident-images/[a-f0-9]{32}\.(jpg|png|webp)$#', $path)) {
        $photo = $path;
    }
} catch (PDOException $e) {
    error_log('Confirmation page error: ' . $e->getMessage());
}

// Residents can only see their own confirmation; officials can see any.
if (
    !$report ||
    (!isStaffRole(currentRole()) && (int) $report['reporter_id'] !== (int) $_SESSION['user_id'])
) {
    http_response_code(404);
    $pageTitle = 'Report Not Found';
    require __DIR__ . '/includes/header.php';
    echo '<section class="page-heading"><h1>Report not found</h1>'
        . '<p><a href="dashboard.php">Back to Dashboard</a></p></section>';
    require __DIR__ . '/includes/footer.php';
    exit;
}

$status = normalizeStatus($report['status']);
$reportCode = 'BR-' . date('Y', strtotime((string) $report['created_at']))
    . '-' . str_pad((string) $report['incident_id'], 4, '0', STR_PAD_LEFT);
$submittedAt = date('M j, Y g:i A', strtotime((string) $report['created_at']));

$pageTitle = 'Report Confirmation';
require __DIR__ . '/includes/header.php';
?>

<section class="confirmation">
    <div class="confirmation-head">
        <div class="confirmation-icon" aria-hidden="true">&#10003;</div>
        <h1>Report Submitted Successfully!</h1>
        <p>Your incident report has been received.</p>
    </div>

    <p class="confirmation-status">
        <strong>Status: <?= escapeConfirm($status) ?></strong><br>
        Your report is waiting for barangay officials to review it.
    </p>

    <h2>Report Details</h2>

    <div class="detail-rows">
        <div class="detail-row">
            <span>Report ID</span>
            <strong><?= escapeConfirm($reportCode) ?></strong>
        </div>

        <div class="detail-row">
            <span>Incident Type</span>
            <strong><?= escapeConfirm($report['type_name'] ?? 'Other') ?></strong>
        </div>

        <div class="detail-row">
            <span>Photo</span>
            <div>
                <?php if ($photo !== ''): ?>
                    <img src="<?= escapeConfirm($photo) ?>"
                         alt="Incident photo" class="confirmation-photo">
                <?php else: ?>
                    <span class="muted">No photo provided</span>
                <?php endif; ?>
            </div>
        </div>

        <div class="detail-row">
            <span>Location</span>
            <strong><?= escapeConfirm($report['location_description'] ?: 'Location not provided') ?></strong>
        </div>

        <div class="detail-row">
            <span>Date &amp; Time</span>
            <strong><?= escapeConfirm($submittedAt) ?></strong>
        </div>

        <div class="detail-row">
            <span>Status</span>
            <span class="status <?= statusBadgeClass($status) ?>"><?= escapeConfirm($status) ?></span>
        </div>
    </div>

    <div class="form-actions">
        <a href="dashboard.php" class="button button-primary">Back to Dashboard</a>
        <a href="report-details.php?id=<?= (int) $report['incident_id'] ?>"
           class="button button-secondary">View Full Report</a>
    </div>
</section>

<?php require __DIR__ . '/includes/footer.php'; ?>
