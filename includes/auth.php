<?php
// Shared role and security helpers.

const STAFF_ROLES = ['super_admin', 'admin', 'staff'];

function currentRole(): string
{
    return strtolower((string) ($_SESSION['role'] ?? ''));
}

// super_admin, admin and staff can manage incident reports.
function isStaffRole(?string $role): bool
{
    return in_array(strtolower((string) $role), STAFF_ROLES, true);
}

function isSuperAdmin(?string $role = null): bool
{
    return strtolower((string) ($role ?? currentRole())) === 'super_admin';
}

function roleLabel(?string $role): string
{
    $labels = [
        'super_admin' => 'Super Admin',
        'admin' => 'Admin',
        'staff' => 'Staff',
        'resident' => 'Resident',
    ];

    return $labels[strtolower((string) $role)] ?? ucfirst((string) $role);
}

function csrfToken(): string
{
    if (empty($_SESSION['csrf_token'])) {
        $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
    }

    return $_SESSION['csrf_token'];
}

function csrfIsValid(?string $token): bool
{
    return is_string($token)
        && !empty($_SESSION['csrf_token'])
        && hash_equals($_SESSION['csrf_token'], $token);
}

// CSS class for a status badge (Pending, Verified, In Progress, Resolved).
function statusBadgeClass(?string $status): string
{
    switch ($status) {
        case 'Resolved':
        case 'Completed':
            return 'status-resolved';
        case 'Archived':
            return 'status-archived';
        case 'Verified':
            return 'status-verified';
        case 'In Progress':
            return 'status-progress';
        default:
            return 'status-pending';
    }
}

// Position of a status in the 4-step progress bar (0 to 3).
function statusStepIndex(?string $status): int
{
    switch ($status) {
        case 'Verified':
            return 1;
        case 'In Progress':
            return 2;
        case 'Resolved':
        case 'Completed':
        case 'Archived':
            return 3;
        default:
            return 0;
    }
}
