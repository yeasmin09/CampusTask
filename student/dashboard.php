<?php
/**
 * CampusTask - Student Dashboard
 * 
 * Course: CSE 2291 - Database Management System (DBMS)
 * Role: Dashboard & Analytics (Person B)
 */

$page_title = 'Student Dashboard';

require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/auth.php';

// Strict backend check: Student role required
require_student();

$student_id = current_user_id();

// 1. Live Aggregate Queries for Dashboard Stat Cards
// A. Total Posted Tasks
$stmt = $pdo->prepare('SELECT COUNT(*) FROM tasks WHERE poster_id = :id');
$stmt->execute([':id' => $student_id]);
$posted_count = (int)$stmt->fetchColumn();

// B. Total Submitted Applications
$stmt = $pdo->prepare('SELECT COUNT(*) FROM applications WHERE applicant_id = :id');
$stmt->execute([':id' => $student_id]);
$submitted_count = (int)$stmt->fetchColumn();

// C. Accepted Applications (Freelance jobs won)
$stmt = $pdo->prepare('SELECT COUNT(*) FROM applications WHERE applicant_id = :id AND status = "ACCEPTED"');
$stmt->execute([':id' => $student_id]);
$accepted_count = (int)$stmt->fetchColumn();

// D. Completed Tasks (Both as poster and as accepted freelancer)
$stmt = $pdo->prepare('
    SELECT COUNT(DISTINCT t.task_id) 
    FROM tasks t
    LEFT JOIN applications a ON t.task_id = a.task_id AND a.status = "ACCEPTED"
    WHERE t.status = "COMPLETED" 
      AND (t.poster_id = :id OR a.applicant_id = :id2)
');
$stmt->execute([':id' => $student_id, ':id2' => $student_id]);
$completed_count = (int)$stmt->fetchColumn();

// E. Pending Items (Pending applications sent by student)
$stmt = $pdo->prepare('SELECT COUNT(*) FROM applications WHERE applicant_id = :id AND status = "PENDING"');
$stmt->execute([':id' => $student_id]);
$pending_apps_count = (int)$stmt->fetchColumn();


// 2. Fetch "My Posted Tasks" (Live data with applicant counts)
$posted_tasks_stmt = $pdo->prepare('
    SELECT 
        t.task_id,
        t.title,
        t.budget,
        t.deadline,
        t.status,
        t.created_at,
        c.category_name,
        (SELECT COUNT(*) FROM applications a WHERE a.task_id = t.task_id) AS total_applicants,
        (SELECT COUNT(*) FROM applications a WHERE a.task_id = t.task_id AND a.status = "PENDING") AS pending_applicants
    FROM tasks t
    LEFT JOIN categories c ON t.category_id = c.category_id
    WHERE t.poster_id = :id
    ORDER BY t.created_at DESC
');
$posted_tasks_stmt->execute([':id' => $student_id]);
$my_posted_tasks = $posted_tasks_stmt->fetchAll();


// 3. Fetch "Assigned Work (As Freelancer)"
$assigned_tasks_stmt = $pdo->prepare('
    SELECT 
        t.task_id,
        t.title,
        t.budget,
        t.deadline,
        t.status AS task_status,
        poster.full_name AS poster_name,
        a.proposed_budget,
        a.applied_at
    FROM applications a
    JOIN tasks t ON a.task_id = t.task_id
    JOIN users poster ON t.poster_id = poster.user_id
    WHERE a.applicant_id = :id AND a.status = "ACCEPTED"
    ORDER BY t.created_at DESC
');
$assigned_tasks_stmt->execute([':id' => $student_id]);
$assigned_tasks = $assigned_tasks_stmt->fetchAll();


// 4. Fetch Recent 5 Submitted Applications
$recent_apps_stmt = $pdo->prepare('
    SELECT 
        a.application_id,
        a.task_id,
        a.proposed_budget,
        a.status AS app_status,
        a.applied_at,
        t.title AS task_title,
        t.status AS task_status,
        poster.full_name AS poster_name
    FROM applications a
    JOIN tasks t ON a.task_id = t.task_id
    JOIN users poster ON t.poster_id = poster.user_id
    WHERE a.applicant_id = :id
    ORDER BY a.applied_at DESC
    LIMIT 5
');
$recent_apps_stmt->execute([':id' => $student_id]);
$recent_applications = $recent_apps_stmt->fetchAll();


// 5. Fetch Reviews Received (Reputation summary)
$reviews_received_stmt = $pdo->prepare('
    SELECT 
        r.review_id,
        r.rating,
        r.comment,
        r.created_at,
        reviewer.full_name AS reviewer_name,
        t.title AS task_title
    FROM reviews r
    JOIN users reviewer ON r.reviewer_id = reviewer.user_id
    JOIN tasks t ON r.task_id = t.task_id
    WHERE r.reviewee_id = :id
    ORDER BY r.created_at DESC
    LIMIT 3
');
$reviews_received_stmt->execute([':id' => $student_id]);
$recent_reviews = $reviews_received_stmt->fetchAll();

require_once __DIR__ . '/../includes/header.php';
?>

<div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 1.5rem; flex-wrap: wrap; gap: 0.5rem;">
    <div>
        <h1>Welcome, <?= e($_SESSION['full_name'] ?? 'Student') ?>!</h1>
        <p style="color: var(--gray-600); margin: 0;">Manage your posted tasks, track freelance assignments, and view project progress.</p>
    </div>
    <div style="display: flex; gap: 0.5rem;">
        <a href="<?= BASE_URL ?>/student/post-task.php" class="btn btn-primary btn-sm">+ Post a New Task</a>
        <a href="<?= BASE_URL ?>/student/tasks.php" class="btn btn-outline btn-sm">Find Tasks</a>
    </div>
</div>

<!-- 1. Statistics Cards Grid (Live Aggregates) -->
<div class="stats-grid">
    <div class="stat-card">
        <div class="stat-card-title">Tasks Posted</div>
        <div class="stat-card-value"><?= $posted_count ?></div>
        <div class="stat-card-subtext">Tasks created by you</div>
    </div>
    <div class="stat-card">
        <div class="stat-card-title">Applications Sent</div>
        <div class="stat-card-value"><?= $submitted_count ?></div>
        <div class="stat-card-subtext"><?= $pending_apps_count ?> pending decision</div>
    </div>
    <div class="stat-card">
        <div class="stat-card-title">Accepted Jobs</div>
        <div class="stat-card-value"><?= $accepted_count ?></div>
        <div class="stat-card-subtext">Freelance jobs assigned</div>
    </div>
    <div class="stat-card">
        <div class="stat-card-title">Completed Collaborations</div>
        <div class="stat-card-value"><?= $completed_count ?></div>
        <div class="stat-card-subtext">Successfully delivered</div>
    </div>
</div>

<!-- 2. My Posted Tasks Section -->
<div class="card">
    <div class="card-header">
        <h3 class="card-title">My Posted Tasks (<?= count($my_posted_tasks) ?>)</h3>
        <a href="<?= BASE_URL ?>/student/post-task.php" class="btn btn-primary btn-sm">+ Post Task</a>
    </div>
    <div class="card-body" style="padding: 0;">
        <?php if (empty($my_posted_tasks)): ?>
            <div class="empty-state">
                <div class="empty-state-title">No tasks posted yet</div>
                <div class="empty-state-text">Need help with a presentation, coding assignment, or tutoring? Post your first task now!</div>
                <a href="<?= BASE_URL ?>/student/post-task.php" class="btn btn-primary btn-sm">Create Task</a>
            </div>
        <?php else: ?>
            <div class="table-responsive">
                <table class="table table-striped table-hover">
                    <thead>
                        <tr>
                            <th>Task Title</th>
                            <th>Category</th>
                            <th>Budget</th>
                            <th>Deadline</th>
                            <th>Status</th>
                            <th>Applications</th>
                            <th style="text-align: right;">Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($my_posted_tasks as $task): ?>
                            <tr>
                                <td>
                                    <strong>
                                        <a href="<?= BASE_URL ?>/student/task-details.php?id=<?= e($task['task_id']) ?>">
                                            <?= e($task['title']) ?>
                                        </a>
                                    </strong>
                                </td>
                                <td><?= e($task['category_name'] ?? 'General') ?></td>
                                <td>$<?= number_format((float)$task['budget'], 2) ?></td>
                                <td><?= date('M d, Y', strtotime($task['deadline'])) ?></td>
                                <td>
                                    <?php
                                    $badge = match ($task['status']) {
                                        'OPEN'        => 'badge-open',
                                        'IN_PROGRESS' => 'badge-in-progress',
                                        'COMPLETED'   => 'badge-completed',
                                        default       => 'badge-cancelled',
                                    };
                                    ?>
                                    <span class="badge <?= $badge ?>"><?= e($task['status']) ?></span>
                                </td>
                                <td>
                                    <a href="<?= BASE_URL ?>/student/applications.php?task_id=<?= e($task['task_id']) ?>" style="font-weight: 600;">
                                        <?= (int)$task['total_applicants'] ?> applicant<?= ($task['total_applicants'] == 1) ? '' : 's' ?>
                                    </a>
                                    <?php if ($task['pending_applicants'] > 0): ?>
                                        <span class="badge badge-pending" style="font-size: 0.7rem; margin-left: 0.25rem;">
                                            <?= (int)$task['pending_applicants'] ?> new
                                        </span>
                                    <?php endif; ?>
                                </td>
                                <td style="text-align: right; white-space: nowrap;">
                                    <a href="<?= BASE_URL ?>/student/applications.php?task_id=<?= e($task['task_id']) ?>" class="btn btn-primary btn-sm">
                                        Manage
                                    </a>

                                    <?php if ($task['status'] === 'OPEN'): ?>
                                        <a href="<?= BASE_URL ?>/student/edit-task.php?id=<?= e($task['task_id']) ?>" class="btn btn-secondary btn-sm">
                                            Edit
                                        </a>
                                    <?php endif; ?>

                                    <?php if ($task['status'] === 'COMPLETED'): ?>
                                        <a href="<?= BASE_URL ?>/student/reviews.php?task_id=<?= e($task['task_id']) ?>" class="btn btn-warning btn-sm">
                                            Review
                                        </a>
                                    <?php endif; ?>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        <?php endif; ?>
    </div>
</div>

<!-- 3. Two Columns: Assigned Work (Left) and Recent Applications (Right) -->
<div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(340px, 1fr)); gap: 1.5rem; margin-bottom: 2rem;">
    <!-- Assigned Work As Freelancer -->
    <div class="card">
        <div class="card-header">
            <h3 class="card-title">Assigned Freelance Work (<?= count($assigned_tasks) ?>)</h3>
        </div>
        <div class="card-body" style="padding: 0;">
            <?php if (empty($assigned_tasks)): ?>
                <div class="empty-state" style="padding: 2rem 1rem;">
                    <div class="empty-state-title">No assigned jobs yet</div>
                    <div class="empty-state-text">Apply to open tasks on the marketplace to earn reputation!</div>
                    <a href="<?= BASE_URL ?>/student/tasks.php" class="btn btn-outline btn-sm">Browse Tasks</a>
                </div>
            <?php else: ?>
                <div class="table-responsive">
                    <table class="table table-hover">
                        <thead>
                            <tr>
                                <th>Task</th>
                                <th>Poster</th>
                                <th>Agreed Budget</th>
                                <th>Status</th>
                                <th style="text-align: right;">Action</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($assigned_tasks as $at): ?>
                                <tr>
                                    <td>
                                        <strong>
                                            <a href="<?= BASE_URL ?>/student/task-details.php?id=<?= e($at['task_id']) ?>">
                                                <?= e($at['title']) ?>
                                            </a>
                                        </strong>
                                        <div style="font-size: 0.8rem; color: var(--gray-500);">
                                            Due: <?= date('M d, Y', strtotime($at['deadline'])) ?>
                                        </div>
                                    </td>
                                    <td><?= e($at['poster_name']) ?></td>
                                    <td>$<?= number_format((float)$at['proposed_budget'], 2) ?></td>
                                    <td>
                                        <?php
                                        $at_badge = match ($at['task_status']) {
                                            'IN_PROGRESS' => 'badge-in-progress',
                                            'COMPLETED'   => 'badge-completed',
                                            default       => 'badge-cancelled',
                                        };
                                        ?>
                                        <span class="badge <?= $at_badge ?>"><?= e($at['task_status']) ?></span>
                                    </td>
                                    <td style="text-align: right;">
                                        <?php if ($at['task_status'] === 'COMPLETED'): ?>
                                            <a href="<?= BASE_URL ?>/student/reviews.php?task_id=<?= e($at['task_id']) ?>" class="btn btn-warning btn-sm">
                                                Review Poster
                                            </a>
                                        <?php else: ?>
                                            <span style="font-size: 0.85rem; color: var(--warning); font-weight: 600;">In Progress</span>
                                        <?php endif; ?>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            <?php endif; ?>
        </div>
    </div>

    <!-- Recent Submitted Applications -->
    <div class="card">
        <div class="card-header">
            <h3 class="card-title">Recent Applications</h3>
            <a href="<?= BASE_URL ?>/student/my-applications.php" class="btn btn-outline btn-sm">View All</a>
        </div>
        <div class="card-body" style="padding: 0;">
            <?php if (empty($recent_applications)): ?>
                <div class="empty-state" style="padding: 2rem 1rem;">
                    <div class="empty-state-title">No applications submitted</div>
                    <div class="empty-state-text">You haven't applied to any tasks yet.</div>
                </div>
            <?php else: ?>
                <div class="table-responsive">
                    <table class="table table-hover">
                        <thead>
                            <tr>
                                <th>Task Title</th>
                                <th>Offer</th>
                                <th>Status</th>
                                <th>Date</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($recent_applications as $ra): ?>
                                <tr>
                                    <td>
                                        <a href="<?= BASE_URL ?>/student/task-details.php?id=<?= e($ra['task_id']) ?>">
                                            <?= e(mb_strimwidth($ra['task_title'], 0, 32, '...')) ?>
                                        </a>
                                    </td>
                                    <td>$<?= number_format((float)$ra['proposed_budget'], 2) ?></td>
                                    <td>
                                        <?php
                                        $r_badge = match ($ra['app_status']) {
                                            'ACCEPTED'  => 'badge-accepted',
                                            'REJECTED'  => 'badge-rejected',
                                            'WITHDRAWN' => 'badge-withdrawn',
                                            default     => 'badge-pending',
                                        };
                                        ?>
                                        <span class="badge <?= $r_badge ?>"><?= e($ra['app_status']) ?></span>
                                    </td>
                                    <td><?= date('M d', strtotime($ra['applied_at'])) ?></td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            <?php endif; ?>
        </div>
    </div>
</div>

<!-- 4. Reputation / Recent Reviews Received -->
<?php if (!empty($recent_reviews)): ?>
    <div class="card">
        <div class="card-header">
            <h3 class="card-title">Recent Feedback Received</h3>
        </div>
        <div class="card-body">
            <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(260px, 1fr)); gap: 1rem;">
                <?php foreach ($recent_reviews as $rev): ?>
                    <div style="background-color: var(--gray-50); border: 1px solid var(--gray-200); border-radius: var(--border-radius); padding: 1rem;">
                        <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 0.5rem;">
                            <strong><?= e($rev['reviewer_name']) ?></strong>
                            <span class="rating-stars" style="font-size: 0.95rem;">
                                <?php for ($i = 1; $i <= 5; $i++): ?>
                                    <span class="<?= ($i <= $rev['rating']) ? 'star-filled' : '' ?>">&starf;</span>
                                <?php endfor; ?>
                            </span>
                        </div>
                        <div style="font-size: 0.8rem; color: var(--gray-500); margin-bottom: 0.5rem;">
                            Task: <?= e($rev['task_title']) ?>
                        </div>
                        <p style="font-size: 0.875rem; color: var(--gray-700); margin: 0;">
                            &ldquo;<?= nl2br(e($rev['comment'])) ?>&rdquo;
                        </p>
                    </div>
                <?php endforeach; ?>
            </div>
        </div>
    </div>
<?php endif; ?>

<?php
require_once __DIR__ . '/../includes/footer.php';
?>
