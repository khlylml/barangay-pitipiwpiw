<?php
session_start();
require_once __DIR__ . '/includes/auth.php';

// Logged-in users go straight to their dashboard.
if (isset($_SESSION['user_id'])) {
    header('Location: ' . (isStaffRole($_SESSION['role'] ?? '') ? 'admin/dashboard.php' : 'dashboard.php'));
    exit;
}

$pageTitle = 'Barangay Pitipiwpiw Incident Reporting and Management System';
$pageClass = 'home-page';
require __DIR__ . '/includes/header.php';
?>

<section class="home-hero">
    <img class="home-logo" src="images/logo.png" alt="Barangay Pitipiwpiw logo"
         width="132" height="137" onerror="this.style.display='none'">
    <span class="home-badge">Barangay Pitipiwpiw</span>
    <h1>Incident Reporting and<br>Management System</h1>
    <p class="home-text">
        Report incidents in our community quickly and easily, and track the
        status of your reports until they are resolved.
    </p>

    <div class="home-actions">
        <a class="button button-primary" href="login.php">Log In to My Account</a>
        <a class="button button-outline" href="register.php">Register New Account</a>
        <a class="home-guest" href="guest.php?guest=1">Continue as Guest &rarr;</a>
    </div>
</section>

<section class="feature-row" aria-label="What you can do">
    <div>
        <h3><span class="feature-dot fd-blue"></span>Report Incidents</h3>
        <p>Submit a report with a description, photo and the exact location on the map.</p>
    </div>
    <div>
        <h3><span class="feature-dot fd-green"></span>Track Status</h3>
        <p>Follow your report from Pending to Resolved as officials act on it.</p>
    </div>
    <div>
        <h3><span class="feature-dot fd-gold"></span>Community Safety</h3>
        <p>Help keep Barangay Pitipiwpiw safe and orderly for everyone.</p>
    </div>
</section>

<?php require __DIR__ . '/includes/footer.php'; ?>
