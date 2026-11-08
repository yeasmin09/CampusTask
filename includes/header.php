<?php
/**
 * CampusTask - Shared Header Layout
 * 
 * Course: CSE 2291 - Database Management System (DBMS)
 * Role: Shared Layout (Person B)
 */

// Ensure configuration and database connection are loaded
if (!defined('BASE_URL')) {
    $local_config = __DIR__ . '/../config/database.php';
    if (file_exists($local_config)) {
        require_once $local_config;
    } else {
        require_once __DIR__ . '/../config/database.example.php';
    }
}

// Check logged in user status and role
$is_logged_in = isset($_SESSION['user_id']);
$user_role = $_SESSION['role'] ?? null;
$user_name = $_SESSION['full_name'] ?? 'User';

// Retrieve any pending flash notification
$flash = get_flash();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= isset($page_title) ? e($page_title) . ' | CampusTask' : 'CampusTask - Student Freelance Marketplace' ?></title>
    <link rel="stylesheet" href="<?= BASE_URL ?>/assets/css/style.css">
</head>
<body>

<!-- Main Navigation Bar -->
<header class="navbar">
    <div class="nav-container">
        <!-- Site Logo -->
        <a href="<?= BASE_URL ?>/index.php" class="nav-brand">
            <span class="brand-accent">Campus</span>Task
        </a>

        <!-- Navigation Links Based on User Role -->
        <nav class="nav-menu">
            <?php if (!$is_logged_in): ?>
                <!-- Guest Navigation -->
                <a href="<?= BASE_URL ?>/student/tasks.php" class="nav-link">Browse Tasks</a>
                <a href="<?= BASE_URL ?>/login.php" class="nav-link">Login</a>
                <a href="<?= BASE_URL ?>/register.php" class="nav-btn btn-primary-sm">Register</a>

            <?php elseif ($user_role === 'ADMIN'): ?>
                <!-- Admin Navigation -->
                <a href="<?= BASE_URL ?>/admin/dashboard.php" class="nav-link">Dashboard</a>
                <a href="<?= BASE_URL ?>/admin/users.php" class="nav-link">Users</a>
                <a href="<?= BASE_URL ?>/admin/tasks.php" class="nav-link">Tasks</a>
                <a href="<?= BASE_URL ?>/admin/categories.php" class="nav-link">Categories</a>
                <a href="<?= BASE_URL ?>/admin/skills.php" class="nav-link">Skills</a>
                <a href="<?= BASE_URL ?>/admin/reviews.php" class="nav-link">Reviews</a>
                
                <div class="nav-user-info">
                    <span class="user-badge admin-badge">Admin</span>
                    <span class="user-greeting"><?= e($user_name) ?></span>
                    <a href="<?= BASE_URL ?>/logout.php" class="nav-link-logout">Logout</a>
                </div>

            <?php elseif ($user_role === 'STUDENT'): ?>
                <!-- Student Navigation -->
                <a href="<?= BASE_URL ?>/student/dashboard.php" class="nav-link">Dashboard</a>
                <a href="<?= BASE_URL ?>/student/tasks.php" class="nav-link">Browse Tasks</a>
                <a href="<?= BASE_URL ?>/student/post-task.php" class="nav-link">Post Task</a>
                <a href="<?= BASE_URL ?>/student/my-applications.php" class="nav-link">My Applications</a>
                <a href="<?= BASE_URL ?>/student/profile.php" class="nav-link">My Profile</a>
                
                <div class="nav-user-info">
                    <span class="user-badge student-badge">Student</span>
                    <span class="user-greeting"><?= e($user_name) ?></span>
                    <a href="<?= BASE_URL ?>/logout.php" class="nav-link-logout">Logout</a>
                </div>
            <?php endif; ?>
        </nav>
    </div>
</header>

<!-- Main Page Container -->
<div class="container main-wrapper">
    <!-- Flash Messages (Success / Error / Info) -->
    <?php if ($flash): ?>
        <div class="flash-alert flash-<?= e($flash['type']) ?>" role="alert">
            <div class="flash-content">
                <span><?= e($flash['message']) ?></span>
            </div>
        </div>
    <?php endif; ?>
