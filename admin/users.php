<?php
session_start();

require_once __DIR__ . '/../includes/auth.php';

// Super admin only.
if (!isset($_SESSION['user_id'])) {
    header('Location: ../login.php');
    exit;
}

if (!isSuperAdmin()) {
    header('Location: dashboard.php');
    exit;
}

require_once __DIR__ . '/../config/database.php';

function escapeUsers($value): string
{
    return htmlspecialchars((string) ($value ?? ''), ENT_QUOTES, 'UTF-8');
}

$allowedRoles = ['resident', 'staff', 'admin', 'super_admin'];
$currentUserId = (int) $_SESSION['user_id'];

$error = '';
$success = '';
$tempPassword = '';
$tempPasswordFor = '';

$newUser = [
    'first_name' => '',
    'last_name' => '',
    'email' => '',
    'contact_no' => '',
    'address' => '',
    'role' => 'staff',
];

// Easy-to-read temporary password (no look-alike characters).
function makeTemporaryPassword(int $length = 10): string
{
    $chars = 'ABCDEFGHJKLMNPQRSTUVWXYZabcdefghijkmnpqrstuvwxyz23456789';
    $password = '';

    for ($i = 0; $i < $length; $i++) {
        $password .= $chars[random_int(0, strlen($chars) - 1)];
    }

    return $password;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';

    try {
        if (!csrfIsValid($_POST['csrf_token'] ?? null)) {
            $error = 'Your session expired. Please try again.';

        } elseif ($action === 'change_role') {
            $targetId = (int) ($_POST['user_id'] ?? 0);
            $role = $_POST['role'] ?? '';

            if (!in_array($role, $allowedRoles, true)) {
                $error = 'Please choose a valid role.';
            } elseif ($targetId === $currentUserId) {
                $error = 'You cannot change your own role.';
            } else {
                $find = $pdo->prepare('SELECT role FROM users WHERE user_id = ? LIMIT 1');
                $find->execute([$targetId]);
                $previousRole = $find->fetchColumn();

                if ($previousRole === false) {
                    $error = 'Account not found.';
                } elseif ($previousRole === $role) {
                    $error = 'That account already has this role.';
                } else {
                    $pdo->beginTransaction();

                    $stmt = $pdo->prepare('UPDATE users SET role = ? WHERE user_id = ?');
                    $stmt->execute([$role, $targetId]);

                    // Keep a record in the role_history table.
                    $history = $pdo->prepare(
                        'INSERT INTO role_history (user_id, previous_role, new_role)
                         VALUES (?, ?, ?)'
                    );
                    $history->execute([$targetId, $previousRole, $role]);

                    $pdo->commit();
                    $success = 'The account role has been updated.';
                }
            }

        } elseif ($action === 'reset_password') {
            $targetId = (int) ($_POST['user_id'] ?? 0);

            $find = $pdo->prepare(
                'SELECT first_name, last_name, email FROM users WHERE user_id = ? LIMIT 1'
            );
            $find->execute([$targetId]);
            $target = $find->fetch();

            if (!$target) {
                $error = 'Account not found.';
            } else {
                $tempPassword = makeTemporaryPassword();
                $tempPasswordFor = $target['first_name'] . ' ' . $target['last_name']
                    . ' (' . $target['email'] . ')';

                $stmt = $pdo->prepare('UPDATE users SET password_hash = ? WHERE user_id = ?');
                $stmt->execute([
                    password_hash($tempPassword, PASSWORD_DEFAULT),
                    $targetId
                ]);
            }

        } elseif ($action === 'create') {
            foreach (array_keys($newUser) as $field) {
                $newUser[$field] = trim($_POST[$field] ?? '');
            }
            $password = $_POST['password'] ?? '';

            if (
                $newUser['first_name'] === '' || $newUser['last_name'] === '' ||
                $newUser['email'] === '' || $newUser['contact_no'] === '' ||
                $newUser['address'] === ''
            ) {
                $error = 'Please complete all fields.';
            } elseif (!filter_var($newUser['email'], FILTER_VALIDATE_EMAIL)) {
                $error = 'Please enter a valid email address.';
            } elseif (!in_array($newUser['role'], $allowedRoles, true)) {
                $error = 'Please choose a valid role.';
            } elseif (strlen($password) < 8) {
                $error = 'The password must contain at least 8 characters.';
            } else {
                $check = $pdo->prepare('SELECT user_id FROM users WHERE email = ? LIMIT 1');
                $check->execute([$newUser['email']]);

                if ($check->fetch()) {
                    $error = 'An account with this email already exists.';
                } else {
                    $stmt = $pdo->prepare(
                        'INSERT INTO users
                         (first_name, last_name, contact_no, email, address, role, password_hash)
                         VALUES (?, ?, ?, ?, ?, ?, ?)'
                    );
                    $stmt->execute([
                        $newUser['first_name'],
                        $newUser['last_name'],
                        $newUser['contact_no'],
                        $newUser['email'],
                        $newUser['address'],
                        $newUser['role'],
                        password_hash($password, PASSWORD_DEFAULT)
                    ]);

                    $success = 'The account has been created.';
                    $newUser = array_map(static fn() => '', $newUser);
                    $newUser['role'] = 'staff';
                }
            }
        }
    } catch (PDOException $e) {
        if ($pdo->inTransaction()) {
            $pdo->rollBack();
        }

        error_log('User management error: ' . $e->getMessage());
        $error = 'Something went wrong. Please check the database and try again.';
    }
}

// Search and role filter.
$search = trim((string) ($_GET['q'] ?? ''));
$roleFilter = (string) ($_GET['role'] ?? '');

if (!in_array($roleFilter, $allowedRoles, true)) {
    $roleFilter = '';
}

$users = [];

try {
    $where = [];
    $params = [];

    if ($search !== '') {
        $where[] = '(first_name LIKE ? OR last_name LIKE ? OR email LIKE ? OR contact_no LIKE ?)';
        $like = '%' . $search . '%';
        array_push($params, $like, $like, $like, $like);
    }

    if ($roleFilter !== '') {
        $where[] = 'role = ?';
        $params[] = $roleFilter;
    }

    $stmt = $pdo->prepare(
        'SELECT user_id, first_name, last_name, email, contact_no, address, role
         FROM users' .
        ($where ? ' WHERE ' . implode(' AND ', $where) : '') .
        ' ORDER BY user_id DESC'
    );
    $stmt->execute($params);
    $users = $stmt->fetchAll();
} catch (PDOException $e) {
    error_log('User list error: ' . $e->getMessage());
    $error = $error ?: 'Unable to load the user accounts.';
}

$basePath = '../';
$pageTitle = 'User Accounts';

require __DIR__ . '/../includes/header.php';
?>

<section class="page-heading">
    <h1>User Accounts</h1>
    <p>
        Create accounts, change roles, and reset passwords. Passwords are stored
        encrypted (hashed), so nobody can read them. If someone forgets their
        password, reset it and give them the temporary password.
    </p>
</section>

<?php if ($error !== ''): ?>
    <p class="alert alert-error" role="alert"><?= escapeUsers($error) ?></p>
<?php endif; ?>

<?php if ($success !== ''): ?>
    <p class="alert alert-success"><?= escapeUsers($success) ?></p>
<?php endif; ?>

<?php if ($tempPassword !== ''): ?>
    <div class="alert alert-success temp-password" role="status">
        <p>
            Temporary password for <strong><?= escapeUsers($tempPasswordFor) ?></strong>
            (shown only once):
        </p>
        <code><?= escapeUsers($tempPassword) ?></code>
        <p>Ask them to log in and change it on their Profile page.</p>
    </div>
<?php endif; ?>

<details class="profile-card create-user">
    <summary>Create a new account</summary>

    <form method="POST" class="form-layout register-grid">
        <input type="hidden" name="csrf_token" value="<?= escapeUsers(csrfToken()) ?>">
        <input type="hidden" name="action" value="create">

        <div class="form-field">
            <label for="first_name">First Name</label>
            <input type="text" id="first_name" name="first_name"
                   value="<?= escapeUsers($newUser['first_name']) ?>" required>
        </div>

        <div class="form-field">
            <label for="last_name">Last Name</label>
            <input type="text" id="last_name" name="last_name"
                   value="<?= escapeUsers($newUser['last_name']) ?>" required>
        </div>

        <div class="form-field">
            <label for="email">Email Address</label>
            <input type="email" id="email" name="email"
                   value="<?= escapeUsers($newUser['email']) ?>" required>
        </div>

        <div class="form-field">
            <label for="contact_no">Contact Number</label>
            <input type="tel" id="contact_no" name="contact_no"
                   value="<?= escapeUsers($newUser['contact_no']) ?>" required>
        </div>

        <div class="form-field field-full">
            <label for="address">Address</label>
            <input type="text" id="address" name="address"
                   value="<?= escapeUsers($newUser['address']) ?>" required>
        </div>

        <div class="form-field">
            <label for="role">Role</label>
            <select id="role" name="role" required>
                <?php foreach ($allowedRoles as $role): ?>
                    <option value="<?= escapeUsers($role) ?>"
                        <?= $newUser['role'] === $role ? 'selected' : '' ?>>
                        <?= escapeUsers(roleLabel($role)) ?>
                    </option>
                <?php endforeach; ?>
            </select>
        </div>

        <div class="form-field">
            <label for="password">Password</label>
            <input type="password" id="password" name="password"
                   minlength="8" autocomplete="new-password" required>
            <small>At least 8 characters.</small>
        </div>

        <div class="field-full">
            <button type="submit" class="button button-primary">Create Account</button>
        </div>
    </form>
</details>

<form method="GET" class="search-bar" role="search">
    <label class="sr-only" for="q">Search accounts</label>
    <input type="search" id="q" name="q" value="<?= escapeUsers($search) ?>"
           placeholder="Search name, email or contact number">

    <label class="sr-only" for="role_filter">Filter by role</label>
    <select id="role_filter" name="role">
        <option value="">All roles</option>
        <?php foreach ($allowedRoles as $role): ?>
            <option value="<?= escapeUsers($role) ?>"
                <?= $roleFilter === $role ? 'selected' : '' ?>>
                <?= escapeUsers(roleLabel($role)) ?>
            </option>
        <?php endforeach; ?>
    </select>

    <button type="submit" class="button button-primary">Search</button>
</form>

<div class="table-wrap">
    <table class="data-table">
        <thead>
            <tr>
                <th>ID</th>
                <th>Name</th>
                <th>Email</th>
                <th>Contact</th>
                <th>Address</th>
                <th>Role</th>
                <th>Actions</th>
            </tr>
        </thead>

        <tbody>
            <?php if ($users): ?>
                <?php foreach ($users as $user): ?>
                    <?php $isSelf = (int) $user['user_id'] === $currentUserId; ?>
                    <tr>
                        <td>#<?= (int) $user['user_id'] ?></td>
                        <td><?= escapeUsers($user['first_name'] . ' ' . $user['last_name']) ?></td>
                        <td><?= escapeUsers($user['email']) ?></td>
                        <td><?= escapeUsers($user['contact_no']) ?></td>
                        <td><?= escapeUsers($user['address']) ?></td>
                        <td>
                            <span class="profile-role"><?= escapeUsers(roleLabel($user['role'])) ?></span>
                        </td>
                        <td class="user-actions">
                            <?php if ($isSelf): ?>
                                <em>This is you</em>
                            <?php else: ?>
                                <form method="POST" class="inline-form">
                                    <input type="hidden" name="csrf_token" value="<?= escapeUsers(csrfToken()) ?>">
                                    <input type="hidden" name="action" value="change_role">
                                    <input type="hidden" name="user_id" value="<?= (int) $user['user_id'] ?>">

                                    <label class="sr-only" for="role-<?= (int) $user['user_id'] ?>">Role</label>
                                    <select name="role" id="role-<?= (int) $user['user_id'] ?>">
                                        <?php foreach ($allowedRoles as $role): ?>
                                            <option value="<?= escapeUsers($role) ?>"
                                                <?= strtolower($user['role']) === $role ? 'selected' : '' ?>>
                                                <?= escapeUsers(roleLabel($role)) ?>
                                            </option>
                                        <?php endforeach; ?>
                                    </select>
                                    <button type="submit" class="button button-secondary">Save Role</button>
                                </form>

                                <form method="POST" class="inline-form"
                                      onsubmit="return confirm('Reset this account\'s password? A new temporary password will be created.')">
                                    <input type="hidden" name="csrf_token" value="<?= escapeUsers(csrfToken()) ?>">
                                    <input type="hidden" name="action" value="reset_password">
                                    <input type="hidden" name="user_id" value="<?= (int) $user['user_id'] ?>">
                                    <button type="submit" class="button button-outline">Reset Password</button>
                                </form>
                            <?php endif; ?>
                        </td>
                    </tr>
                <?php endforeach; ?>
            <?php else: ?>
                <tr><td colspan="7">No accounts found.</td></tr>
            <?php endif; ?>
        </tbody>
    </table>
</div>

<?php require __DIR__ . '/../includes/footer.php'; ?>
