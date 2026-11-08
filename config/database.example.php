<?php
/**
 * CampusTask - Database Configuration & Global Helper Functions
 * 
 * Course: CSE 2291 - Database Management System (DBMS)
 * Role: Shared Foundation (Person B)
 * 
 * Copy this file to `config/database.php` on your local machine
 * and update the credentials to match your local XAMPP setup.
 */

// 1. Start PHP Session if not already started
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// 2. Define Base URL for links and assets
if (!defined('BASE_URL')) {
    define('BASE_URL', '/CampusTask');
}

// 3. Database Credentials (Default XAMPP settings)
define('DB_HOST', 'localhost');
define('DB_NAME', 'campustask');
define('DB_USER', 'root');
define('DB_PASS', '');
define('DB_CHARSET', 'utf8mb4');

// 4. PDO Connection
$dsn = "mysql:host=" . DB_HOST . ";dbname=" . DB_NAME . ";charset=" . DB_CHARSET;
$options = [
    // Throw PDOException on errors for proper debugging and transactions
    PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
    // Return rows as associative arrays (e.g., $row['user_id'])
    PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
    // Use real prepared statements in MySQL for security against SQL Injection
    PDO::ATTR_EMULATE_PREPARES   => false,
];

try {
    $pdo = new PDO($dsn, DB_USER, DB_PASS, $options);
} catch (PDOException $e) {
    // If connection fails, show a clean message (helpful for viva explanation)
    die("Database Connection Error: " . htmlspecialchars($e->getMessage(), ENT_QUOTES, 'UTF-8'));
}

/**
 * Helper: Escape HTML strings to prevent Cross-Site Scripting (XSS).
 *
 * @param mixed $value
 * @return string
 */
function e($value) {
    return htmlspecialchars((string)($value ?? ''), ENT_QUOTES, 'UTF-8');
}

/**
 * Helper: Redirect to another page and stop script execution.
 *
 * @param string $path URL or relative path inside CampusTask
 * @return void
 */
function redirect($path) {
    if (str_starts_with($path, 'http://') || str_starts_with($path, 'https://')) {
        header("Location: " . $path);
        exit();
    }

    if (str_starts_with($path, BASE_URL)) {
        header("Location: " . $path);
    } else {
        $clean_path = ltrim($path, '/');
        header("Location: " . BASE_URL . '/' . $clean_path);
    }
    exit();
}

/**
 * Helper: Set a flash message in session for the next page load.
 *
 * @param string $type 'success', 'error', or 'info'
 * @param string $message Flash message text
 * @return void
 */
function set_flash($type, $message) {
    $_SESSION['flash'] = [
        'type' => $type,
        'message' => $message
    ];
}

/**
 * Helper: Retrieve and clear the current flash message from session.
 *
 * @return array|null Returns ['type' => ..., 'message' => ...] or null
 */
function get_flash() {
    if (isset($_SESSION['flash'])) {
        $flash = $_SESSION['flash'];
        unset($_SESSION['flash']);
        return $flash;
    }
    return null;
}
