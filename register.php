<?php
/**
 * CampusTask - Student Registration
 * 
 * Course: CSE 2291 - Database Management System (DBMS)
 * Role: Authentication & User Onboarding (Person A - Feature A1)
 */

$page_title = 'Register';

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
$full_name = '';
$email = '';

// Process registration form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $full_name = trim($_POST['full_name'] ?? '');
    $email = trim($_POST['email'] ?? '');
    $password = $_POST['password'] ?? '';
    $password_confirm = $_POST['password_confirm'] ?? '';

    // 1. Validation
    if ($full_name === '') {
        $errors[] = 'Full name is required.';
    } elseif (mb_strlen($full_name) < 3 || mb_strlen($full_name) > 100) {
        $errors[] = 'Full name must be between 3 and 100 characters.';
    }

    if ($email === '') {
        $errors[] = 'University email address is required.';
    } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $errors[] = 'Please enter a valid university email address.';
    }

    if ($password === '') {
        $errors[] = 'Password is required.';
    } elseif (strlen($password) < 6) {
        $errors[] = 'Password must be at least 6 characters long.';
    }

    if ($password !== $password_confirm) {
        $errors[] = 'Password confirmation does not match.';
    }

    // 2. Check for duplicate email and create account if valid
    if (empty($errors)) {
        if (!isset($pdo) || $pdo === null) {
            $errors[] = 'Database connection error. Please ensure MySQL is running in XAMPP.';
        } else {
            try {
                // Check if email is already registered
                $check_stmt = $pdo->prepare('SELECT user_id FROM users WHERE email = :email LIMIT 1');
                $check_stmt->execute([':email' => $email]);
                if ($check_stmt->fetch()) {
                    $errors[] = 'This email address is already registered. Please log in instead.';
                } else {
                    // Hash the password securely using bcrypt
                    $password_hash = password_hash($password, PASSWORD_DEFAULT);

                    // Public registration form strictly assigns role 'STUDENT'
                    $insert_stmt = $pdo->prepare(
                        'INSERT INTO users (full_name, email, password_hash, role, account_status, created_at)
                         VALUES (:full_name, :email, :password_hash, :role, :account_status, NOW())'
                    );

                    $insert_stmt->execute([
                        ':full_name'      => $full_name,
                        ':email'          => $email,
                        ':password_hash'  => $password_hash,
                        ':role'           => 'STUDENT',
                        ':account_status' => 'ACTIVE'
                    ]);

                    $new_user_id = (int)$pdo->lastInsertId();

                    // Regenerate session ID to prevent session fixation attacks
                    session_regenerate_id(true);

                    // Log the user in directly as specified in the shared interface
                    $_SESSION['user_id'] = $new_user_id;
                    $_SESSION['full_name'] = $full_name;
                    $_SESSION['role'] = 'STUDENT';

                    set_flash('success', 'Account created successfully! Welcome to CampusTask, ' . $full_name . '.');
                    redirect('student/dashboard.php');
                }
            } catch (PDOException $e) {
                if ($e->getCode() === '23000') {
                    $errors[] = 'This email address is already registered.';
                } else {
                    $errors[] = 'A database error occurred during registration. Please try again.';
                }
            }
        }
    }
}

// Include shared header layout
require_once __DIR__ . '/includes/header.php';
?>

<style>
/* Corporate Palette Integration */
:root {
    --primary: #367C89 !important;
    --primary-color: #367C89 !important;
    --primary-hover: #2b646e !important;
    --primary-light: rgba(54, 124, 137, 0.12) !important;
    --gray-900: #102F46 !important;
    --gray-800: #163D57 !important;
    --gray-600: #7597A8 !important;
}
.btn-primary, .btn-primary-sm {
    background-color: #367C89 !important;
    border-color: #367C89 !important;
    color: #FFFFFF !important;
    border-radius: 6px !important;
    font-weight: 600 !important;
}
.btn-primary:hover, .btn-primary-sm:hover {
    background-color: #2b646e !important;
    border-color: #2b646e !important;
}
.card {
    border: 1px solid rgba(117, 151, 168, 0.22) !important;
    border-radius: 14px !important;
    box-shadow: 0 10px 30px rgba(16, 47, 70, 0.08) !important;
}
</style>

<div style="max-width: 520px; margin: 2rem auto;">
    <div class="card">
        <div class="card-header">
            <h2 class="card-title">Create a Student Account</h2>
            <p style="color: var(--gray-600); font-size: 0.9rem; margin-top: 0.25rem;">
                Join the CampusTask marketplace to find tasks or offer your skills.
            </p>
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

            <form action="<?= BASE_URL ?>/register.php" method="POST" novalidate>
                <div class="form-group">
                    <label for="full_name">Full Name <span style="color: var(--danger-color);">*</span></label>
                    <input 
                        type="text" 
                        id="full_name" 
                        name="full_name" 
                        class="form-control" 
                        placeholder="e.g. Tanvir Hassan"
                        value="<?= e($full_name) ?>" 
                        required 
                        autocomplete="name"
                    >
                </div>

                <div class="form-group">
                    <label for="email">University Email <span style="color: var(--danger-color);">*</span></label>
                    <input 
                        type="email" 
                        id="email" 
                        name="email" 
                        class="form-control" 
                        placeholder="e.g. tanvir@student.test"
                        value="<?= e($email) ?>" 
                        required 
                        autocomplete="email"
                    >
                    <small style="color: var(--gray-500); font-size: 0.8rem;">
                        Must be a unique valid university email address.
                    </small>
                </div>

                <div class="form-group">
                    <label for="password">Password <span style="color: var(--danger-color);">*</span></label>
                    <input 
                        type="password" 
                        id="password" 
                        name="password" 
                        class="form-control" 
                        placeholder="At least 6 characters"
                        required 
                        autocomplete="new-password"
                    >
                </div>

                <div class="form-group">
                    <label for="password_confirm">Confirm Password <span style="color: var(--danger-color);">*</span></label>
                    <input 
                        type="password" 
                        id="password_confirm" 
                        name="password_confirm" 
                        class="form-control" 
                        placeholder="Re-enter your password"
                        required 
                        autocomplete="new-password"
                    >
                    <div style="margin-top: 0.5rem;">
                        <label style="font-size: 0.85rem; font-weight: normal; cursor: pointer; display: inline-flex; align-items: center; gap: 0.35rem;">
                            <input type="checkbox" id="togglePassword"> Show passwords
                        </label>
                    </div>
                </div>

                <div class="flash-alert flash-info" style="font-size: 0.85rem; margin-top: 0.5rem; margin-bottom: 1rem;">
                    <strong>Note:</strong> All new registrations automatically receive a <strong>STUDENT</strong> role with full access to post tasks, apply to freelance tasks, and manage profiles.
                </div>

                <button type="submit" class="btn btn-primary btn-block" style="margin-top: 0.5rem;">
                    Create Account
                </button>
            </form>

            <div style="text-align: center; margin-top: 1.25rem; padding-top: 1rem; border-top: 1px solid var(--gray-200); font-size: 0.9rem;">
                Already have an account? <a href="<?= BASE_URL ?>/login.php">Log in here</a>
            </div>
        </div>
    </div>
</div>

<script>
    // Simple vanilla JS toggle for password visibility
    const togglePassword = document.getElementById('togglePassword');
    const passwordInput = document.getElementById('password');
    const passwordConfirmInput = document.getElementById('password_confirm');

    if (togglePassword && passwordInput && passwordConfirmInput) {
        togglePassword.addEventListener('change', function() {
            const newType = this.checked ? 'text' : 'password';
            passwordInput.type = newType;
            passwordConfirmInput.type = newType;
        });
    }
</script>

<?php
// Include shared footer layout
require_once __DIR__ . '/includes/footer.php';
?>
