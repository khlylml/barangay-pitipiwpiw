<?php
session_start();

require_once __DIR__ . '/config/database.php';
require_once __DIR__ . '/includes/auth.php';

// Login required.
if (!isset($_SESSION['user_id'])) {
    header('Location: login.php');
    exit;
}

$userId = (int) $_SESSION['user_id'];

csrfToken();

function escapeProfile($value): string
{
    return htmlspecialchars((string) ($value ?? ''), ENT_QUOTES, 'UTF-8');
}

$profileError = '';
$profileSuccess = '';
$passwordError = '';
$passwordSuccess = '';

// Load the current user.
try {
    $stmt = $pdo->prepare(
        'SELECT user_id, first_name, last_name, contact_no, email,
                address, role, password_hash
         FROM users
         WHERE user_id = ?
         LIMIT 1'
    );
    $stmt->execute([$userId]);
    $user = $stmt->fetch();
} catch (PDOException $e) {
    error_log('Profile load error: ' . $e->getMessage());
    http_response_code(500);
    exit('Unable to load your profile. Please try again later.');
}

if (!$user) {
    // Account no longer exists.
    header('Location: logout.php');
    exit;
}

$form = [
    'first_name' => $user['first_name'],
    'last_name' => $user['last_name'],
    'contact_no' => $user['contact_no'],
    'address' => $user['address'],
];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $token = $_POST['csrf_token'] ?? '';
    $action = $_POST['action'] ?? '';

    if (!hash_equals($_SESSION['csrf_token'], $token)) {
        $profileError = 'Your session expired. Please try again.';

    } elseif ($action === 'profile') {
        $form['first_name'] = trim($_POST['first_name'] ?? '');
        $form['last_name'] = trim($_POST['last_name'] ?? '');
        $form['contact_no'] = trim($_POST['contact_no'] ?? '');
        $form['address'] = trim($_POST['address'] ?? '');

        if (
            $form['first_name'] === '' || $form['last_name'] === '' ||
            $form['contact_no'] === '' || $form['address'] === ''
        ) {
            $profileError = 'Please complete all fields.';
        } elseif (!preg_match('/^[0-9+\-\s()]{7,20}$/', $form['contact_no'])) {
            $profileError = 'Please enter a valid contact number.';
        } else {
            try {
                $update = $pdo->prepare(
                    'UPDATE users
                     SET first_name = ?, last_name = ?,
                         contact_no = ?, address = ?
                     WHERE user_id = ?'
                );
                $update->execute([
                    $form['first_name'],
                    $form['last_name'],
                    $form['contact_no'],
                    $form['address'],
                    $userId
                ]);

                $_SESSION['first_name'] = $form['first_name'];
                $_SESSION['last_name'] = $form['last_name'];
                $user = array_merge($user, $form);
                $profileSuccess = 'Your profile has been updated.';
            } catch (PDOException $e) {
                error_log('Profile update error: ' . $e->getMessage());
                $profileError = 'Your profile could not be updated.';
            }
        }

    } elseif ($action === 'password') {
        $current = $_POST['current_password'] ?? '';
        $new = $_POST['new_password'] ?? '';
        $confirm = $_POST['confirm_new_password'] ?? '';

        if (!password_verify($current, $user['password_hash'])) {
            $passwordError = 'Your current password is incorrect.';
        } elseif (strlen($new) < 8) {
            $passwordError = 'Your new password must contain at least 8 characters.';
        } elseif ($new !== $confirm) {
            $passwordError = 'The new passwords do not match.';
        } else {
            try {
                $update = $pdo->prepare(
                    'UPDATE users SET password_hash = ? WHERE user_id = ?'
                );
                $update->execute([
                    password_hash($new, PASSWORD_DEFAULT),
                    $userId
                ]);

                session_regenerate_id(true);
                $passwordSuccess = 'Your password has been changed.';
            } catch (PDOException $e) {
                error_log('Password update error: ' . $e->getMessage());
                $passwordError = 'Your password could not be changed.';
            }
        }
    }
}

// Report totals (residents only).
$isStaff = isStaffRole($user['role']);
$stats = ['total' => 0, 'pending' => 0, 'in_progress' => 0, 'resolved' => 0];

if (!$isStaff) {
    try {
        $statStmt = $pdo->prepare(
            "SELECT
                COUNT(*) AS total,
                COALESCE(SUM(status = 'Pending'), 0) AS pending,
                COALESCE(SUM(status = 'In Progress'), 0) AS in_progress,
                COALESCE(SUM(status IN ('Resolved', 'Completed')), 0) AS resolved
             FROM incidents
             WHERE reporter_id = ?"
        );
        $statStmt->execute([$userId]);
        $row = $statStmt->fetch();

        if ($row) {
            $stats = array_map('intval', $row);
        }
    } catch (PDOException $e) {
        error_log('Profile stats error: ' . $e->getMessage());
    }
}

$initials = strtoupper(
    mb_substr($user['first_name'], 0, 1) . mb_substr($user['last_name'], 0, 1)
);

$pageTitle = 'My Profile';
require __DIR__ . '/includes/header.php';
?>

<section class="profile-summary">
    <div class="profile-avatar" aria-hidden="true">
        <?= escapeProfile($initials) ?>
    </div>

    <div>
        <h1>
            <?= escapeProfile($user['first_name'] . ' ' . $user['last_name']) ?>
        </h1>
        <p><?= escapeProfile($user['email']) ?></p>
        <span class="profile-role"><?= escapeProfile(roleLabel($user['role'])) ?></span>
    </div>
</section>

<?php if (!$isStaff): ?>
    <section class="profile-stats" aria-label="My report totals">
        <div class="profile-stat">
            <p>Total Reports</p>
            <strong><?= $stats['total'] ?></strong>
        </div>
        <div class="profile-stat">
            <p>Pending</p>
            <strong><?= $stats['pending'] ?></strong>
        </div>
        <div class="profile-stat">
            <p>In Progress</p>
            <strong><?= $stats['in_progress'] ?></strong>
        </div>
        <div class="profile-stat">
            <p>Resolved</p>
            <strong><?= $stats['resolved'] ?></strong>
        </div>
    </section>
<?php endif; ?>

<div class="profile-grid">

    <section class="profile-card">
        <h2>Personal Information</h2>

        <?php if ($profileError !== ''): ?>
            <p class="alert alert-error"><?= escapeProfile($profileError) ?></p>
        <?php endif; ?>

        <?php if ($profileSuccess !== ''): ?>
            <p class="alert alert-success"><?= escapeProfile($profileSuccess) ?></p>
        <?php endif; ?>

        <form method="POST" class="form-layout single-column">
            <input type="hidden" name="csrf_token"
                   value="<?= escapeProfile($_SESSION['csrf_token']) ?>">
            <input type="hidden" name="action" value="profile">

            <div class="form-field">
                <label for="first_name">First Name</label>
                <input type="text" id="first_name" name="first_name"
                       value="<?= escapeProfile($form['first_name']) ?>"
                       autocomplete="given-name" required>
            </div>

            <div class="form-field">
                <label for="last_name">Last Name</label>
                <input type="text" id="last_name" name="last_name"
                       value="<?= escapeProfile($form['last_name']) ?>"
                       autocomplete="family-name" required>
            </div>

            <div class="form-field">
                <label for="email">Email Address</label>
                <input type="email" id="email"
                       value="<?= escapeProfile($user['email']) ?>"
                       disabled>
                <small>Email cannot be changed.</small>
            </div>

            <div class="form-field">
                <label for="contact_no">Contact Number</label>
                <input type="tel" id="contact_no" name="contact_no"
                       value="<?= escapeProfile($form['contact_no']) ?>"
                       autocomplete="tel" required>
            </div>

            <div class="form-field">
                <label for="address">Address</label>
                <input type="text" id="address" name="address"
                       value="<?= escapeProfile($form['address']) ?>"
                       autocomplete="street-address" required>
            </div>

            <button type="submit" class="button button-primary button-full">
                Save Changes
            </button>
        </form>
    </section>

    <section class="profile-card">
        <h2>Change Password</h2>

        <?php if ($passwordError !== ''): ?>
            <p class="alert alert-error"><?= escapeProfile($passwordError) ?></p>
        <?php endif; ?>

        <?php if ($passwordSuccess !== ''): ?>
            <p class="alert alert-success"><?= escapeProfile($passwordSuccess) ?></p>
        <?php endif; ?>

        <form method="POST" class="form-layout single-column">
            <input type="hidden" name="csrf_token"
                   value="<?= escapeProfile($_SESSION['csrf_token']) ?>">
            <input type="hidden" name="action" value="password">

            <div class="form-field">
                <label for="current_password">Current Password</label>
                <input type="password" id="current_password"
                       name="current_password"
                       autocomplete="current-password" required>
            </div>

            <div class="form-field">
                <label for="new_password">New Password</label>
                <input type="password" id="new_password" name="new_password"
                       minlength="8" autocomplete="new-password" required>
                <small>At least 8 characters.</small>
            </div>

            <div class="form-field">
                <label for="confirm_new_password">Confirm New Password</label>
                <input type="password" id="confirm_new_password"
                       name="confirm_new_password"
                       minlength="8" autocomplete="new-password" required>
            </div>

            <button type="submit" class="button button-primary button-full">
                Update Password
            </button>
        </form>
    </section>

</div>

<?php require __DIR__ . '/includes/footer.php'; ?>
