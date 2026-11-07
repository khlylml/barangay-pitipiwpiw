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

<section class="how" aria-label="How to report an incident">
    <h2>How to report an incident</h2>
    <ol class="how-steps">
        <li>
            <span class="how-num">1</span>
            <div class="how-pic"><svg viewBox="0 0 24 24" width="38" height="38" fill="none" stroke="currentColor" stroke-width="1.6" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><circle cx="12" cy="8" r="4"/><path d="M4 21c0-4 4-6 8-6s8 2 8 6"/></svg></div>
            <h3>Log in or register</h3>
            <p>Create a free resident account, or log in.</p>
        </li>
        <li>
            <span class="how-num">2</span>
            <div class="how-pic"><svg viewBox="0 0 24 24" width="38" height="38" fill="none" stroke="currentColor" stroke-width="1.6" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M14 3H6a2 2 0 00-2 2v14a2 2 0 002 2h12a2 2 0 002-2V9z"/><path d="M14 3v6h6M8 13h8M8 17h5"/></svg></div>
            <h3>Fill in the report</h3>
            <p>Choose the type, describe what happened and add a photo.</p>
        </li>
        <li>
            <span class="how-num">3</span>
            <div class="how-pic"><svg viewBox="0 0 24 24" width="38" height="38" fill="none" stroke="currentColor" stroke-width="1.6" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M12 21s7-6.2 7-11a7 7 0 10-14 0c0 4.8 7 11 7 11z"/><circle cx="12" cy="10" r="2.5"/></svg></div>
            <h3>Pin the location</h3>
            <p>Tap the map or use your current location.</p>
        </li>
        <li>
            <span class="how-num">4</span>
            <div class="how-pic"><svg viewBox="0 0 24 24" width="38" height="38" fill="none" stroke="currentColor" stroke-width="1.6" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><circle cx="12" cy="12" r="9"/><path d="M8 12l3 3 5-6"/></svg></div>
            <h3>Review and track</h3>
            <p>Check your details, submit, then follow the status in My Reports.</p>
        </li>
    </ol>
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
