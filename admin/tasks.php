<?php
/**
 * CampusTask - Admin Task Moderation
 * 
 * Course: CSE 2291 - Database Management System (DBMS)
 * Role: Administration (Person A - Feature A9)
 * Demonstrates:
 *  - Administrative task supervision and moderation
 *  - Aggregate SQL functions (AVG, SUM, COUNT)
 *  - Status modification (Disable/Cancel) and permanent deletion
 *  - Multi-parameter search, filtering, and whitelisted sorting
 */

$page_title = 'Task Moderation';

require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/auth.php';

// Strict backend check: Only ADMIN can access
require_admin();

// 1. Process Moderation Actions (POST: Cancel or Delete)
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';
    $task_id = (int)($_POST['task_id'] ?? 0);

    if ($task_id <= 0) {
        set_flash('error', 'Invalid task specified.');
        redirect('admin/tasks.php');
    }

    // Verify task exists
    $check_stmt = $pdo->prepare('SELECT task_id, title, status FROM tasks WHERE task_id = :id LIMIT 1');
    $check_stmt->execute([':id' => $task_id]);
    $target_task = $check_stmt->fetch();

    if (!$target_task) {
        set_flash('error', 'Target task not found.');
        redirect('admin/tasks.php');
    }

    // ACTION: DISABLE / CANCEL TASK
    if ($action === 'cancel') {
        try {
            $cancel_stmt = $pdo->prepare('UPDATE tasks SET status = "CANCELLED", updated_at = NOW() WHERE task_id = :id');
            $cancel_stmt->execute([':id' => $task_id]);

            set_flash('success', "Task #{$task_id} ('" . e($target_task['title']) . "') has been disabled and marked as CANCELLED.");
            redirect('admin/tasks.php');
        } catch (PDOException $e) {
            set_flash('error', 'Database error disabling task: ' . $e->getMessage());
            redirect('admin/tasks.php');
        }
    }

    // ACTION: RE-OPEN TASK
    elseif ($action === 'reopen') {
        try {
            $reopen_stmt = $pdo->prepare('UPDATE tasks SET status = "OPEN", updated_at = NOW() WHERE task_id = :id');
            $reopen_stmt->execute([':id' => $task_id]);

            set_flash('success', "Task #{$task_id} has been reopened with OPEN status.");
            redirect('admin/tasks.php');
        } catch (PDOException $e) {
            set_flash('error', 'Database error reopening task: ' . $e->getMessage());
            redirect('admin/tasks.php');
        }
    }

    // ACTION: DELETE TASK
    elseif ($action === 'delete') {
        try {
            $pdo->beginTransaction();

            $del_stmt = $pdo->prepare('DELETE FROM tasks WHERE task_id = :id');
            $del_stmt->execute([':id' => $task_id]);

            $pdo->commit();

            set_flash('success', "Task #{$task_id} and all associated applications have been permanently deleted.");
            redirect('admin/tasks.php');
        } catch (PDOException $e) {
            if ($pdo->inTransaction()) {
                $pdo->rollBack();
            }
            set_flash('error', 'Database error deleting task: ' . $e->getMessage());
            redirect('admin/tasks.php');
        }
    }
}

// 2. Fetch High-Level Moderation Statistics (Live SQL Aggregates)
$stats_sql = '
    SELECT 
        COUNT(*) AS total_tasks,
        SUM(CASE WHEN status = "OPEN" THEN 1 ELSE 0 END) AS open_tasks,
        SUM(CASE WHEN status = "IN_PROGRESS" THEN 1 ELSE 0 END) AS in_progress_tasks,
        SUM(CASE WHEN status = "COMPLETED" THEN 1 ELSE 0 END) AS completed_tasks,
        SUM(CASE WHEN status = "CANCELLED" THEN 1 ELSE 0 END) AS cancelled_tasks,
        COALESCE(ROUND(AVG(CASE WHEN status != "CANCELLED" THEN budget ELSE NULL END), 2), 0.00) AS avg_budget,
        COALESCE(SUM(CASE WHEN status != "CANCELLED" THEN budget ELSE 0 END), 0.00) AS total_budget
    FROM tasks
';
$stats = $pdo->query($stats_sql)->fetch();

// 3. Search, Filter, and Sort Controls
$categories = $pdo->query('SELECT category_id, category_name FROM categories ORDER BY category_name ASC')->fetchAll();

$search = trim($_GET['search'] ?? '');
$category_id = (int)($_GET['category_id'] ?? 0);
$status_filter = trim($_GET['status'] ?? '');
$sort = trim($_GET['sort'] ?? 'newest');

$sort_whitelist = [
    'newest'        => 't.created_at DESC',
    'oldest'        => 't.created_at ASC',
    'budget_high'   => 't.budget DESC',
    'budget_low'    => 't.budget ASC',
    'deadline_near' => 't.deadline ASC',
    'title_az'      => 't.title ASC'
];
$order_by = $sort_whitelist[$sort] ?? 't.created_at DESC';

$where_clauses = [];
$params = [];

if ($search !== '') {
    $where_clauses[] = '(t.title LIKE :s1 OR t.description LIKE :s2 OR u.full_name LIKE :s3 OR u.email LIKE :s4)';
    $params[':s1'] = '%' . $search . '%';
    $params[':s2'] = '%' . $search . '%';
    $params[':s3'] = '%' . $search . '%';
    $params[':s4'] = '%' . $search . '%';
}

if ($category_id > 0) {
    $where_clauses[] = 't.category_id = :cat_id';
    $params[':cat_id'] = $category_id;
}

if ($status_filter !== '' && in_array($status_filter, ['OPEN', 'IN_PROGRESS', 'COMPLETED', 'CANCELLED'], true)) {
    $where_clauses[] = 't.status = :status';
    $params[':status'] = $status_filter;
}

$where_sql = !empty($where_clauses) ? 'WHERE ' . implode(' AND ', $where_clauses) : '';

$tasks_query = "
    SELECT 
        t.task_id,
        t.poster_id,
        t.title,
        t.budget,
        t.deadline,
        t.status,
        t.created_at,
        c.category_name,
        u.full_name AS poster_name,
        u.email AS poster_email,
        (SELECT COUNT(*) FROM applications a WHERE a.task_id = t.task_id) AS app_count
    FROM tasks t
    JOIN categories c ON t.category_id = c.category_id
    JOIN users u ON t.poster_id = u.user_id
    $where_sql
    ORDER BY $order_by
";

$stmt = $pdo->prepare($tasks_query);
$stmt->execute($params);
$tasks = $stmt->fetchAll();

require_once __DIR__ . '/../includes/header.php';
?>

<div style="max-width: 1200px; margin: 2rem auto;">
    <!-- Page Header -->
    <div style="display: flex; justify-content: space-between; align-items: center; flex-wrap: wrap; gap: 1rem; margin-bottom: 1.5rem;">
        <div>
            <h1 style="font-size: 1.85rem; font-weight: 700; color: var(--gray-900); margin-bottom: 0.25rem;">
                Task Moderation
            </h1>
            <p style="color: var(--gray-600); font-size: 0.95rem;">
                Supervise, inspect, disable inappropriate content, or permanently remove tasks.
            </p>
        </div>
        <a href="<?= BASE_URL ?>/admin/dashboard.php" class="btn btn-secondary">
            &larr; Admin Dashboard
        </a>
    </div>

    <!-- Aggregate KPI Summary Cards -->
    <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(200px, 1fr)); gap: 1rem; margin-bottom: 1.75rem;">
        <div class="card" style="padding: 1.25rem; border-left: 4px solid var(--primary-color);">
            <span style="font-size: 0.85rem; color: var(--gray-600); font-weight: 500;">Total Tasks</span>
            <div style="font-size: 1.75rem; font-weight: 700; color: var(--gray-900); margin-top: 0.25rem;">
                <?= (int)$stats['total_tasks'] ?>
            </div>
            <div style="font-size: 0.75rem; color: var(--gray-500); margin-top: 0.25rem;">
                <?= (int)$stats['open_tasks'] ?> Open &bull; <?= (int)$stats['in_progress_tasks'] ?> Active
            </div>
        </div>

        <div class="card" style="padding: 1.25rem; border-left: 4px solid var(--success-color);">
            <span style="font-size: 0.85rem; color: var(--gray-600); font-weight: 500;">Average Task Budget</span>
            <div style="font-size: 1.75rem; font-weight: 700; color: var(--success-color); margin-top: 0.25rem;">
                $<?= number_format((float)$stats['avg_budget'], 2) ?>
            </div>
            <div style="font-size: 0.75rem; color: var(--gray-500); margin-top: 0.25rem;">
                Excluding cancelled tasks
            </div>
        </div>

        <div class="card" style="padding: 1.25rem; border-left: 4px solid var(--info-color);">
            <span style="font-size: 0.85rem; color: var(--gray-600); font-weight: 500;">Total Marketplace Budget</span>
            <div style="font-size: 1.75rem; font-weight: 700; color: var(--gray-900); margin-top: 0.25rem;">
                $<?= number_format((float)$stats['total_budget'], 2) ?>
            </div>
            <div style="font-size: 0.75rem; color: var(--gray-500); margin-top: 0.25rem;">
                Posted student work value
            </div>
        </div>

        <div class="card" style="padding: 1.25rem; border-left: 4px solid var(--danger-color);">
            <span style="font-size: 0.85rem; color: var(--gray-600); font-weight: 500;">Cancelled / Disabled</span>
            <div style="font-size: 1.75rem; font-weight: 700; color: var(--danger-color); margin-top: 0.25rem;">
                <?= (int)$stats['cancelled_tasks'] ?>
            </div>
            <div style="font-size: 0.75rem; color: var(--gray-500); margin-top: 0.25rem;">
                Moderated or withdrawn
            </div>
        </div>
    </div>

    <!-- Search & Filter Controls -->
    <div class="card" style="margin-bottom: 1.5rem;">
        <div class="card-body" style="padding: 1.25rem;">
            <form action="<?= BASE_URL ?>/admin/tasks.php" method="GET">
                <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(200px, 1fr)); gap: 1rem; align-items: flex-end;">
                    <div class="form-group" style="margin-bottom: 0;">
                        <label for="search" style="font-size: 0.85rem;">Search Title, Poster or Email</label>
                        <input 
                            type="text" 
                            id="search" 
                            name="search" 
                            class="form-control" 
                            placeholder="e.g. Python, Rafi, Slides..."
                            value="<?= e($search) ?>"
                        >
                    </div>

                    <div class="form-group" style="margin-bottom: 0;">
                        <label for="category_id" style="font-size: 0.85rem;">Category</label>
                        <select id="category_id" name="category_id" class="form-control">
                            <option value="">All Categories</option>
                            <?php foreach ($categories as $cat): ?>
                                <option value="<?= (int)$cat['category_id'] ?>" <?= $category_id === (int)$cat['category_id'] ? 'selected' : '' ?>>
                                    <?= e($cat['category_name']) ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>

                    <div class="form-group" style="margin-bottom: 0;">
                        <label for="status" style="font-size: 0.85rem;">Status</label>
                        <select id="status" name="status" class="form-control">
                            <option value="">All Statuses</option>
                            <option value="OPEN" <?= $status_filter === 'OPEN' ? 'selected' : '' ?>>OPEN</option>
                            <option value="IN_PROGRESS" <?= $status_filter === 'IN_PROGRESS' ? 'selected' : '' ?>>IN_PROGRESS</option>
                            <option value="COMPLETED" <?= $status_filter === 'COMPLETED' ? 'selected' : '' ?>>COMPLETED</option>
                            <option value="CANCELLED" <?= $status_filter === 'CANCELLED' ? 'selected' : '' ?>>CANCELLED</option>
                        </select>
                    </div>

                    <div class="form-group" style="margin-bottom: 0;">
                        <label for="sort" style="font-size: 0.85rem;">Sort By</label>
                        <select id="sort" name="sort" class="form-control">
                            <option value="newest" <?= $sort === 'newest' ? 'selected' : '' ?>>Newest First</option>
                            <option value="oldest" <?= $sort === 'oldest' ? 'selected' : '' ?>>Oldest First</option>
                            <option value="budget_high" <?= $sort === 'budget_high' ? 'selected' : '' ?>>Budget: High to Low</option>
                            <option value="budget_low" <?= $sort === 'budget_low' ? 'selected' : '' ?>>Budget: Low to High</option>
                            <option value="deadline_near" <?= $sort === 'deadline_near' ? 'selected' : '' ?>>Nearest Deadline</option>
                            <option value="title_az" <?= $sort === 'title_az' ? 'selected' : '' ?>>Title A-Z</option>
                        </select>
                    </div>
                </div>

                <div style="margin-top: 1rem; display: flex; gap: 0.75rem; align-items: center;">
                    <button type="submit" class="btn btn-primary" style="font-size: 0.9rem; padding: 0.45rem 1.25rem;">
                        Filter Tasks
                    </button>
                    <a href="<?= BASE_URL ?>/admin/tasks.php" class="btn btn-secondary" style="font-size: 0.9rem; padding: 0.45rem 1.25rem;">
                        Reset
                    </a>
                    <span style="color: var(--gray-500); font-size: 0.85rem; margin-left: auto;">
                        Showing <strong><?= count($tasks) ?></strong> task<?= count($tasks) === 1 ? '' : 's' ?>
                    </span>
                </div>
            </form>
        </div>
    </div>

    <!-- Tasks Moderation Table -->
    <div class="card">
        <div class="card-body" style="padding: 0;">
            <div style="overflow-x: auto;">
                <table class="table" style="margin-bottom: 0;">
                    <thead>
                        <tr>
                            <th style="width: 50px;">#</th>
                            <th>Task Title</th>
                            <th>Category</th>
                            <th>Poster</th>
                            <th>Budget</th>
                            <th>Deadline</th>
                            <th style="text-align: center;">Applicants</th>
                            <th style="text-align: center;">Status</th>
                            <th style="text-align: right;">Moderation</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (empty($tasks)): ?>
                            <tr>
                                <td colspan="9" style="text-align: center; color: var(--gray-500); padding: 3rem;">
                                    No tasks match your moderation filter.
                                </td>
                            </tr>
                        <?php else: ?>
                            <?php foreach ($tasks as $t): ?>
                                <?php
                                $status_badge = 'badge-success';
                                if ($t['status'] === 'IN_PROGRESS') $status_badge = 'badge-warning';
                                if ($t['status'] === 'COMPLETED') $status_badge = 'badge-info';
                                if ($t['status'] === 'CANCELLED') $status_badge = 'badge-danger';
                                ?>
                                <tr>
                                    <td style="color: var(--gray-500);"><?= (int)$t['task_id'] ?></td>
                                    <td>
                                        <a href="<?= BASE_URL ?>/student/task-details.php?id=<?= (int)$t['task_id'] ?>" target="_blank" style="font-weight: 600; color: var(--gray-900); text-decoration: none;">
                                            <?= e($t['title']) ?>
                                        </a>
                                        <div style="font-size: 0.75rem; color: var(--gray-400);">
                                            Posted: <?= date('M d, Y', strtotime($t['created_at'])) ?>
                                        </div>
                                    </td>
                                    <td>
                                        <span class="badge" style="background-color: var(--gray-100); color: var(--gray-700);">
                                            <?= e($t['category_name']) ?>
                                        </span>
                                    </td>
                                    <td>
                                        <strong style="color: var(--gray-800); font-size: 0.9rem;"><?= e($t['poster_name']) ?></strong>
                                        <div style="font-size: 0.75rem; color: var(--gray-500);"><?= e($t['poster_email']) ?></div>
                                    </td>
                                    <td style="font-weight: 600; color: var(--success-color);">
                                        $<?= number_format((float)$t['budget'], 2) ?>
                                    </td>
                                    <td style="font-size: 0.85rem; color: var(--gray-600);">
                                        <?= date('M d, Y', strtotime($t['deadline'])) ?>
                                    </td>
                                    <td style="text-align: center;">
                                        <span class="badge" style="background-color: var(--primary-light); color: var(--primary-color);">
                                            <?= (int)$t['app_count'] ?>
                                        </span>
                                    </td>
                                    <td style="text-align: center;">
                                        <span class="badge <?= $status_badge ?>">
                                            <?= e($t['status']) ?>
                                        </span>
                                    </td>
                                    <td style="text-align: right; white-space: nowrap;">
                                        <?php if ($t['status'] !== 'CANCELLED'): ?>
                                            <!-- Disable / Cancel Button -->
                                            <form action="<?= BASE_URL ?>/admin/tasks.php" method="POST" style="display: inline;" onsubmit="return confirm('Disable Task #<?= (int)$t['task_id'] ?> and mark as CANCELLED?');">
                                                <input type="hidden" name="action" value="cancel">
                                                <input type="hidden" name="task_id" value="<?= (int)$t['task_id'] ?>">
                                                <button type="submit" class="btn btn-secondary btn-sm" style="color: #b91c1c;">
                                                    Disable
                                                </button>
                                            </form>
                                        <?php else: ?>
                                            <!-- Reopen Button -->
                                            <form action="<?= BASE_URL ?>/admin/tasks.php" method="POST" style="display: inline;" onsubmit="return confirm('Re-open Task #<?= (int)$t['task_id'] ?> with OPEN status?');">
                                                <input type="hidden" name="action" value="reopen">
                                                <input type="hidden" name="task_id" value="<?= (int)$t['task_id'] ?>">
                                                <button type="submit" class="btn btn-secondary btn-sm" style="color: var(--success-color);">
                                                    Reopen
                                                </button>
                                            </form>
                                        <?php endif; ?>

                                        <!-- Permanent Delete Button -->
                                        <form action="<?= BASE_URL ?>/admin/tasks.php" method="POST" style="display: inline; margin-left: 0.25rem;" onsubmit="return confirm('Permanently delete Task #<?= (int)$t['task_id'] ?> and all related applicant records? This action cannot be undone.');">
                                            <input type="hidden" name="action" value="delete">
                                            <input type="hidden" name="task_id" value="<?= (int)$t['task_id'] ?>">
                                            <button type="submit" class="btn btn-sm" style="background-color: var(--danger-color); color: #fff;">
                                                Delete
                                            </button>
                                        </form>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</div>

<?php
require_once __DIR__ . '/../includes/footer.php';
?>
