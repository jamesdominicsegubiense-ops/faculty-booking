<?php

declare(strict_types=1);

/* ------------------------------------------------------------------
 |  SESSION (hardened)
 * ------------------------------------------------------------------ */
if (session_status() === PHP_SESSION_NONE) {
    session_set_cookie_params([
        'lifetime' => 0,
        'path'     => '/',
        'httponly' => true,
        'samesite' => 'Lax',
    ]);
    session_start();
}

/* ------------------------------------------------------------------
 |  DATABASE CREDENTIALS
 |  → CHANGE THESE when deploying to a different environment.
 * ------------------------------------------------------------------ */
const DB_HOST    = 'localhost';          
const DB_NAME    = 'faculty_booking_db'; 
const DB_USER    = 'root';              
const DB_PASS    = '';                   
const DB_CHARSET = 'utf8mb4';

/* ------------------------------------------------------------------
 |  PDO CONNECTION
 * ------------------------------------------------------------------ */
$dsn = 'mysql:host=' . DB_HOST . ';dbname=' . DB_NAME . ';charset=' . DB_CHARSET;

$options = [
    PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
    PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
    PDO::ATTR_EMULATE_PREPARES   => false,
];

try {
    $pdo = new PDO($dsn, DB_USER, DB_PASS, $options);
} catch (PDOException $e) {
    error_log('DB connection failed: ' . $e->getMessage());
    http_response_code(500);
    exit('A database error occurred. Please try again later.');
}

/* ------------------------------------------------------------------
 |  DEPARTMENTS offered by the university
 * ------------------------------------------------------------------ */
const DEPARTMENTS = [
    'Computer Science',
    'Information Technology',
    'Information System',
    'Engineering',
    'Business Administration',
];

/* ------------------------------------------------------------------
 |  HELPERS
 * ------------------------------------------------------------------ */

/** Escape output for HTML. */
function e(?string $v): string
{
    return htmlspecialchars((string)$v, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
}

/** Get (or lazily create) the CSRF token for this session. */
function csrf_token(): string
{
    if (empty($_SESSION['csrf_token'])) {
        $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
    }
    return $_SESSION['csrf_token'];
}

/** Constant-time CSRF check. */
function csrf_verify(?string $token): bool
{
    return is_string($token)
        && !empty($_SESSION['csrf_token'])
        && hash_equals($_SESSION['csrf_token'], $token);
}

/** Currently logged-in user array, or null. */
function current_user(): ?array
{
    return $_SESSION['user'] ?? null;
}

/** Redirect to login if not authenticated. */
function require_login(): void
{
    if (!current_user()) {
        header('Location: login.php');
        exit;
    }
}

/** Require a specific role. */
function require_role(string $role): void
{
    require_login();
    if ((current_user()['role'] ?? '') !== $role) {
        http_response_code(403);
        exit('403 — Forbidden');
    }
}

/** Two-letter initials for avatar bubbles, skipping titles. */
function initials(string $name): string
{
    $out = '';
    foreach (preg_split('/\s+/', trim($name)) as $part) {
        if ($part === '' || preg_match('/^(dr|prof|mr|ms|mrs|engr)\.?$/i', $part)) {
            continue;
        }
        $out .= mb_strtoupper(mb_substr($part, 0, 1));
        if (mb_strlen($out) >= 2) break;
    }
    return $out !== '' ? $out : '?';
}

/** Map a booking status to a CSS badge class. */
function status_class(string $status): string
{
    return match ($status) {
        'Approved'  => 'status-approved',
        'Declined'  => 'status-declined',
        'Completed' => 'status-completed',
        default     => 'status-pending',
    };
}