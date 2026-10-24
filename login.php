<?php
session_start();

require_once __DIR__ . '/config/database.php';
require_once __DIR__ . '/includes/auth.php';

if (isset($_SESSION['user_id'])) {
    if (isStaffRole($_SESSION['role'] ?? '')) {
        header('Location: admin/dashboard.php');
    } else {
        header('Location: dashboard.php');
    }
    exit;
}

$error = '';
$email = '';
$registered = ($_GET['registered'] ?? '') === '1';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $email = trim($_POST['email'] ?? '');
    $password = $_POST['password'] ?? '';

    if (!filter_var($email, FILTER_VALIDATE_EMAIL) || $password === '') {
        $error = 'Please enter a valid email address and password.';
    } else {
        try {
            $stmt = $pdo->prepare(
                'SELECT user_id, first_name, last_name, email, role, password_hash
                 FROM users
                 WHERE email = ?
                 LIMIT 1'
            );

            $stmt->execute([$email]);
            $user = $stmt->fetch();

            if ($user && password_verify($password, $user['password_hash'])) {
                session_regenerate_id(true);

                $_SESSION['user_id'] = (int) $user['user_id'];
                $_SESSION['first_name'] = $user['first_name'];
                $_SESSION['last_name'] = $user['last_name'];
                $_SESSION['email'] = $user['email'];
                $_SESSION['role'] = $user['role'];

                if (isStaffRole($user['role'])) {
                    header('Location: admin/dashboard.php');
                } else {
                    header('Location: dashboard.php');
                }
                exit;
            }

            $error = 'Incorrect email or password.';

        } catch (PDOException $e) {
            error_log('Login database error: ' . $e->getMessage());
            $error = 'Something went wrong. Please try again later.';
        }
    }
}

$pageTitle = 'Log In';
$pageClass = 'center-page';
require __DIR__ . '/includes/header.php';
?>

<section class="minimal-auth">
    <p class="minimal-eyebrow">Barangay Pitipiwpiw</p>
    <h1>Welcome back</h1>
    <p class="minimal-sub">Please enter your details to sign in.</p>

    <?php if ($registered && !$error): ?>
        <p class="alert alert-success">
            Account created. You can now log in.
        </p>
    <?php endif; ?>

    <?php if ($error): ?>
        <p class="alert alert-error">
            <?= htmlspecialchars($error, ENT_QUOTES, 'UTF-8') ?>
        </p>
    <?php endif; ?>

    <form method="POST" class="form-layout single-column">
        <div class="form-field">
            <label for="email">Email Address</label>
            <input
                type="email"
                id="email"
                name="email"
                value="<?= htmlspecialchars($email, ENT_QUOTES, 'UTF-8') ?>"
                placeholder="name@example.com"
                autocomplete="email"
                required
            >
        </div>

        <div class="form-field">
            <label for="password">Password</label>
            <input
                type="password"
                id="password"
                name="password"
                autocomplete="current-password"
                required
            >
        </div>

        <button type="submit" class="button button-primary button-full">
            Sign in
        </button>
    </form>

    <p class="auth-switch">
        Don't have an account?
        <a href="register.php">Register here</a>
    </p>

    <p class="auth-switch auth-guest">
        or <a href="guest.php?guest=1">Continue as Guest</a>
    </p>
</section>

<?php require __DIR__ . '/includes/footer.php'; ?>
