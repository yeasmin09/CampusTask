<?php
/**
 * CampusTask - Task Details Page
 * 
 * Course: CSE 2291 - Database Management System (DBMS)
 * Role: Task Marketplace (Person A - Feature A7)
 * Interacts with:
 *  - student/apply.php (Person B): Submits task application
 *  - student/applications.php (Person B): Task owner applicant management
 *  - student/edit-task.php (Person A): Task owner edit/delete options
 */

// Load configuration and database connection
$config_path = __DIR__ . '/../config/database.php';
if (file_exists($config_path)) {
    require_once $config_path;
} else {
    require_once __DIR__ . '/../config/database.example.php';
}

require_once __DIR__ . '/../includes/auth.php';

$task_id = (int)($_GET['id'] ?? 0);

if ($task_id <= 0) {
    set_flash('error', 'Invalid task specified.');
    redirect('student/tasks.php');
}

// 1. Fetch Task Details with poster profile and category
$stmt = $pdo->prepare('
    SELECT 
        t.*,
        u.full_name AS poster_name,
        u.email AS poster_email,
        p.department AS poster_department,
        p.student_id AS poster_student_id,
        c.category_name,
        (SELECT COUNT(*) FROM applications a WHERE a.task_id = t.task_id) AS total_applicants,
        (SELECT COUNT(*) FROM applications a WHERE a.task_id = t.task_id AND a.status = "ACCEPTED") AS accepted_count
    FROM tasks t
    JOIN users u ON t.poster_id = u.user_id
    LEFT JOIN profiles p ON u.user_id = p.user_id
    JOIN categories c ON t.category_id = c.category_id
    WHERE t.task_id = :id
    LIMIT 1
');
$stmt->execute([':id' => $task_id]);
$task = $stmt->fetch();

if (!$task) {
    set_flash('error', 'The requested task was not found.');
    redirect('student/tasks.php');
}

$page_title = $task['title'];

// 2. Fetch associated skills
$skills_stmt = $pdo->prepare('
    SELECT s.skill_id, s.skill_name 
    FROM task_skills ts 
    JOIN skills s ON ts.skill_id = s.skill_id 
    WHERE ts.task_id = :task_id 
    ORDER BY s.skill_name ASC
');
$skills_stmt->execute([':task_id' => $task_id]);
$skills = $skills_stmt->fetchAll();

// 3. User Authorization and Application Context
$is_logged = is_logged_in();
$user_id = current_user_id();
$user_role = current_user_role();
$is_owner = ($is_logged && (int)$task['poster_id'] === $user_id);
$is_student = ($is_logged && $user_role === 'STUDENT');

// Check if current student has already applied
$existing_app = null;
if ($is_student && !$is_owner) {
    $app_check = $pdo->prepare('
        SELECT application_id, status, proposed_budget, proposal, applied_at 
        FROM applications 
        WHERE task_id = :task_id AND applicant_id = :applicant_id 
        LIMIT 1
    ');
    $app_check->execute([
        ':task_id'      => $task_id,
        ':applicant_id' => $user_id
    ]);
    $existing_app = $app_check->fetch();
}

// Determine status badge class
$status_badge = 'badge-success';
if ($task['status'] === 'IN_PROGRESS') {
    $status_badge = 'badge-warning';
} elseif ($task['status'] === 'COMPLETED') {
    $status_badge = 'badge-info';
} elseif ($task['status'] === 'CANCELLED') {
    $status_badge = 'badge-danger';
}

require_once __DIR__ . '/../includes/header.php';
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
    box-shadow: 0 4px 15px rgba(16, 47, 70, 0.05) !important;
}
.badge-success {
    background-color: rgba(54, 124, 137, 0.15) !important;
    color: #367C89 !important;
    font-weight: 700 !important;
}
</style>

<div style="max-width: 1000px; margin: 2rem auto;">
    <!-- Navigation Breadcrumb -->
    <div style="margin-bottom: 1.25rem;">
        <a href="<?= BASE_URL ?>/student/tasks.php" style="color: var(--primary-color); text-decoration: none; font-size: 0.9rem;">
            &larr; Back to Task Marketplace
        </a>
    </div>

    <div style="display: grid; grid-template-columns: 2fr 1fr; gap: 2rem; align-items: start;">
        <!-- Left Column: Task Specifications -->
        <div>
            <div class="card" style="margin-bottom: 1.5rem;">
                <div class="card-header" style="padding-bottom: 1rem;">
                    <div style="display: flex; justify-content: space-between; align-items: center; gap: 0.5rem; margin-bottom: 0.75rem;">
                        <span class="badge" style="background-color: var(--gray-100); color: var(--gray-700); font-weight: 500;">
                            <?= e($task['category_name']) ?>
                        </span>
                        <span class="badge <?= $status_badge ?>">
                            <?= e($task['status']) ?>
                        </span>
                    </div>
                    <h1 style="font-size: 1.65rem; font-weight: 700; color: var(--gray-900); line-height: 1.35;">
                        <?= e($task['title']) ?>
                    </h1>
                    <div style="color: var(--gray-500); font-size: 0.85rem; margin-top: 0.5rem;">
                        Posted by <strong><?= e($task['poster_name']) ?></strong> 
                        <?php if (!empty($task['poster_department'])): ?>
                            (<?= e($task['poster_department']) ?>)
                        <?php endif; ?>
                        &bull; <?= date('M d, Y', strtotime($task['created_at'])) ?>
                    </div>
                </div>

                <div class="card-body">
                    <h3 style="font-size: 1.05rem; font-weight: 600; color: var(--gray-800); margin-bottom: 0.5rem;">
                        Task Description
                    </h3>
                    <div style="color: var(--gray-700); line-height: 1.7; font-size: 0.95rem; white-space: pre-wrap; margin-bottom: 1.5rem;">
<?= e($task['description']) ?>
                    </div>

                    <?php if (!empty($skills)): ?>
                        <h4 style="font-size: 0.95rem; font-weight: 600; color: var(--gray-800); margin-bottom: 0.5rem;">
                            Required Skills
                        </h4>
                        <div style="display: flex; flex-wrap: wrap; gap: 0.4rem; margin-bottom: 1.5rem;">
                            <?php foreach ($skills as $skill): ?>
                                <span class="badge" style="background-color: var(--primary-light); color: var(--primary-color); font-weight: 500; font-size: 0.8rem; padding: 0.25rem 0.6rem;">
                                    <?= e($skill['skill_name']) ?>
                                </span>
                            <?php endforeach; ?>
                        </div>
                    <?php endif; ?>
                </div>
            </div>

            <!-- Application Section -->
            <?php if ($is_owner): ?>
                <!-- Task Owner Panel -->
                <div class="card" style="border-left: 4px solid var(--primary-color);">
                    <div class="card-body">
                        <h3 style="font-size: 1.15rem; font-weight: 600; margin-bottom: 0.5rem; color: var(--gray-900);">
                            You are the Poster of this Task
                        </h3>
                        <p style="color: var(--gray-600); font-size: 0.9rem; margin-bottom: 1rem;">
                            You have received <strong><?= (int)$task['total_applicants'] ?></strong> application<?= (int)$task['total_applicants'] === 1 ? '' : 's' ?> so far.
                        </p>
                        <div style="display: flex; gap: 0.75rem; flex-wrap: wrap;">
                            <a href="<?= BASE_URL ?>/student/applications.php?task_id=<?= $task_id ?>" class="btn btn-primary">
                                Review Applicants (<?= (int)$task['total_applicants'] ?>)
                            </a>
                            <?php if ($task['status'] === 'OPEN'): ?>
                                <a href="<?= BASE_URL ?>/student/edit-task.php?id=<?= $task_id ?>" class="btn btn-secondary">
                                    Edit / Delete Task
                                </a>
                            <?php elseif ($task['status'] === 'COMPLETED'): ?>
                                <a href="<?= BASE_URL ?>/student/reviews.php?task_id=<?= $task_id ?>" class="btn btn-secondary">
                                    Manage Review
                                </a>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>

            <?php elseif ($existing_app): ?>
                <!-- Already Applied Notice -->
                <div class="card" style="border-left: 4px solid var(--info-color);">
                    <div class="card-body">
                        <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 0.5rem;">
                            <h3 style="font-size: 1.15rem; font-weight: 600; color: var(--gray-900);">
                                Your Application Submitted
                            </h3>
                            <?php
                            $app_status_badge = 'badge-warning';
                            if ($existing_app['status'] === 'ACCEPTED') $app_status_badge = 'badge-success';
                            if ($existing_app['status'] === 'REJECTED') $app_status_badge = 'badge-danger';
                            if ($existing_app['status'] === 'WITHDRAWN') $app_status_badge = 'badge-secondary';
                            ?>
                            <span class="badge <?= $app_status_badge ?>">
                                <?= e($existing_app['status']) ?>
                            </span>
                        </div>
                        <p style="color: var(--gray-600); font-size: 0.9rem; margin-bottom: 0.75rem;">
                            You submitted an application on <?= date('M d, Y', strtotime($existing_app['applied_at'])) ?> with a proposed budget of <strong>$<?= number_format((float)$existing_app['proposed_budget'], 2) ?></strong>.
                        </p>
                        <div style="background-color: var(--gray-50); padding: 0.75rem; border-radius: var(--radius-sm); font-size: 0.85rem; color: var(--gray-700); margin-bottom: 1rem;">
                            <strong>Your Proposal:</strong> <?= nl2br(e($existing_app['proposal'])) ?>
                        </div>
                        <a href="<?= BASE_URL ?>/student/my-applications.php" class="btn btn-secondary btn-sm">
                            View All My Applications
                        </a>
                    </div>
                </div>

            <?php elseif ($is_student && $task['status'] === 'OPEN'): ?>
                <!-- Student Apply Form (Posts to Person B student/apply.php) -->
                <div class="card">
                    <div class="card-header">
                        <h3 class="card-title">Apply for this Task</h3>
                        <p style="color: var(--gray-600); font-size: 0.85rem; margin-top: 0.25rem;">
                            Submit your proposal and proposed budget to collaborate with the task poster.
                        </p>
                    </div>
                    <div class="card-body">
                        <form action="<?= BASE_URL ?>/student/apply.php" method="POST">
                            <input type="hidden" name="task_id" value="<?= $task_id ?>">

                            <div class="form-group">
                                <label for="proposed_budget">Proposed Budget ($) <span style="color: var(--danger-color);">*</span></label>
                                <input 
                                    type="number" 
                                    id="proposed_budget" 
                                    name="proposed_budget" 
                                    class="form-control" 
                                    step="0.01" 
                                    min="0"
                                    value="<?= e((string)$task['budget']) ?>"
                                    required
                                >
                                <small style="color: var(--gray-500); font-size: 0.8rem;">
                                    Original posted budget is $<?= number_format((float)$task['budget'], 2) ?>. You may offer your rate.
                                </small>
                            </div>

                            <div class="form-group">
                                <label for="proposal">Why are you suited for this task? <span style="color: var(--danger-color);">*</span></label>
                                <textarea 
                                    id="proposal" 
                                    name="proposal" 
                                    rows="4" 
                                    class="form-control" 
                                    placeholder="Briefly state your relevant skills, expected delivery timeline, and approach..."
                                    required
                                ></textarea>
                                <small style="color: var(--gray-500); font-size: 0.8rem;">
                                    Minimum 10 characters required.
                                </small>
                            </div>

                            <button type="submit" class="btn btn-primary btn-block">
                                Submit Task Application
                            </button>
                        </form>
                    </div>
                </div>

            <?php elseif ($task['status'] !== 'OPEN'): ?>
                <!-- Task Not Open Notice -->
                <div class="card" style="background-color: var(--gray-50); border: 1px dashed var(--gray-300); text-align: center; padding: 1.5rem;">
                    <h4 style="font-size: 1rem; color: var(--gray-700); margin-bottom: 0.25rem;">
                        Applications Closed
                    </h4>
                    <p style="font-size: 0.85rem; color: var(--gray-500);">
                        This task is currently <strong><?= e($task['status']) ?></strong> and is not accepting new student proposals.
                    </p>
                </div>

            <?php else: ?>
                <!-- Guest Prompt -->
                <div class="card" style="text-align: center; padding: 2rem 1.5rem; background-color: var(--gray-50); border: 1px dashed var(--gray-300);">
                    <h3 style="font-size: 1.15rem; font-weight: 600; color: var(--gray-800); margin-bottom: 0.5rem;">
                        Interested in completing this task?
                    </h3>
                    <p style="color: var(--gray-600); font-size: 0.9rem; max-width: 400px; margin: 0 auto 1.25rem auto;">
                        Sign in to your university student account to submit your proposal and proposed budget.
                    </p>
                    <div style="display: flex; gap: 0.75rem; justify-content: center;">
                        <a href="<?= BASE_URL ?>/login.php" class="btn btn-primary">
                            Log In
                        </a>
                        <a href="<?= BASE_URL ?>/register.php" class="btn btn-secondary">
                            Register
                        </a>
                    </div>
                </div>
            <?php endif; ?>
        </div>

        <!-- Right Column: Task Overview Sidebar -->
        <div>
            <!-- Overview Card -->
            <div class="card" style="margin-bottom: 1.5rem;">
                <div class="card-header">
                    <h3 class="card-title" style="font-size: 1.1rem;">Task Summary</h3>
                </div>
                <div class="card-body" style="padding: 1rem 1.25rem; font-size: 0.9rem;">
                    <div style="display: flex; justify-content: space-between; align-items: center; padding: 0.65rem 0; border-bottom: 1px solid var(--gray-100);">
                        <span style="color: var(--gray-600);">Budget:</span>
                        <strong style="color: var(--success-color); font-size: 1.15rem;">$<?= number_format((float)$task['budget'], 2) ?></strong>
                    </div>
                    <div style="display: flex; justify-content: space-between; align-items: center; padding: 0.65rem 0; border-bottom: 1px solid var(--gray-100);">
                        <span style="color: var(--gray-600);">Deadline:</span>
                        <strong style="color: var(--gray-900);"><?= date('M d, Y', strtotime($task['deadline'])) ?></strong>
                    </div>
                    <div style="display: flex; justify-content: space-between; align-items: center; padding: 0.65rem 0; border-bottom: 1px solid var(--gray-100);">
                        <span style="color: var(--gray-600);">Status:</span>
                        <span class="badge <?= $status_badge ?>"><?= e($task['status']) ?></span>
                    </div>
                    <div style="display: flex; justify-content: space-between; align-items: center; padding: 0.65rem 0;">
                        <span style="color: var(--gray-600);">Applicants:</span>
                        <strong style="color: var(--gray-900);"><?= (int)$task['total_applicants'] ?> applied</strong>
                    </div>
                </div>
            </div>

            <!-- Poster Info Card -->
            <div class="card">
                <div class="card-header">
                    <h3 class="card-title" style="font-size: 1.1rem;">About the Poster</h3>
                </div>
                <div class="card-body" style="font-size: 0.9rem;">
                    <div style="display: flex; align-items: center; gap: 0.75rem; margin-bottom: 0.75rem;">
                        <div style="width: 44px; height: 44px; border-radius: 50%; background-color: var(--primary-light); color: var(--primary-color); font-weight: 700; display: flex; align-items: center; justify-content: center; font-size: 1.1rem;">
                            <?= strtoupper(substr($task['poster_name'], 0, 1)) ?>
                        </div>
                        <div>
                            <strong style="color: var(--gray-900); display: block;"><?= e($task['poster_name']) ?></strong>
                            <span style="color: var(--gray-500); font-size: 0.8rem;"><?= !empty($task['poster_department']) ? e($task['poster_department']) : 'Student' ?></span>
                        </div>
                    </div>
                    <p style="color: var(--gray-600); font-size: 0.85rem; line-height: 1.5; margin-bottom: 0;">
                        Verified student collaborator on CampusTask.
                    </p>
                </div>
            </div>
        </div>
    </div>
</div>

<?php
require_once __DIR__ . '/../includes/footer.php';
?>
