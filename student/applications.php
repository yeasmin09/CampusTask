<?php
/**
 * CampusTask - Manage Task Applications (Task Owner View)
 * 
 * Course: CSE 2291 - Database Management System (DBMS)
 * Role: Task Management & Transactions (Person B)
 */

$page_title = 'Manage Applications';

require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/auth.php';

// Strict backend check: Student role required
require_student();

$current_student_id = current_user_id();
$task_id = (int)($_REQUEST['task_id'] ?? 0);

if ($task_id <= 0) {
    set_flash('error', 'Invalid task specified.');
    redirect('student/dashboard.php');
}

// 1. Fetch Task Details and Verify Ownership
$stmt = $pdo->prepare('
    SELECT t.task_id, t.poster_id, t.title, t.description, t.budget, t.deadline, t.status, c.category_name
    FROM tasks t
    LEFT JOIN categories c ON t.category_id = c.category_id
    WHERE t.task_id = :id
    LIMIT 1
');
$stmt->execute([':id' => $task_id]);
$task = $stmt->fetch();

if (!$task) {
    set_flash('error', 'Task not found.');
    redirect('student/dashboard.php');
}

// Ownership check: Only task poster can manage applications for this task
if ((int)$task['poster_id'] !== $current_student_id) {
    set_flash('error', 'Access denied. You can only manage applications for tasks you posted.');
    redirect('student/dashboard.php');
}

// 2. Handle POST Actions (Accept, Reject, Mark Completed, Cancel Task)
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';
    $application_id = (int)($_POST['application_id'] ?? 0);

    // ACTION: Accept Applicant (Mandatory Database Transaction)
    if ($action === 'accept') {
        if ($task['status'] !== 'OPEN') {
            set_flash('error', 'Applicants can only be accepted when the task is OPEN.');
            redirect('student/applications.php?task_id=' . $task_id);
        }

        if ($application_id <= 0) {
            set_flash('error', 'Invalid application selected.');
            redirect('student/applications.php?task_id=' . $task_id);
        }

        // Verify application belongs to this task and is PENDING
        $app_check = $pdo->prepare('SELECT application_id, status FROM applications WHERE application_id = :app_id AND task_id = :task_id LIMIT 1');
        $app_check->execute([':app_id' => $application_id, ':task_id' => $task_id]);
        $app_row = $app_check->fetch();

        if (!$app_row || $app_row['status'] !== 'PENDING') {
            set_flash('error', 'Application is no longer pending or does not exist.');
            redirect('student/applications.php?task_id=' . $task_id);
        }

        // Execute Database Transaction
        try {
            $pdo->beginTransaction();

            // 1. Mark chosen application as ACCEPTED
            $accept_stmt = $pdo->prepare('UPDATE applications SET status = "ACCEPTED" WHERE application_id = :app_id');
            $accept_stmt->execute([':app_id' => $application_id]);

            // 2. Mark all other PENDING applications for this task as REJECTED
            $reject_others_stmt = $pdo->prepare('
                UPDATE applications 
                SET status = "REJECTED" 
                WHERE task_id = :task_id AND application_id != :app_id AND status = "PENDING"
            ');
            $reject_others_stmt->execute([
                ':task_id' => $task_id,
                ':app_id'  => $application_id
            ]);

            // 3. Update task status to IN_PROGRESS
            $task_status_stmt = $pdo->prepare('UPDATE tasks SET status = "IN_PROGRESS", updated_at = NOW() WHERE task_id = :task_id');
            $task_status_stmt->execute([':task_id' => $task_id]);

            // Commit all changes atomically
            $pdo->commit();

            set_flash('success', 'Applicant accepted! Task is now IN_PROGRESS and other applications have been updated.');
        } catch (Exception $e) {
            $pdo->rollBack();
            set_flash('error', 'Transaction failed: Unable to accept application. Please try again.');
        }

        redirect('student/applications.php?task_id=' . $task_id);
    }

    // ACTION: Reject a single application
    if ($action === 'reject') {
        if ($task['status'] !== 'OPEN') {
            set_flash('error', 'Cannot reject applications on non-open tasks.');
            redirect('student/applications.php?task_id=' . $task_id);
        }

        $reject_stmt = $pdo->prepare('
            UPDATE applications 
            SET status = "REJECTED" 
            WHERE application_id = :app_id AND task_id = :task_id AND status = "PENDING"
        ');
        $reject_stmt->execute([':app_id' => $application_id, ':task_id' => $task_id]);

        set_flash('info', 'Application has been rejected.');
        redirect('student/applications.php?task_id=' . $task_id);
    }

    // ACTION: Mark task as COMPLETED
    if ($action === 'complete') {
        if ($task['status'] !== 'IN_PROGRESS') {
            set_flash('error', 'Only tasks currently IN_PROGRESS can be marked as COMPLETED.');
            redirect('student/applications.php?task_id=' . $task_id);
        }

        $complete_stmt = $pdo->prepare('UPDATE tasks SET status = "COMPLETED", updated_at = NOW() WHERE task_id = :task_id');
        $complete_stmt->execute([':task_id' => $task_id]);

        set_flash('success', 'Task has been marked as COMPLETED! You can now write a review for the student freelancer.');
        redirect('student/applications.php?task_id=' . $task_id);
    }

    // ACTION: Cancel task
    if ($action === 'cancel') {
        if ($task['status'] === 'COMPLETED' || $task['status'] === 'CANCELLED') {
            set_flash('error', 'This task cannot be cancelled.');
            redirect('student/applications.php?task_id=' . $task_id);
        }

        try {
            $pdo->beginTransaction();

            // Reject any remaining pending applications
            $cancel_apps = $pdo->prepare('UPDATE applications SET status = "REJECTED" WHERE task_id = :task_id AND status = "PENDING"');
            $cancel_apps->execute([':task_id' => $task_id]);

            // Set task to CANCELLED
            $cancel_task = $pdo->prepare('UPDATE tasks SET status = "CANCELLED", updated_at = NOW() WHERE task_id = :task_id');
            $cancel_task->execute([':task_id' => $task_id]);

            $pdo->commit();
            set_flash('info', 'Task has been cancelled.');
        } catch (Exception $e) {
            $pdo->rollBack();
            set_flash('error', 'Failed to cancel task.');
        }

        redirect('student/applications.php?task_id=' . $task_id);
    }
}

// 3. Fetch All Applicants for this Task (with Student Profile Details)
$applicants_stmt = $pdo->prepare('
    SELECT 
        a.application_id,
        a.proposal,
        a.proposed_budget,
        a.status AS app_status,
        a.applied_at,
        u.user_id AS applicant_user_id,
        u.full_name AS applicant_name,
        u.email AS applicant_email,
        p.student_id,
        p.department,
        p.bio
    FROM applications a
    JOIN users u ON a.applicant_id = u.user_id
    LEFT JOIN profiles p ON u.user_id = p.user_id
    WHERE a.task_id = :task_id
    ORDER BY 
        CASE a.status 
            WHEN "ACCEPTED" THEN 1
            WHEN "PENDING" THEN 2
            WHEN "REJECTED" THEN 3
            ELSE 4 
        END,
        a.applied_at DESC
');
$applicants_stmt->execute([':task_id' => $task_id]);
$applicants = $applicants_stmt->fetchAll();

require_once __DIR__ . '/../includes/header.php';
?>

<!-- Task Summary Header -->
<div class="card" style="margin-bottom: 2rem;">
    <div class="card-header">
        <div>
            <span style="font-size: 0.85rem; color: var(--gray-500); text-transform: uppercase; font-weight: 600;">Managing Applications</span>
            <h2 class="card-title" style="font-size: 1.4rem; margin-top: 0.25rem;">
                <a href="<?= BASE_URL ?>/student/task-details.php?id=<?= e($task['task_id']) ?>">
                    <?= e($task['title']) ?>
                </a>
            </h2>
        </div>
        <div>
            <?php
            $task_badge = match ($task['status']) {
                'OPEN'        => 'badge-open',
                'IN_PROGRESS' => 'badge-in-progress',
                'COMPLETED'   => 'badge-completed',
                default       => 'badge-cancelled',
            };
            ?>
            <span class="badge <?= $task_badge ?>" style="font-size: 0.85rem; padding: 0.35rem 0.75rem;">
                Status: <?= e($task['status']) ?>
            </span>
        </div>
    </div>
    <div class="card-body">
        <div style="display: flex; gap: 2rem; flex-wrap: wrap; margin-bottom: 1rem; font-size: 0.95rem;">
            <div><strong>Category:</strong> <?= e($task['category_name'] ?? 'General') ?></div>
            <div><strong>Budget:</strong> $<?= number_format((float)$task['budget'], 2) ?></div>
            <div><strong>Deadline:</strong> <?= date('M d, Y', strtotime($task['deadline'])) ?></div>
        </div>
        <p style="color: var(--gray-700); margin-bottom: 1rem;"><?= nl2br(e($task['description'])) ?></p>

        <!-- Task Management Actions -->
        <div style="display: flex; gap: 0.75rem; flex-wrap: wrap; padding-top: 0.75rem; border-top: 1px solid var(--gray-200);">
            <?php if ($task['status'] === 'IN_PROGRESS'): ?>
                <form action="<?= BASE_URL ?>/student/applications.php?task_id=<?= e($task_id) ?>" method="POST" style="display: inline;">
                    <input type="hidden" name="action" value="complete">
                    <button type="submit" class="btn btn-success btn-sm" onclick="return confirm('Mark this task as COMPLETED?');">
                        &check; Mark as Completed
                    </button>
                </form>
            <?php endif; ?>

            <?php if ($task['status'] === 'COMPLETED'): ?>
                <a href="<?= BASE_URL ?>/student/reviews.php?task_id=<?= e($task_id) ?>" class="btn btn-warning btn-sm">
                    &star; Leave Review for Freelancer
                </a>
            <?php endif; ?>

            <?php if (in_array($task['status'], ['OPEN', 'IN_PROGRESS'], true)): ?>
                <form action="<?= BASE_URL ?>/student/applications.php?task_id=<?= e($task_id) ?>" method="POST" style="display: inline;">
                    <input type="hidden" name="action" value="cancel">
                    <button type="submit" class="btn btn-danger btn-sm" onclick="return confirm('Are you sure you want to cancel this task?');">
                        Cancel Task
                    </button>
                </form>
            <?php endif; ?>

            <a href="<?= BASE_URL ?>/student/dashboard.php" class="btn btn-secondary btn-sm">
                &larr; Back to Dashboard
            </a>
        </div>
    </div>
</div>

<!-- Applicants List -->
<div class="card">
    <div class="card-header">
        <h3 class="card-title">Applicants (<?= count($applicants) ?>)</h3>
    </div>
    <div class="card-body" style="padding: 0;">
        <?php if (empty($applicants)): ?>
            <div class="empty-state">
                <div class="empty-state-title">No applications yet</div>
                <div class="empty-state-text">No students have applied to this task yet. As soon as someone applies, you can review their proposal here.</div>
            </div>
        <?php else: ?>
            <div class="table-responsive">
                <table class="table table-striped table-hover">
                    <thead>
                        <tr>
                            <th>Applicant</th>
                            <th>Student Info</th>
                            <th>Proposed Budget</th>
                            <th>Proposal</th>
                            <th>Status</th>
                            <th>Applied Date</th>
                            <th style="text-align: right;">Action</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($applicants as $app): ?>
                            <tr>
                                <td>
                                    <strong><?= e($app['applicant_name']) ?></strong>
                                    <div style="font-size: 0.8rem; color: var(--gray-500);"><?= e($app['applicant_email']) ?></div>
                                </td>
                                <td>
                                    <div style="font-size: 0.85rem;">
                                        ID: <?= e($app['student_id'] ?? 'N/A') ?><br>
                                        Dept: <?= e($app['department'] ?? 'N/A') ?>
                                    </div>
                                </td>
                                <td><strong>$<?= number_format((float)$app['proposed_budget'], 2) ?></strong></td>
                                <td style="max-width: 280px;">
                                    <div style="font-size: 0.9rem; color: var(--gray-700);">
                                        <?= nl2br(e($app['proposal'])) ?>
                                    </div>
                                </td>
                                <td>
                                    <?php
                                    $app_badge = match ($app['app_status']) {
                                        'ACCEPTED'  => 'badge-accepted',
                                        'REJECTED'  => 'badge-rejected',
                                        'WITHDRAWN' => 'badge-withdrawn',
                                        default     => 'badge-pending',
                                    };
                                    ?>
                                    <span class="badge <?= $app_badge ?>"><?= e($app['app_status']) ?></span>
                                </td>
                                <td><?= date('M d, Y', strtotime($app['applied_at'])) ?></td>
                                <td style="text-align: right; white-space: nowrap;">
                                    <?php if ($task['status'] === 'OPEN' && $app['app_status'] === 'PENDING'): ?>
                                        <!-- Accept Form (Triggers Database Transaction) -->
                                        <form action="<?= BASE_URL ?>/student/applications.php?task_id=<?= e($task_id) ?>" method="POST" style="display: inline-block;">
                                            <input type="hidden" name="action" value="accept">
                                            <input type="hidden" name="application_id" value="<?= e($app['application_id']) ?>">
                                            <button 
                                                type="submit" 
                                                class="btn btn-success btn-sm"
                                                onclick="return confirm('Accept this student? Other pending applications will be rejected and the task will be marked IN_PROGRESS.');"
                                            >
                                                Accept
                                            </button>
                                        </form>

                                        <!-- Reject Form -->
                                        <form action="<?= BASE_URL ?>/student/applications.php?task_id=<?= e($task_id) ?>" method="POST" style="display: inline-block;">
                                            <input type="hidden" name="action" value="reject">
                                            <input type="hidden" name="application_id" value="<?= e($app['application_id']) ?>">
                                            <button 
                                                type="submit" 
                                                class="btn btn-danger btn-sm"
                                                onclick="return confirm('Reject this application?');"
                                            >
                                                Reject
                                            </button>
                                        </form>
                                    <?php elseif ($app['app_status'] === 'ACCEPTED'): ?>
                                        <span style="font-size: 0.85rem; color: var(--success-dark); font-weight: 600;">Selected Helper</span>
                                    <?php else: ?>
                                        <span style="font-size: 0.85rem; color: var(--gray-400);">&mdash;</span>
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

<?php
require_once __DIR__ . '/../includes/footer.php';
?>
