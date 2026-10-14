<?php
require_once __DIR__ . '/../config/db.php';
$__user = current_user();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title><?= e($pageTitle ?? 'Faculty Consultation Booking') ?></title>
    <link rel="stylesheet" href="assets/css/styles.css">
</head>
<body>

<header class="site-header">
    <div class="container header-inner">
        <a class="brand" href="index.php">
            <span class="brand-mark">FC</span>
            <span class="brand-text">Faculink</span>
        </a>

        <nav class="main-nav">
            <a href="index.php">Home</a>

            <?php if ($__user): ?>
                <?php if ($__user['role'] === 'faculty'): ?>
                    <a href="faculty_dashboard.php">Dashboard</a>
                    <a href="faculty_schedules.php">Schedule</a>
                    <a href="faculty_profile.php">Profile</a>
                <?php elseif ($__user['role'] === 'student'): ?>
                    <a href="my_bookings.php">My Bookings</a>
                <?php elseif ($__user['role'] === 'admin'): ?>
                    <a href="admin_dashboard.php">Admin Panel</a>
                <?php endif; ?>

                <span class="user-chip">
                    <?= e($__user['full_name']) ?>
                    <em><?= e(ucfirst($__user['role'])) ?></em>
                </span>
                <a class="btn btn-ghost btn-sm" href="logout.php">Logout</a>
            <?php else: ?>
                <a href="login.php">Login</a>
                <a href="register.php">Register</a>
            <?php endif; ?>
        </nav>
    </div>
</header>

<main class="container page">