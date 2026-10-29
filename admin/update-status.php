<?php
session_start();

require_once __DIR__ . '/../includes/auth.php';

// Only staff, admin and super admin can update incident statuses.
if (!isset($_SESSION['user_id']) || !isStaffRole(currentRole())) {
    header('Location: ../login.php');
    exit;
}

require_once __DIR__ . '/../config/database.php';

$incidentId = filter_input(INPUT_GET, 'id', FILTER_VALIDATE_INT);

if (!$incidentId || $incidentId < 1) {
    header('Location: reports.php');
    exit;
}

require_once __DIR__ . '/../includes/reports.php';

$error = '';
$incident = null;

// Get the selected incident.
try {
    $stmt = $pdo->prepare(
        'SELECT incident_id, status
         FROM incidents
         WHERE incident_id = :incident_id
         LIMIT 1'
    );

    $stmt->execute([
        'incident_id' => $incidentId
    ]);

    $incident = $stmt->fetch();

} catch (PDOException $e) {
    error_log($e->getMessage());
    http_response_code(500);
    exit('Unable to load the incident report.');
}

if (!$incident) {
    http_response_code(404);
    exit('Incident report not found.');
}

// Process the status update.
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $newStatus = trim($_POST['status'] ?? '');
    $csrfOk = csrfIsValid($_POST['csrf_token'] ?? null);
    $remarks = trim($_POST['remarks'] ?? '');

    if (!$csrfOk) {
        $error = 'Your session expired. Please try again.';

    } else {
        try {
            updateIncidentStatus(
                $pdo,
                (int) $incidentId,
                $newStatus,
                $remarks,
                (int) $_SESSION['user_id']
            );

            header('Location: reports.php?updated=1');
            exit;

        } catch (InvalidArgumentException $e) {
            $error = $e->getMessage();
        } catch (Throwable $e) {
            error_log($e->getMessage());
            $error = 'The status could not be updated. Please try again.';
        }
    }
}

$allowedStatuses = allowedNextStatuses((string) $incident['status']);

$basePath = '../';
$pageTitle = 'Update Incident Status';

require_once __DIR__ . '/../includes/header.php';
?>

<section class="page-heading">
    <h1>Update Incident Status</h1>

    <p>Report #<?= (int) $incidentId ?></p>

    <p>
        Current status:
        <strong>
            <?= htmlspecialchars(
                normalizeStatus($incident['status']),
                ENT_QUOTES,
                'UTF-8'
            ) ?>
        </strong>
    </p>
</section>

<?php if ($error !== ''): ?>
    <p class="alert alert-error">
        <?= htmlspecialchars($error, ENT_QUOTES, 'UTF-8') ?>
    </p>
<?php endif; ?>

<form method="POST" class="form-layout single-column">
    <input type="hidden" name="csrf_token" value="<?= htmlspecialchars(csrfToken(), ENT_QUOTES, 'UTF-8') ?>">

    <div class="form-field">
        <label for="status">New Status</label>

        <select name="status" id="status" required>
            <option value="">Choose status</option>

            <?php foreach ($allowedStatuses as $status): ?>
                <option
                    value="<?= htmlspecialchars($status, ENT_QUOTES, 'UTF-8') ?>"
                    <?= (($_POST['status'] ?? '') === $status) ? 'selected' : '' ?>
                >
                    <?= htmlspecialchars($status, ENT_QUOTES, 'UTF-8') ?>
                </option>
            <?php endforeach; ?>
        </select>
    </div>

    <div class="form-field">
        <label for="remarks">Remarks (optional)</label>

        <textarea
            name="remarks"
            id="remarks"
            rows="4"
        ><?= htmlspecialchars(
            $_POST['remarks'] ?? '',
            ENT_QUOTES,
            'UTF-8'
        ) ?></textarea>
    </div>

    <div class="form-actions">
        <button type="submit" class="button button-primary">
            Save Status
        </button>

        <a class="button button-secondary" href="reports.php">
            Cancel
        </a>
    </div>

</form>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
