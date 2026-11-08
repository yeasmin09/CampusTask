<?php
/**
 * CampusTask - User Logout
 * 
 * Course: CSE 2291 - Database Management System (DBMS)
 * Role: Authentication (Person B)
 */

$config_path = __DIR__ . '/config/database.php';
if (file_exists($config_path)) {
    require_once $config_path;
} else {
    require_once __DIR__ . '/config/database.example.php';
}

// Ensure session is active before clearing it
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// 1. Unset all session variables
$_SESSION = [];

// 2. Clear session cookie from the browser
if (ini_get("session.use_cookies")) {
    $params = session_get_cookie_params();
    setcookie(
        session_name(),
        '',
        time() - 42000,
        $params["path"],
        $params["domain"],
        $params["secure"],
        $params["httponly"]
    );
}

// 3. Destroy the session on server
session_destroy();

// 4. Start a fresh session to send a friendly flash message
session_start();
set_flash('info', 'You have been logged out successfully.');

// 5. Redirect back to login page
redirect('login.php');
