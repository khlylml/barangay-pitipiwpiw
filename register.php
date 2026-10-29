<?php
session_start();
require 'config/database.php';

if (isset($_SESSION['user_id'])) {
    header('Location: dashboard.php');
    exit;
}

$error = '';
$firstName = '';
$lastName = '';
$contactNo = '';
$email = '';
$address = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $firstName = trim($_POST['first_name'] ?? '');
    $lastName = trim($_POST['last_name'] ?? '');
    $contactNo = trim($_POST['contact_no'] ?? '');
    $email = trim($_POST['email'] ?? '');
    $address = trim($_POST['address'] ?? '');
    $password = $_POST['password'] ?? '';
    $confirmPassword = $_POST['confirm_password'] ?? '';

    if (
        $firstName === '' || $lastName === '' || $contactNo === '' ||
        $email === '' || $address === '' || $password === ''
    ) {
        $error = 'Please complete all fields.';
    } elseif (mb_strlen($firstName) < 2 || mb_strlen($lastName) < 2) {
        $error = 'Please enter your first and last name.';
    } elseif (!preg_match('/^(09\d{9}|\+639\d{9})$/', $contactNo)) {
        $error = 'Mobile number must be 09 followed by 9 digits, or +639 followed by 9 digits.';
    } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $error = 'Please enter a valid email address.';
    } elseif (!preg_match('/^(?=.*[A-Z])(?=.*\d)(?=.*[@$!])\S{8,}$/', $password)) {
        $error = 'Password needs at least 8 characters, 1 capital letter, 1 number, and one of @ $ !, with no spaces.';
    } elseif ($password !== $confirmPassword) {
        $error = 'The passwords do not match.';
    } else {
        try {
            $check = $pdo->prepare(
                'SELECT user_id FROM users WHERE email = ? LIMIT 1'
            );
            $check->execute([$email]);

            if ($check->fetch()) {
                $error = 'An account with this email already exists.';
            } else {
                $passwordHash = password_hash($password, PASSWORD_DEFAULT);

                $stmt = $pdo->prepare(
                    'INSERT INTO users
                     (first_name, last_name, contact_no, email, address, role, password_hash)
                     VALUES (?, ?, ?, ?, ?, ?, ?)'
                );
                $stmt->execute([
                    $firstName,
                    $lastName,
                    $contactNo,
                    $email,
                    $address,
                    'resident',
                    $passwordHash
                ]);

                header('Location: login.php?registered=1');
                exit;
            }
        } catch (PDOException $e) {
            error_log('Register database error: ' . $e->getMessage());
            $error = 'Something went wrong. Please try again later.';
        }
    }
}

$pageTitle = 'Register';
$pageClass = 'fit-screen center-page';
$extraHead = '<script src="js/register-validation.js" defer></script>';
require 'includes/header.php';
?>

<section class="minimal-register">
    <div class="minimal-head">
        <p class="minimal-eyebrow">Barangay Pitipiwpiw</p>
        <h1>Create your account</h1>
        <p class="minimal-sub">Register to submit and track incident reports.</p>
    </div>

    <?php if ($error): ?>
        <p class="alert alert-error"><?= htmlspecialchars($error) ?></p>
    <?php endif; ?>

    <form method="POST" id="registrationForm" class="form-layout register-grid" novalidate>
        <div class="form-field">
            <label for="first_name">First Name</label>
            <input type="text" id="first_name" name="first_name"
                   value="<?= htmlspecialchars($firstName) ?>"
                   autocomplete="given-name" required>
        </div>

        <div class="form-field">
            <label for="last_name">Last Name</label>
            <input type="text" id="last_name" name="last_name"
                   value="<?= htmlspecialchars($lastName) ?>"
                   autocomplete="family-name" required>
        </div>

        <div class="form-field">
            <label for="contact_no">Contact Number</label>
            <input type="tel" id="contact_no" name="contact_no"
                   value="<?= htmlspecialchars($contactNo) ?>"
                   autocomplete="tel" required>
        </div>

        <div class="form-field">
            <label for="email">Email Address</label>
            <input type="email" id="email" name="email"
                   value="<?= htmlspecialchars($email) ?>"
                   autocomplete="email" required>
        </div>

        <div class="form-field field-span-2">
            <label for="address">Address</label>
            <input type="text" id="address" name="address"
                   value="<?= htmlspecialchars($address) ?>"
                   autocomplete="street-address" required>
        </div>

        <div class="form-field">
            <label for="password">Password</label>
            <input type="password" id="password" name="password"
                   minlength="8" autocomplete="new-password" required>
            <small class="hint">8+ characters, 1 capital, 1 number, and @ $ or !</small>
        </div>

        <div class="form-field">
            <label for="confirm_password">Confirm Password</label>
            <input type="password" id="confirm_password" name="confirm_password"
                   minlength="8" autocomplete="new-password" required>
        </div>

        <div class="register-submit">
            <button type="submit" class="button button-primary button-full">
                Create Account
            </button>
        </div>
    </form>

    <p class="auth-switch">
        Already have an account?
        <a href="login.php">Log in</a>
    </p>
</section>

<?php require 'includes/footer.php'; ?>