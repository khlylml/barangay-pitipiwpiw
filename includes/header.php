<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

require_once __DIR__ . '/auth.php';

$basePath = $basePath ?? '';
$pageTitle = $pageTitle ?? 'Barangay Incident Reporting and Management System';
$extraHead = $extraHead ?? '';
$pageClass = $pageClass ?? '';
$isGuest = !empty($_GET['guest']) && !isset($_SESSION['user_id']);
$userRole = strtolower($_SESSION['role'] ?? 'guest');

// Staff, admins and the super admin get the sidebar layout.
$useAdminLayout = isset($_SESSION['user_id']) && isStaffRole($userRole);

// Work out which page is open so its menu link can be highlighted.
$scriptPath = str_replace('\\', '/', $_SERVER['SCRIPT_NAME'] ?? '');
$currentPage = (strpos($scriptPath, '/admin/') !== false ? 'admin/' : '')
    . basename($scriptPath);

// Small line icons for the sidebar (decorative).
$iconPaths = [
    'home' => '<path d="M3 11l9-8 9 8"/><path d="M5 10v10h14V10"/>',
    'plus' => '<circle cx="12" cy="12" r="9"/><path d="M12 8v8M8 12h8"/>',
    'list' => '<path d="M8 6h13M8 12h13M8 18h13"/><path d="M3 6h.01M3 12h.01M3 18h.01"/>',
    'user' => '<circle cx="12" cy="8" r="4"/><path d="M4 21c0-4 4-6 8-6s8 2 8 6"/>',
    'login' => '<path d="M15 3h4a2 2 0 012 2v14a2 2 0 01-2 2h-4"/><path d="M10 17l5-5-5-5M15 12H3"/>',
    'userplus' => '<circle cx="9" cy="8" r="4"/><path d="M2 21c0-4 3-6 7-6s7 2 7 6"/><path d="M19 8v6M16 11h6"/>',
    'dash' => '<rect x="3" y="3" width="7" height="9"/><rect x="14" y="3" width="7" height="5"/><rect x="14" y="12" width="7" height="9"/><rect x="3" y="16" width="7" height="5"/>',
    'file' => '<path d="M14 3H6a2 2 0 00-2 2v14a2 2 0 002 2h12a2 2 0 002-2V9z"/><path d="M14 3v6h6"/>',
    'clock' => '<circle cx="12" cy="12" r="9"/><path d="M12 7v5l3 2"/>',
    'check' => '<circle cx="12" cy="12" r="9"/><path d="M8 12l3 3 5-6"/>',
    'tool' => '<path d="M14.7 6.3a4 4 0 005 5L21 17l-4 4-5.7-1.3a4 4 0 00-5-5L3 7l4-4z"/>',
    'archive' => '<rect x="3" y="4" width="18" height="4"/><path d="M5 8v12h14V8M10 12h4"/>',
    'users' => '<circle cx="9" cy="8" r="3.5"/><path d="M2 21c0-4 3-6 7-6s7 2 7 6"/><path d="M17 4.5a3.5 3.5 0 010 7M22 21c0-3-2-5-5-5.7"/>',
    'logout' => '<path d="M9 3H5a2 2 0 00-2 2v14a2 2 0 002 2h4"/><path d="M16 17l5-5-5-5M21 12H9"/>',
    'eye' => '<path d="M2 12s4-7 10-7 10 7 10 7-4 7-10 7S2 12 2 12z"/><circle cx="12" cy="12" r="3"/>',
];

$icon = function (string $name) use ($iconPaths): string {
    return '<svg class="nav-icon" viewBox="0 0 24 24" width="22" height="22" fill="none" '
        . 'stroke="currentColor" stroke-width="1.8" stroke-linecap="round" '
        . 'stroke-linejoin="round" aria-hidden="true">' . ($iconPaths[$name] ?? '') . '</svg>';
};

// Clicking the system name goes to the dashboard when logged in.
$homeHref = $basePath . 'index.php';

if (isset($_SESSION['user_id'])) {
    $homeHref = $basePath . ($useAdminLayout ? 'admin/dashboard.php' : 'dashboard.php');
}

$navLink = function (
    string $file,
    string $label,
    string $query = '',
    string $class = '',
    string $iconName = ''
) use ($basePath, $currentPage, $icon): void {
    $params = [];
    parse_str(ltrim($query, '?'), $params);

    $isActive = $currentPage === $file
        && (string) ($params['status'] ?? '') === (string) ($_GET['status'] ?? '');

    $classes = trim($class . ($isActive ? ' active' : ''));

    echo '<a href="' . htmlspecialchars($basePath . $file . $query) . '"'
        . ($classes !== '' ? ' class="' . $classes . '"' : '')
        . ($isActive ? ' aria-current="page"' : '')
        . '>' . ($iconName !== '' ? $icon($iconName) : '')
        . '<span>' . htmlspecialchars($label) . '</span></a>' . "\n";
};
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= htmlspecialchars($pageTitle) ?></title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link rel="stylesheet" href="https://fonts.googleapis.com/css2?family=Questrial&display=swap">
    <script>try{var t=localStorage.getItem('theme');if(!t&&window.matchMedia&&matchMedia('(prefers-color-scheme: dark)').matches)t='dark';if(t==='dark')document.documentElement.setAttribute('data-theme','dark');}catch(e){}</script>
    <link rel="stylesheet" href="<?= $basePath ?>css/style.css?v=<?= @filemtime(__DIR__ . '/../css/style.css') ?: 1 ?>">
    <meta name="csrf-token" content="<?= htmlspecialchars(csrfToken(), ENT_QUOTES, 'UTF-8') ?>">
    <meta name="api-base" content="<?= htmlspecialchars($basePath, ENT_QUOTES, 'UTF-8') ?>api">
    <?= $extraHead ?>
    <script src="<?= $basePath ?>js/app.js?v=<?= @filemtime(__DIR__ . '/../js/app.js') ?: 1 ?>" defer></script>
    <script src="<?= $basePath ?>js/api.js?v=<?= @filemtime(__DIR__ . '/../js/api.js') ?: 1 ?>" defer></script>
</head>
<body class="<?= trim(($useAdminLayout ? 'admin-body ' : '') . $pageClass) ?>">
<?php $isPublic = !isset($_SESSION['user_id']); ?>
<div class="app-shell<?= $isPublic ? ' is-public' : '' ?>">

<?php if (!$isPublic): ?>
    <nav class="app-sidebar" id="site-navigation" aria-label="Main menu">
        <a class="sidebar-brand" href="<?= htmlspecialchars($homeHref) ?>">
            <img src="<?= $basePath ?>images/logo.png"
                 alt="Barangay logo" width="48" height="48"
                 onerror="this.style.display='none'">
            <span>Barangay Incident Reporting and Management System</span>
        </a>

        <?php if (isset($_SESSION['user_id'])): ?>
            <p class="sidebar-user">
                <strong>
                    <?= htmlspecialchars(
                        trim(($_SESSION['first_name'] ?? '') . ' ' . ($_SESSION['last_name'] ?? ''))
                    ) ?>
                </strong>
                <span class="profile-role"><?= htmlspecialchars(roleLabel($userRole)) ?></span>
            </p>
        <?php endif; ?>

        <?php if ($useAdminLayout): ?>

            <?php $navLink('admin/dashboard.php', 'Dashboard', '', '', 'dash'); ?>

            <p class="sidebar-label">Incident Reports</p>
            <?php $navLink('admin/reports.php', 'All Reports', '', '', 'file'); ?>
            <?php $navLink('admin/reports.php', 'Pending', '?status=Pending', '', 'clock'); ?>
            <?php $navLink('admin/reports.php', 'Verified', '?status=Verified', '', 'check'); ?>
            <?php $navLink('admin/reports.php', 'In Progress', '?status=In Progress', '', 'tool'); ?>
            <?php $navLink('admin/reports.php', 'Resolved', '?status=Resolved', '', 'check'); ?>
            <?php $navLink('admin/reports.php', 'Archived', '?status=Archived', '', 'archive'); ?>

            <?php if (isSuperAdmin($userRole)): ?>
                <p class="sidebar-label">Super Admin</p>
                <?php $navLink('admin/users.php', 'User Accounts', '', '', 'users'); ?>
            <?php endif; ?>

            <p class="sidebar-label">Account</p>
            <?php $navLink('profile.php', 'My Profile', '', '', 'user'); ?>

        <?php elseif (isset($_SESSION['user_id'])): ?>

            <?php $navLink('dashboard.php', 'Dashboard', '', '', 'dash'); ?>
            <?php $navLink('report-incident.php', 'Report an Incident', '', '', 'plus'); ?>
            <?php $navLink('my-reports.php', 'My Reports', '', '', 'list'); ?>
            <?php $navLink('profile.php', 'Profile', '', '', 'user'); ?>

        <?php endif; ?>

        <?php if (isset($_SESSION['user_id'])): ?>
            <a href="<?= $basePath ?>logout.php" class="nav-logout"
               onclick="return confirm('Are you sure you want to log out?')">
                <?= $icon('logout') ?><span>Log Out</span>
            </a>
        <?php endif; ?>
    </nav>
<?php endif; ?>

    <div class="app-content">
        <header class="app-topbar">
            <?php if (!$isPublic): ?>
            <button class="menu-toggle"
                    type="button"
                    aria-label="Open navigation menu"
                    aria-expanded="false"
                    aria-controls="site-navigation">
                <span></span><span></span><span></span>
            </button>
            <?php endif; ?>

            <a class="topbar-brand" href="<?= htmlspecialchars($homeHref) ?>">
                <img src="<?= $basePath ?>images/logo.png"
                     alt="" width="34" height="34"
                     onerror="this.style.display='none'">
                <span>Barangay Incident Reporting and Management System</span>
            </a>

            <?php if ($isPublic): ?>
                <span class="topbar-links">
                    <a href="<?= $basePath ?>login.php">Log In</a>
                    <a href="<?= $basePath ?>register.php" class="topbar-cta">Register</a>
                    <?php if ($isGuest): ?>
                        <a href="<?= $basePath ?>index.php">Exit Guest Mode</a>
                    <?php endif; ?>
                </span>
            <?php endif; ?>

            <?php if (isset($_SESSION['user_id'])): ?>
                <span class="topbar-user">
                    <?= htmlspecialchars(trim($_SESSION['first_name'] ?? '')) ?>
                </span>
            <?php endif; ?>
            <button class="theme-toggle" type="button" aria-label="Switch light/dark mode" title="Light / Dark mode">
                <svg class="ico-moon" viewBox="0 0 24 24" width="20" height="20" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M21 12.8A9 9 0 1111.2 3a7 7 0 009.8 9.8z"/></svg>
                <svg class="ico-sun" viewBox="0 0 24 24" width="20" height="20" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><circle cx="12" cy="12" r="4"/><path d="M12 2v2M12 20v2M4.9 4.9l1.4 1.4M17.7 17.7l1.4 1.4M2 12h2M20 12h2M4.9 19.1l1.4-1.4M17.7 6.3l1.4-1.4"/></svg>
            </button>
        </header>

<?php if ($useAdminLayout): ?>
        <main class="admin-main">
<?php else: ?>
        <main class="page-container">
<?php endif; ?>
