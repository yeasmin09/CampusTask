<?php
/**
 * CampusTask - Student Profile Management
 * 
 * Course: CSE 2291 - Database Management System (DBMS)
 * Role: Profile Management (Person A - Feature A2)
 * Demonstrates: One-to-One relationship between users and profiles tables
 */

$page_title = 'My Profile';

require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/auth.php';

// Strict backend check: Only logged-in students can view and manage their profile
require_student();

$current_user_id = current_user_id();
$errors = [];

// 1. Fetch current user details
$user_stmt = $pdo->prepare('SELECT user_id, full_name, email, role, account_status, created_at FROM users WHERE user_id = :id LIMIT 1');
$user_stmt->execute([':id' => $current_user_id]);
$user = $user_stmt->fetch();

if (!$user) {
    set_flash('error', 'User account not found.');
    redirect('logout.php');
}

// 2. Fetch existing profile (One-to-One relationship)
$profile_stmt = $pdo->prepare('SELECT profile_id, student_id, department, bio, profile_image, created_at FROM profiles WHERE user_id = :id LIMIT 1');
$profile_stmt->execute([':id' => $current_user_id]);
$profile = $profile_stmt->fetch();

// Prepopulate form fields
$full_name = $user['full_name'];
$student_id = $profile['student_id'] ?? '';
$department = $profile['department'] ?? '';
$bio = $profile['bio'] ?? '';

// 3. Handle Profile Update (POST)
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $full_name = trim($_POST['full_name'] ?? '');
    $student_id = trim($_POST['student_id'] ?? '');
    $department = trim($_POST['department'] ?? '');
    $bio = trim($_POST['bio'] ?? '');

    // Validation
    if ($full_name === '') {
        $errors[] = 'Full name is required.';
    } elseif (mb_strlen($full_name) > 100) {
        $errors[] = 'Full name cannot exceed 100 characters.';
    }

    if ($student_id !== '' && mb_strlen($student_id) > 50) {
        $errors[] = 'Student ID cannot exceed 50 characters.';
    }

    if ($department !== '' && mb_strlen($department) > 100) {
        $errors[] = 'Department name cannot exceed 100 characters.';
    }

    if ($bio !== '' && mb_strlen($bio) > 1000) {
        $errors[] = 'Bio cannot exceed 1000 characters.';
    }

    if (empty($errors)) {
        try {
            // Update user full name
            $update_user_stmt = $pdo->prepare('UPDATE users SET full_name = :full_name WHERE user_id = :id');
            $update_user_stmt->execute([
                ':full_name' => $full_name,
                ':id'        => $current_user_id
            ]);
            $_SESSION['full_name'] = $full_name;

            // Save profile details (create row if first save, or update if exists)
            if ($profile) {
                $update_profile_stmt = $pdo->prepare('
                    UPDATE profiles 
                    SET student_id = :student_id, department = :department, bio = :bio 
                    WHERE user_id = :user_id
                ');
                $update_profile_stmt->execute([
                    ':student_id' => $student_id ?: null,
                    ':department' => $department ?: null,
                    ':bio'        => $bio ?: null,
                    ':user_id'    => $current_user_id
                ]);
            } else {
                $insert_profile_stmt = $pdo->prepare('
                    INSERT INTO profiles (user_id, student_id, department, bio, created_at) 
                    VALUES (:user_id, :student_id, :department, :bio, NOW())
                ');
                $insert_profile_stmt->execute([
                    ':user_id'    => $current_user_id,
                    ':student_id' => $student_id ?: null,
                    ':department' => $department ?: null,
                    ':bio'        => $bio ?: null
                ]);
            }

            set_flash('success', 'Profile updated successfully!');
            redirect('student/profile.php');
        } catch (PDOException $e) {
            $errors[] = 'An error occurred while saving your profile: ' . $e->getMessage();
        }
    }
}

// 4. Quick student activity statistics
$task_count_stmt = $pdo->prepare('SELECT COUNT(*) FROM tasks WHERE poster_id = :id');
$task_count_stmt->execute([':id' => $current_user_id]);
$posted_tasks_count = (int)$task_count_stmt->fetchColumn();

$app_count_stmt = $pdo->prepare('SELECT COUNT(*) FROM applications WHERE applicant_id = :id');
$app_count_stmt->execute([':id' => $current_user_id]);
$submitted_apps_count = (int)$app_count_stmt->fetchColumn();

require_once __DIR__ . '/../includes/header.php';
?>

<div style="max-width: 900px; margin: 2rem auto;">
    <div style="margin-bottom: 1.5rem; display: flex; justify-content: space-between; align-items: center; flex-wrap: wrap; gap: 1rem;">
        <div>
            <h1 style="font-size: 1.75rem; font-weight: 700; color: var(--gray-900); margin-bottom: 0.25rem;">
                Student Profile
            </h1>
            <p style="color: var(--gray-600); font-size: 0.95rem;">
                Manage your academic identity and information visible across the campus marketplace.
            </p>
        </div>
        <a href="<?= BASE_URL ?>/student/dashboard.php" class="btn btn-secondary">
            &larr; Back to Dashboard
        </a>
    </div>

    <?php if (!empty($errors)): ?>
        <div class="flash-alert flash-error" style="margin-bottom: 1.5rem;">
            <ul style="margin-left: 1.25rem; margin-bottom: 0;">
                <?php foreach ($errors as $error): ?>
                    <li><?= e($error) ?></li>
                <?php endforeach; ?>
            </ul>
        </div>
    <?php endif; ?>

    <div style="display: grid; grid-template-columns: 1fr 2fr; gap: 1.5rem;">
        <!-- Left Column: Profile Card & Activity Summary -->
        <div>
            <div class="card" style="text-align: center;">
                <div class="card-body">
                    <div style="width: 80px; height: 80px; border-radius: 50%; background: linear-gradient(135deg, var(--primary-color), #4f46e5); color: #fff; font-size: 2rem; font-weight: 700; display: inline-flex; align-items: center; justify-content: center; margin-bottom: 1rem; box-shadow: var(--shadow-md);">
                        <?= strtoupper(substr($user['full_name'], 0, 1)) ?>
                    </div>
                    <h3 style="font-size: 1.25rem; font-weight: 600; color: var(--gray-900); margin-bottom: 0.25rem;">
                        <?= e($user['full_name']) ?>
                    </h3>
                    <p style="color: var(--gray-600); font-size: 0.85rem; margin-bottom: 0.75rem;">
                        <?= e($user['email']) ?>
                    </p>
                    <span class="badge" style="background-color: var(--primary-light); color: var(--primary-color); font-weight: 600;">
                        <?= e($user['role']) ?>
                    </span>
                    
                    <hr style="border: 0; border-top: 1px solid var(--gray-200); margin: 1.25rem 0;">

                    <div style="text-align: left; font-size: 0.85rem; color: var(--gray-600); line-height: 1.8;">
                        <div>
                            <strong>Department:</strong> <?= !empty($profile['department']) ? e($profile['department']) : '<span style="color: var(--gray-400);">Not set</span>' ?>
                        </div>
                        <div>
                            <strong>Student ID:</strong> <?= !empty($profile['student_id']) ? e($profile['student_id']) : '<span style="color: var(--gray-400);">Not set</span>' ?>
                        </div>
                        <div>
                            <strong>Joined:</strong> <?= date('M d, Y', strtotime($user['created_at'])) ?>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Marketplace Stats Card -->
            <div class="card" style="margin-top: 1.5rem;">
                <div class="card-header">
                    <h4 class="card-title" style="font-size: 1rem;">Marketplace Activity</h4>
                </div>
                <div class="card-body" style="padding: 1rem;">
                    <div style="display: flex; justify-content: space-between; align-items: center; padding: 0.5rem 0; border-bottom: 1px solid var(--gray-100);">
                        <span style="color: var(--gray-600); font-size: 0.9rem;">Tasks Posted</span>
                        <strong style="color: var(--gray-900); font-size: 1.1rem;"><?= $posted_tasks_count ?></strong>
                    </div>
                    <div style="display: flex; justify-content: space-between; align-items: center; padding: 0.5rem 0;">
                        <span style="color: var(--gray-600); font-size: 0.9rem;">Applications Submitted</span>
                        <strong style="color: var(--gray-900); font-size: 1.1rem;"><?= $submitted_apps_count ?></strong>
                    </div>
                </div>
            </div>
        </div>

        <!-- Right Column: Edit Profile Form -->
        <div>
            <div class="card">
                <div class="card-header">
                    <h3 class="card-title">Edit Profile Information</h3>
                </div>
                <div class="card-body">
                    <form action="<?= BASE_URL ?>/student/profile.php" method="POST">
                        <div class="form-group">
                            <label for="full_name">Full Name <span style="color: var(--danger-color);">*</span></label>
                            <input 
                                type="text" 
                                id="full_name" 
                                name="full_name" 
                                class="form-control" 
                                value="<?= e($full_name) ?>" 
                                required
                            >
                        </div>

                        <div class="form-group">
                            <label for="email">University Email</label>
                            <input 
                                type="email" 
                                id="email" 
                                class="form-control" 
                                value="<?= e($user['email']) ?>" 
                                disabled 
                                style="background-color: var(--gray-100); cursor: not-allowed;"
                            >
                            <small style="color: var(--gray-500); font-size: 0.8rem;">
                                Registered university email address cannot be changed.
                            </small>
                        </div>

                        <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 1rem;">
                            <div class="form-group">
                                <label for="student_id">Student ID</label>
                                <input 
                                    type="text" 
                                    id="student_id" 
                                    name="student_id" 
                                    class="form-control" 
                                    placeholder="e.g. ST-2022-108"
                                    value="<?= e($student_id) ?>"
                                >
                            </div>

                            <div class="form-group">
                                <label for="department">Department</label>
                                <input 
                                    type="text" 
                                    id="department" 
                                    name="department" 
                                    class="form-control" 
                                    placeholder="e.g. Computer Science"
                                    value="<?= e($department) ?>"
                                >
                            </div>
                        </div>

                        <div class="form-group">
                            <label for="bio">Bio / Academic Interests</label>
                            <textarea 
                                id="bio" 
                                name="bio" 
                                rows="4" 
                                class="form-control" 
                                placeholder="Describe your technical skills, software expertise, and collaboration interests..."
                            ><?= e($bio) ?></textarea>
                            <small style="color: var(--gray-500); font-size: 0.8rem;">
                                Brief introduction shown to task owners when you submit applications.
                            </small>
                        </div>

                        <button type="submit" class="btn btn-primary" style="margin-top: 0.5rem;">
                            Save Profile Changes
                        </button>
                    </form>
                </div>
            </div>
        </div>
    </div>
</div>

<?php
require_once __DIR__ . '/../includes/footer.php';
?>
