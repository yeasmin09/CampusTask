<?php
/**
 * CampusTask - User Login
 * 
 * Course: CSE 2291 - Database Management System (DBMS)
 * Role: Authentication (Person B)
 */

$page_title = 'Login';

// Load configuration and database connection
$config_path = __DIR__ . '/config/database.php';
if (file_exists($config_path)) {
    require_once $config_path;
} else {
    require_once __DIR__ . '/config/database.example.php';
}

// If already logged in, redirect to respective dashboard
if (isset($_SESSION['user_id'])) {
    if (($_SESSION['role'] ?? '') === 'ADMIN') {
        redirect('admin/dashboard.php');
    } else {
        redirect('student/dashboard.php');
    }
}

$errors = [];
$email = '';

// Process form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $email = trim($_POST['email'] ?? '');
    $password = $_POST['password'] ?? '';

    // Validation
    if ($email === '') {
        $errors[] = 'Email address is required.';
    } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $errors[] = 'Please enter a valid email address.';
    }

    if ($password === '') {
        $errors[] = 'Password is required.';
    }

    // Attempt authentication if validation passes
    if (empty($errors)) {
        if (!isset($pdo) || $pdo === null) {
            $errors[] = 'Database connection is not available. Please ensure MySQL is running in XAMPP.';
        } else {
            try {
                // Prepared statement to prevent SQL Injection
                $stmt = $pdo->prepare('SELECT user_id, full_name, email, password_hash, role, account_status FROM users WHERE email = :email LIMIT 1');
                $stmt->execute([':email' => $email]);
                $user = $stmt->fetch();

                if ($user && password_verify($password, $user['password_hash'])) {
                    // Check if account is active
                    if ($user['account_status'] !== 'ACTIVE') {
                        $errors[] = 'Your account has been deactivated. Please contact an administrator.';
                    } else {
                        // Prevent Session Fixation attack
                        session_regenerate_id(true);

                        // Store session data (as specified in Section 11)
                        $_SESSION['user_id'] = (int)$user['user_id'];
                        $_SESSION['full_name'] = $user['full_name'];
                        $_SESSION['role'] = $user['role'];

                        set_flash('success', 'Welcome back, ' . $user['full_name'] . '!');

                        // Redirect based on role
                        if ($user['role'] === 'ADMIN') {
                            redirect('admin/dashboard.php');
                        } else {
                            redirect('student/dashboard.php');
                        }
                    }
                } else {
                    // Generic error message for security (don't reveal whether email exists)
                    $errors[] = 'Invalid email or password.';
                }
            } catch (PDOException $e) {
                $errors[] = 'A database error occurred. Please check that the database schema is imported.';
            }
        }
    }
}

// Include shared header layout
require_once __DIR__ . '/includes/header.php';
?>

<div style="max-width: 480px; margin: 2rem auto;">
    <div class="card">
        <div class="card-header">
            <h2 class="card-title">Sign In to CampusTask</h2>
        </div>
        <div class="card-body">
            <?php if (!empty($errors)): ?>
                <div class="flash-alert flash-error" style="margin-bottom: 1rem;">
                    <ul style="margin-left: 1.25rem; margin-bottom: 0;">
                        <?php foreach ($errors as $error): ?>
                            <li><?= e($error) ?></li>
                        <?php endforeach; ?>
                    </ul>
                </div>
            <?php endif; ?>

            <form action="<?= BASE_URL ?>/login.php" method="POST" novalidate>
                <div class="form-group">
                    <label for="email">University Email</label>
                    <input 
                        type="email" 
                        id="email" 
                        name="email" 
                        class="form-control" 
                        placeholder="e.g. rafi@student.test"
                        value="<?= e($email) ?>" 
                        required 
                        autocomplete="email"
                    >
                </div>

                <div class="form-group">
                    <label for="password">Password</label>
                    <input 
                        type="password" 
                        id="password" 
                        name="password" 
                        class="form-control" 
                        placeholder="Enter your password"
                        required 
                        autocomplete="current-password"
                    >
                    <div style="margin-top: 0.5rem;">
                        <label style="font-size: 0.85rem; font-weight: normal; cursor: pointer; display: inline-flex; align-items: center; gap: 0.35rem;">
                            <input type="checkbox" id="togglePassword"> Show password
                        </label>
                    </div>
                </div>

                <button type="submit" class="btn btn-primary btn-block" style="margin-top: 1rem;">
                    Sign In
                </button>
            </form>

            <div style="text-align: center; margin-top: 1.25rem; padding-top: 1rem; border-top: 1px solid var(--gray-200); font-size: 0.9rem;">
                Don't have an account? <a href="<?= BASE_URL ?>/register.php">Register here</a>
            </div>
        </div>
    </div>

    <!-- Demo Credentials Helper Box (Helpful for Viva & Testing) -->
    <div class="card" style="background-color: var(--gray-50); border: 1px dashed var(--gray-300);">
        <div class="card-body" style="font-size: 0.85rem; color: var(--gray-600);">
            <strong style="color: var(--gray-800); display: block; margin-bottom: 0.35rem;">Demo Accounts (from Seed Data):</strong>
            <div style="margin-bottom: 0.25rem;">
                <strong>Admin:</strong> <code>admin@campustask.test</code> &bull; Password: <code>Admin@123</code>
            </div>
            <div>
                <strong>Student:</strong> <code>rafi@student.test</code> or <code>nusrat@student.test</code> &bull; Password: <code>Student@123</code>
            </div>
        </div>
    </div>
</div>

<script>
    // Simple vanilla JS toggle for password visibility
    const togglePassword = document.getElementById('togglePassword');
    const passwordInput = document.getElementById('password');

    if (togglePassword && passwordInput) {
        togglePassword.addEventListener('change', function() {
            passwordInput.type = this.checked ? 'text' : 'password';
        });
    }
</script>

<?php
// Include shared footer layout
require_once __DIR__ . '/includes/footer.php';
?>
