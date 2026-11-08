<?php
/**
 * CampusTask - My Applications
 * 
 * Course: CSE 2291 - Database Management System (DBMS)
 * Role: Task Applications (Person B)
 */

$page_title = 'My Applications';

require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/auth.php';

// Strict backend check: Only STUDENT users can view own applications
require_student();

$current_student_id = current_user_id();

// Handle Application Withdrawal (POST Request)
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';
    $application_id = (int)($_POST['application_id'] ?? 0);

    if ($action === 'withdraw' && $application_id > 0) {
        // Query application and verify ownership and PENDING status
        $stmt = $pdo->prepare('
            SELECT application_id, task_id, status 
            FROM applications 
            WHERE application_id = :app_id AND applicant_id = :student_id 
            LIMIT 1
        ');
        $stmt->execute([
            ':app_id'     => $application_id,
            ':student_id' => $current_student_id
        ]);
        $app = $stmt->fetch();

        if (!$app) {
            set_flash('error', 'Application not found or unauthorized.');
        } elseif ($app['status'] !== 'PENDING') {
            set_flash('error', 'Only PENDING applications can be withdrawn (Current Status: ' . $app['status'] . ').');
        } else {
            // Update status to WITHDRAWN
            $update_stmt = $pdo->prepare('
                UPDATE applications 
                SET status = "WITHDRAWN" 
                WHERE application_id = :app_id
            ');
            $update_stmt->execute([':app_id' => $application_id]);

            set_flash('success', 'Your application has been withdrawn.');
        }

        redirect('student/my-applications.php');
    }
}

// Filter by Status (Optional query param)
$status_filter = trim($_GET['status'] ?? '');

// Fetch all applications submitted by the logged-in student
$sql = "
    SELECT 
        a.application_id,
        a.task_id,
        a.proposal,
        a.proposed_budget,
        a.status AS app_status,
        a.applied_at,
        t.title AS task_title,
        t.budget AS task_budget,
        t.deadline AS task_deadline,
        t.status AS task_status,
        c.category_name,
        poster.full_name AS poster_name
    FROM applications a
    JOIN tasks t ON a.task_id = t.task_id
    LEFT JOIN categories c ON t.category_id = c.category_id
    JOIN users poster ON t.poster_id = poster.user_id
    WHERE a.applicant_id = :student_id
";

$params = [':student_id' => $current_student_id];

if (in_array($status_filter, ['PENDING', 'ACCEPTED', 'REJECTED', 'WITHDRAWN'], true)) {
    $sql .= " AND a.status = :status";
    $params[':status'] = $status_filter;
}

$sql .= " ORDER BY a.applied_at DESC";

$stmt = $pdo->prepare($sql);
$stmt->execute($params);
$my_applications = $stmt->fetchAll();

require_once __DIR__ . '/../includes/header.php';
?>

<div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 1.5rem; flex-wrap: wrap; gap: 0.5rem;">
    <div>
        <h1>My Applications</h1>
        <p style="color: var(--gray-600); margin: 0;">Track the progress of your submitted task proposals and applications.</p>
    </div>
    <a href="<?= BASE_URL ?>/student/tasks.php" class="btn btn-primary btn-sm">Find More Tasks</a>
</div>

<!-- Status Filters -->
<div class="filter-bar" style="padding: 0.5rem 1rem;">
    <span style="font-weight: 600; font-size: 0.85rem; color: var(--gray-600); margin-right: 0.5rem;">Filter by Status:</span>
    <a href="<?= BASE_URL ?>/student/my-applications.php" class="btn <?= empty($status_filter) ? 'btn-primary' : 'btn-outline' ?> btn-sm">All</a>
    <a href="<?= BASE_URL ?>/student/my-applications.php?status=PENDING" class="btn <?= ($status_filter === 'PENDING') ? 'btn-primary' : 'btn-outline' ?> btn-sm">Pending</a>
    <a href="<?= BASE_URL ?>/student/my-applications.php?status=ACCEPTED" class="btn <?= ($status_filter === 'ACCEPTED') ? 'btn-primary' : 'btn-outline' ?> btn-sm">Accepted</a>
    <a href="<?= BASE_URL ?>/student/my-applications.php?status=REJECTED" class="btn <?= ($status_filter === 'REJECTED') ? 'btn-primary' : 'btn-outline' ?> btn-sm">Rejected</a>
    <a href="<?= BASE_URL ?>/student/my-applications.php?status=WITHDRAWN" class="btn <?= ($status_filter === 'WITHDRAWN') ? 'btn-primary' : 'btn-outline' ?> btn-sm">Withdrawn</a>
</div>

<!-- Applications List -->
<div class="card">
    <div class="card-header">
        <h3 class="card-title">Submitted Applications (<?= count($my_applications) ?>)</h3>
    </div>
    <div class="card-body" style="padding: 0;">
        <?php if (empty($my_applications)): ?>
            <div class="empty-state">
                <div class="empty-state-title">No applications found</div>
                <div class="empty-state-text">You have not submitted any applications matching this filter.</div>
                <a href="<?= BASE_URL ?>/student/tasks.php" class="btn btn-primary btn-sm">Browse Open Tasks</a>
            </div>
        <?php else: ?>
            <div class="table-responsive">
                <table class="table table-striped table-hover">
                    <thead>
                        <tr>
                            <th>Task</th>
                            <th>Posted By</th>
                            <th>Offered Budget</th>
                            <th>Proposed Budget</th>
                            <th>Status</th>
                            <th>Applied Date</th>
                            <th style="text-align: right;">Action</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($my_applications as $app): ?>
                            <tr>
                                <td>
                                    <strong>
                                        <a href="<?= BASE_URL ?>/student/task-details.php?id=<?= e($app['task_id']) ?>">
                                            <?= e($app['task_title']) ?>
                                        </a>
                                    </strong>
                                    <div style="font-size: 0.8rem; color: var(--gray-500);">
                                        Category: <?= e($app['category_name'] ?? 'General') ?> &bull; Task Status: <?= e($app['task_status']) ?>
                                    </div>
                                    <div style="font-size: 0.85rem; color: var(--gray-600); margin-top: 0.25rem;">
                                        <em>&ldquo;<?= e(mb_strimwidth($app['proposal'], 0, 80, '...')) ?>&rdquo;</em>
                                    </div>
                                </td>
                                <td><?= e($app['poster_name']) ?></td>
                                <td>$<?= number_format((float)$app['task_budget'], 2) ?></td>
                                <td><strong>$<?= number_format((float)$app['proposed_budget'], 2) ?></strong></td>
                                <td>
                                    <?php
                                    $status_class = match ($app['app_status']) {
                                        'ACCEPTED'  => 'badge-accepted',
                                        'REJECTED'  => 'badge-rejected',
                                        'WITHDRAWN' => 'badge-withdrawn',
                                        default     => 'badge-pending',
                                    };
                                    ?>
                                    <span class="badge <?= $status_class ?>"><?= e($app['app_status']) ?></span>
                                </td>
                                <td><?= date('M d, Y', strtotime($app['applied_at'])) ?></td>
                                <td style="text-align: right;">
                                    <?php if ($app['app_status'] === 'PENDING'): ?>
                                        <form action="<?= BASE_URL ?>/student/my-applications.php" method="POST" style="display: inline-block;">
                                            <input type="hidden" name="action" value="withdraw">
                                            <input type="hidden" name="application_id" value="<?= e($app['application_id']) ?>">
                                            <button 
                                                type="submit" 
                                                class="btn btn-outline btn-sm"
                                                onclick="return confirm('Are you sure you want to withdraw this application?');"
                                            >
                                                Withdraw
                                            </button>
                                        </form>
                                    <?php elseif ($app['app_status'] === 'ACCEPTED' && $app['task_status'] === 'COMPLETED'): ?>
                                        <a href="<?= BASE_URL ?>/student/reviews.php?task_id=<?= e($app['task_id']) ?>" class="btn btn-warning btn-sm">
                                            Leave Review
                                        </a>
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
