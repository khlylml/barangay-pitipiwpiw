<?php
session_start();

require_once __DIR__ . '/config/database.php';
require_once __DIR__ . '/includes/auth.php';

// Check the current user's access
$isLoggedIn = isset($_SESSION['user_id']);
$isGuest = !$isLoggedIn && ($_GET['guest'] ?? '') === '1';

$userRole = strtolower($_SESSION['role'] ?? 'resident');

$isStaff = $isLoggedIn && isStaffRole($userRole);

// Validate the incident ID
$incidentId = filter_input(
    INPUT_GET,
    'id',
    FILTER_VALIDATE_INT
);

// Determine the correct Back destination
if ($isGuest) {
    $backUrl = 'guest.php?guest=1';
} elseif ($isStaff) {
    $backUrl = 'admin/reports.php';
} else {
    $backUrl = 'my-reports.php';
}

// Require either a logged-in user or guest mode
if (!$incidentId || (!$isLoggedIn && !$isGuest)) {
    header(
        'Location: ' .
        ($isGuest ? 'guest.php?guest=1' : 'login.php')
    );
    exit;
}

$report = null;
$history = [];

// Escape output safely
function escapeDetail($value): string
{
    return htmlspecialchars(
        (string) ($value ?? ''),
        ENT_QUOTES,
        'UTF-8'
    );
}

try {
    // Retrieve the incident report using PDO
    $sql = "
        SELECT
            i.incident_id,
            i.reporter_id,
            i.incident_date,
            i.description,
            i.status,
            i.location_description,
            i.created_at,
            t.type_name,
            l.latitude,
            l.longitude,
            l.landmark,
            l.map_url,
            u.first_name,
            u.last_name,
            u.email AS reporter_email,
            u.contact_no AS reporter_contact,
            u.address AS reporter_address
        FROM incidents AS i
        LEFT JOIN users AS u
            ON i.reporter_id = u.user_id
        LEFT JOIN incident_types AS t
            ON i.incident_type_id = t.type_id
        LEFT JOIN incident_locations AS l
            ON i.incident_id = l.incident_id
        WHERE i.incident_id = :incident_id
        LIMIT 1
    ";

    $stmt = $pdo->prepare($sql);

    $stmt->execute([
        'incident_id' => $incidentId
    ]);

    $report = $stmt->fetch();

} catch (PDOException $e) {
    error_log('Report details error: ' . $e->getMessage());

    http_response_code(500);

    exit(
        'Unable to load report details. Please try again later.'
    );
}

// Report does not exist
if (!$report) {
    http_response_code(404);

    $pageTitle = 'Report Not Found';

    require __DIR__ . '/includes/header.php';
    ?>

    <section class="section-block">
        <h1>Report Not Found</h1>

        <p>
            The requested incident report does not exist
            or may have been removed.
        </p>

        <a
            class="button button-secondary"
            href="<?= escapeDetail($backUrl) ?>">
            Back
        </a>
    </section>

    <?php
    require __DIR__ . '/includes/footer.php';
    exit;
}

// Residents may only view their own reports.
// Guests can view limited public report details.
// Staff/admin can view all reports.
if (
    $isLoggedIn &&
    !$isStaff &&
    (int) $report['reporter_id'] !== (int) $_SESSION['user_id']
) {
    http_response_code(403);

    $pageTitle = 'Access Denied';

    require __DIR__ . '/includes/header.php';
    ?>

    <section class="section-block">
        <h1>Access Denied</h1>

        <p>
            You can only view incident reports that you submitted.
        </p>

        <a
            class="button button-secondary"
            href="my-reports.php">
            Back to My Reports
        </a>
    </section>

    <?php
    require __DIR__ . '/includes/footer.php';
    exit;
}

// Retrieve status history for logged-in users only
if ($isLoggedIn) {
    try {
        $historyStmt = $pdo->prepare("
            SELECT
                old_status,
                new_status,
                remarks,
                updated_at
            FROM incident_status_history
            WHERE incident_id = :incident_id
            ORDER BY updated_at DESC, history_id DESC
        ");

        $historyStmt->execute([
            'incident_id' => $incidentId
        ]);

        $history = $historyStmt->fetchAll();

    } catch (PDOException $e) {
        error_log('Status history error: ' . $e->getMessage());
    }
}

// Retrieve uploaded evidence (photo/video) for logged-in users only
$evidence = [];

if ($isLoggedIn) {
    try {
        $evidenceStmt = $pdo->prepare("
            SELECT photo_path, video_path
            FROM incident_evidence
            WHERE incident_id = :incident_id
        ");

        $evidenceStmt->execute([
            'incident_id' => $incidentId
        ]);

        $evidence = $evidenceStmt->fetchAll();

    } catch (PDOException $e) {
        error_log('Evidence error: ' . $e->getMessage());
    }
}

// Only allow files saved by the upload form.
function safeEvidencePath($path): ?string
{
    $path = (string) ($path ?? '');

    return preg_match(
        '#^uploads/incident-images/[a-f0-9]{32}\.(jpg|png|webp|mp4|webm)$#',
        $path
    ) ? $path : null;
}

$pageTitle = 'Report Details';

if (!$isGuest && $report['latitude'] !== null && $report['longitude'] !== null) {
    $extraHead = '<link rel="stylesheet" href="vendor/leaflet/leaflet.css">';
}

require __DIR__ . '/includes/header.php';
?>

<div class="report-details-page">

    <?php if (($_GET['submitted'] ?? '') === '1'): ?>
        <p class="alert alert-success">
            Your incident report has been submitted.
        </p>
    <?php endif; ?>

    <section>
        <h1>
            Incident Report #<?= (int) $report['incident_id'] ?>
        </h1>

        <p>
            Current Status:

            <span class="status <?= statusBadgeClass($report['status']) ?>">
                <?= escapeDetail($report['status']) ?>
            </span>
        </p>

        <?php $currentStep = statusStepIndex($report['status']); ?>
        <ol class="status-steps" aria-label="Report progress">
            <?php foreach (['Pending', 'Verified', 'In Progress', 'Resolved'] as $step => $label): ?>
                <li class="<?= $step < $currentStep ? 'done' : ($step === $currentStep ? 'current' : '') ?>">
                    <?= escapeDetail($label) ?>
                </li>
            <?php endforeach; ?>
        </ol>
    </section>

    <?php if (!$isGuest && isStaffRole(currentRole())): ?>
        <section class="details-card">
            <h2>Reported By</h2>

            <p><strong>Name:</strong>
                <?= escapeDetail(trim(($report['first_name'] ?? '') . ' ' . ($report['last_name'] ?? ''))) ?></p>
            <p><strong>Contact No.:</strong>
                <?= escapeDetail($report['reporter_contact'] ?? '') ?></p>
            <p><strong>Email:</strong>
                <?= escapeDetail($report['reporter_email'] ?? '') ?></p>
            <p><strong>Address:</strong>
                <?= escapeDetail($report['reporter_address'] ?? '') ?></p>
        </section>
    <?php endif; ?>

    <?php
    $aiSuggestion = null;

    if (!$isGuest && isStaffRole(currentRole())) {
        require_once __DIR__ . '/includes/ai.php';
        $aiSuggestion = latestAiClassification($pdo, (int) $incidentId);
    }
    ?>
    <?php if ($aiSuggestion): ?>
        <section class="details-card ai-suggestion">
            <h2>AI Suggestion</h2>

            <p>
                <strong>Suggested type:</strong>
                <?= escapeDetail($aiSuggestion['predicted_type']) ?>
                (<?= (int) round(((float) $aiSuggestion['confidence_score']) * 100) ?>% sure)
                <?php if (($report['type_name'] ?? '') !== $aiSuggestion['predicted_type']): ?>
                    <span class="status status-pending">Different from the reporter's choice</span>
                <?php endif; ?>
            </p>
            <p class="muted">
                Suggested by <?= escapeDetail($aiSuggestion['classification_source']) ?>.
                This is only a guide. Barangay staff make the final decision.
            </p>
        </section>
    <?php endif; ?>

    <section class="details-card">
        <h2>Incident Information</h2>

        <p>
            <strong>Incident Type:</strong>

            <?= escapeDetail(
                $report['type_name'] ?? 'Uncategorized'
            ) ?>
        </p>

        <p>
            <strong>Incident Date:</strong>

            <?= escapeDetail($report['incident_date']) ?>
        </p>

        <p>
            <strong>Reported On:</strong>

            <?= escapeDetail(
                $report['created_at']
                    ? date('M j, Y g:i A', strtotime($report['created_at']))
                    : ''
            ) ?>
        </p>

        <p>
            <strong>Location:</strong>

            <?= escapeDetail($report['location_description']) ?>
        </p>

        <?php if (!$isGuest): ?>

            <p>
                <strong>Description:</strong><br>

                <?= nl2br(
                    escapeDetail($report['description'])
                ) ?>
            </p>

            <?php if (!empty($report['landmark'])): ?>

                <p>
                    <strong>Nearby Landmark:</strong>

                    <?= escapeDetail($report['landmark']) ?>
                </p>

            <?php endif; ?>

        <?php else: ?>

            <p>
                Guest access is limited to incident summaries,
                locations, dates, and current statuses.
                Log in or register to access resident features.
            </p>

        <?php endif; ?>
    </section>

    <?php if (
        !$isGuest &&
        $report['latitude'] !== null &&
        $report['longitude'] !== null
    ): ?>

        <section class="details-card">
            <h2>Location on Map</h2>

            <div
                id="incidentMap"
                class="location-map"
                data-lat="<?= escapeDetail($report['latitude']) ?>"
                data-lng="<?= escapeDetail($report['longitude']) ?>"
                role="img"
                aria-label="Map showing where the incident happened"
            ></div>


            <div class="details-actions">
                <a
                    class="button button-primary"
                    href="https://www.google.com/maps/dir/?api=1&amp;destination=<?= rawurlencode($report['latitude'] . ',' . $report['longitude']) ?>"
                    target="_blank"
                    rel="noopener noreferrer">
                    Navigate to Location
                </a>

            </div>
        </section>

        <script src="vendor/leaflet/leaflet.js"></script>
        <script>
        document.addEventListener('DOMContentLoaded', function () {
            const element = document.getElementById('incidentMap');
            const lat = parseFloat(element.dataset.lat);
            const lng = parseFloat(element.dataset.lng);
            const map = L.map(element).setView([lat, lng], 17);

            L.tileLayer('https://tile.openstreetmap.org/{z}/{x}/{y}.png', {
                maxZoom: 19,
                attribution: '&copy; OpenStreetMap contributors'
            }).addTo(map);

            L.marker([lat, lng]).addTo(map);
        });
        </script>

    <?php endif; ?>

    <?php if ($isLoggedIn && !empty($evidence)): ?>

        <section class="details-card">
            <h2>Evidence</h2>

            <?php foreach ($evidence as $item): ?>
                <?php $photo = safeEvidencePath($item['photo_path']); ?>
                <?php $video = safeEvidencePath($item['video_path']); ?>

                <?php if ($photo): ?>
                    <a href="<?= escapeDetail($photo) ?>" target="_blank" rel="noopener">
                        <img
                            class="evidence-media"
                            src="<?= escapeDetail($photo) ?>"
                            alt="Photo evidence for report #<?= (int) $report['incident_id'] ?>"
                        >
                    </a>
                <?php endif; ?>

                <?php if ($video): ?>
                    <video
                        class="evidence-media"
                        src="<?= escapeDetail($video) ?>"
                        controls
                        preload="metadata"
                    ></video>
                <?php endif; ?>
            <?php endforeach; ?>
        </section>

    <?php endif; ?>

    <?php if ($isLoggedIn): ?>

        <section class="details-card">
            <h2>Status History</h2>

            <?php if (!empty($history)): ?>

                <?php foreach ($history as $item): ?>

                    <div class="history-item">

                        <strong>
                            <?= escapeDetail(
                                $item['old_status']
                            ) ?>

                            &rarr;

                            <?= escapeDetail(
                                $item['new_status']
                            ) ?>
                        </strong>

                        <span class="history-date">
                            <?= escapeDetail(
                                $item['updated_at']
                            ) ?>
                        </span>

                        <?php if (!empty($item['remarks'])): ?>

                            <p>
                                <?= nl2br(
                                    escapeDetail($item['remarks'])
                                ) ?>
                            </p>

                        <?php endif; ?>

                    </div>

                <?php endforeach; ?>

            <?php else: ?>

                <p>
                    No status updates have been recorded yet.
                </p>

            <?php endif; ?>
        </section>

    <?php endif; ?>

    <div class="details-actions">

        <a
            class="button button-secondary"
            href="<?= escapeDetail($backUrl) ?>">

            Back
        </a>

    </div>

</div>

<?php require __DIR__ . '/includes/footer.php'; ?>
