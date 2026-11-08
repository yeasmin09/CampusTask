<?php
/**
 * CampusTask - Role-Based Access Control (RBAC) & Authentication Helpers
 * 
 * Course: CSE 2291 - Database Management System (DBMS)
 * Role: Access Control (Person B)
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

/**
 * Check if the user is currently authenticated.
 *
 * @return bool
 */
function is_logged_in(): bool {
    return isset($_SESSION['user_id']) && !empty($_SESSION['user_id']);
}

/**
 * Get the current logged-in user's ID.
 *
 * @return int|null
 */
function current_user_id(): ?int {
    return isset($_SESSION['user_id']) ? (int)$_SESSION['user_id'] : null;
}

/**
 * Get the current logged-in user's role ('STUDENT' or 'ADMIN').
 *
 * @return string|null
 */
function current_user_role(): ?string {
    return $_SESSION['role'] ?? null;
}

/**
 * Restrict access to authenticated users only.
 * If not authenticated, redirects to login page with an error flash message.
 *
 * @return void
 */
function require_login(): void {
    if (!is_logged_in()) {
        set_flash('error', 'Please log in to access this page.');
        redirect('login.php');
    }

    // Optional real-time status check against database if $pdo is available
    global $pdo;
    if (isset($pdo) && $pdo !== null) {
        try {
            $stmt = $pdo->prepare('SELECT account_status FROM users WHERE user_id = :id LIMIT 1');
            $stmt->execute([':id' => current_user_id()]);
            $status = $stmt->fetchColumn();

            if ($status !== 'ACTIVE') {
                // If account was deactivated by admin while logged in, terminate session immediately
                $_SESSION = [];
                session_destroy();
                session_start();
                set_flash('error', 'Your account has been deactivated. Please contact an administrator.');
                redirect('login.php');
            }
        } catch (PDOException $e) {
            // Silently continue if database is undergoing migration/testing
        }
    }
}

/**
 * Restrict access to STUDENT users only.
 * Prevents Admins or guests from executing student-only actions.
 *
 * @return void
 */
function require_student(): void {
    require_login();

    if (current_user_role() !== 'STUDENT') {
        set_flash('error', 'Access denied. Student account required.');
        if (current_user_role() === 'ADMIN') {
            redirect('admin/dashboard.php');
        } else {
            redirect('login.php');
        }
    }
}

/**
 * Restrict access to ADMIN users only.
 * Prevents Students or guests from accessing administrative moderation pages.
 *
 * @return void
 */
function require_admin(): void {
    require_login();

    if (current_user_role() !== 'ADMIN') {
        set_flash('error', 'Access denied. Administrator privileges required.');
        if (current_user_role() === 'STUDENT') {
            redirect('student/dashboard.php');
        } else {
            redirect('login.php');
        }
    }
}
