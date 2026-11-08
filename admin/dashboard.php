<?php
/**
 * CampusTask - Admin Dashboard
 * 
 * Course: CSE 2291 - Database Management System (DBMS)
 * Role: Administration & Analytics (Person B)
 */

$page_title = 'Admin Dashboard';

require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/auth.php';

// Strict backend check: Only ADMIN can access
require_admin();

// 1. Live System-Wide Statistics
// Total Registered Students
$stmt = $pdo->query("SELECT COUNT(*) FROM users WHERE role = 'STUDENT'");
$total_students = (int)$stmt->fetchColumn();

// Total Tasks
$stmt = $pdo->query("SELECT COUNT(*) FROM tasks");
$total_tasks = (int)$stmt->fetchColumn();

// Active Tasks (OPEN or IN_PROGRESS)
$stmt = $pdo->query("SELECT COUNT(*) FROM tasks WHERE status IN ('OPEN', 'IN_PROGRESS')");
$active_tasks = (int)$stmt->fetchColumn();

// Completed Tasks
$stmt = $pdo->query("SELECT COUNT(*) FROM tasks WHERE status = 'COMPLETED'");
$completed_tasks = (int)$stmt->fetchColumn();

// Pending Applications across the system
$stmt = $pdo->query("SELECT COUNT(*) FROM applications WHERE status = 'PENDING'");
$pending_apps = (int)$stmt->fetchColumn();

// Total Reviews
$stmt = $pdo->query("SELECT COUNT(*) FROM reviews");
$total_reviews = (int)$stmt->fetchColumn();


// 2. Tasks Per Category Breakdown (GROUP BY and Aggregate Demonstration)
$cat_stmt = $pdo->query("
    SELECT 
        c.category_id,
        c.category_name,
        COUNT(t.task_id) AS task_count,
        COALESCE(SUM(t.budget), 0) AS total_budget
    FROM categories c
    LEFT JOIN tasks t ON c.category_id = t.category_id
    GROUP BY c.category_id, c.category_name
    ORDER BY task_count DESC
");
$category_stats = $cat_stmt->fetchAll();


// 3. Stored Procedure Lookup: GetTaskStatistics(p_task_id)
$proc_result = null;
$proc_task_id = (int)($_GET['lookup_task_id'] ?? 0);
$proc_error = null;

if ($proc_task_id > 0) {
    try {
        // Call the stored procedure required in Section 6 & 9.2
        $proc_stmt = $pdo->prepare("CALL GetTaskStatistics(:task_id)");
        $proc_stmt->execute([':task_id' => $proc_task_id]);
        $proc_result = $proc_stmt->fetch();
        $proc_stmt->closeCursor(); // Important in PDO to allow subsequent queries
    } catch (PDOException $e) {
        $proc_error = 'Stored Procedure `GetTaskStatistics` has not been imported yet or an error occurred: ' . $e->getMessage();
    }
}

// 4. Quick fetch recent 5 tasks for easy lookup
$recent_tasks_stmt = $pdo->query("
    SELECT t.task_id, t.title, t.status, t.budget, u.full_name AS poster_name, t.created_at
    FROM tasks t
    JOIN users u ON t.poster_id = u.user_id
    ORDER BY t.created_at DESC
    LIMIT 5
");
$recent_tasks = $recent_tasks_stmt->fetchAll();

require_once __DIR__ . '/../includes/header.php';
?>

<div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 1.5rem; flex-wrap: wrap; gap: 0.5rem;">
    <div>
        <h1>System Administration Dashboard</h1>
        <p style="color: var(--gray-600); margin: 0;">Comprehensive system overview, live analytics, and database statistics.</p>
    </div>
    <div style="display: flex; gap: 0.5rem;">
        <a href="<?= BASE_URL ?>/admin/users.php" class="btn btn-primary btn-sm">Manage Users</a>
        <a href="<?= BASE_URL ?>/admin/reviews.php" class="btn btn-secondary btn-sm">Moderate Reviews</a>
    </div>
</div>

<!-- 1. System Statistics Grid -->
<div class="stats-grid">
    <div class="stat-card">
        <div class="stat-card-title">Registered Students</div>
        <div class="stat-card-value"><?= $total_students ?></div>
        <div class="stat-card-subtext">Active student accounts</div>
    </div>
    <div class="stat-card">
        <div class="stat-card-title">Total Tasks</div>
        <div class="stat-card-value"><?= $total_tasks ?></div>
        <div class="stat-card-subtext"><?= $active_tasks ?> currently active</div>
    </div>
    <div class="stat-card">
        <div class="stat-card-title">Completed Tasks</div>
        <div class="stat-card-value"><?= $completed_tasks ?></div>
        <div class="stat-card-subtext">Collaborations finished</div>
    </div>
    <div class="stat-card">
        <div class="stat-card-title">Pending Applications</div>
        <div class="stat-card-value"><?= $pending_apps ?></div>
        <div class="stat-card-subtext">Awaiting poster decision</div>
    </div>
</div>

<div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(360px, 1fr)); gap: 1.5rem; margin-bottom: 2rem;">
    <!-- 2. Tasks Per Category (GROUP BY Demonstration) -->
    <div class="card">
        <div class="card-header">
            <h3 class="card-title">Tasks per Category (SQL GROUP BY)</h3>
        </div>
        <div class="card-body" style="padding: 0;">
            <?php if (empty($category_stats)): ?>
                <div class="empty-state" style="padding: 2rem 1rem;">
                    <div class="empty-state-title">No categories found</div>
                </div>
            <?php else: ?>
                <div class="table-responsive">
                    <table class="table table-striped table-hover">
                        <thead>
                            <tr>
                                <th>Category</th>
                                <th>Task Count</th>
                                <th style="text-align: right;">Total Posted Budget</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($category_stats as $cat): ?>
                                <tr>
                                    <td><strong><?= e($cat['category_name']) ?></strong></td>
                                    <td><?= (int)$cat['task_count'] ?> tasks</td>
                                    <td style="text-align: right;">$<?= number_format((float)$cat['total_budget'], 2) ?></td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            <?php endif; ?>
        </div>
    </div>

    <!-- 3. Stored Procedure Demonstration Tool: GetTaskStatistics() -->
    <div class="card">
        <div class="card-header">
            <h3 class="card-title">Task Stats Lookup (Stored Procedure)</h3>
        </div>
        <div class="card-body">
            <p style="font-size: 0.875rem; color: var(--gray-600); margin-bottom: 1rem;">
                Demonstrates execution of MySQL Stored Procedure <code>GetTaskStatistics(p_task_id)</code> to retrieve application breakdown.
            </p>

            <form action="<?= BASE_URL ?>/admin/dashboard.php" method="GET" style="display: flex; gap: 0.5rem; margin-bottom: 1rem;">
                <input 
                    type="number" 
                    name="lookup_task_id" 
                    class="form-control" 
                    placeholder="Enter Task ID (e.g. 1)" 
                    value="<?= ($proc_task_id > 0) ? e($proc_task_id) : '' ?>"
                    min="1" 
                    required
                >
                <button type="submit" class="btn btn-primary btn-sm" style="white-space: nowrap;">Execute Procedure</button>
            </form>

            <?php if ($proc_error): ?>
                <div class="flash-alert flash-error" style="font-size: 0.85rem; padding: 0.75rem 1rem;">
                    <?= e($proc_error) ?>
                </div>
            <?php elseif ($proc_result): ?>
                <div style="background-color: var(--gray-50); border: 1px solid var(--gray-200); border-radius: var(--border-radius); padding: 1rem;">
                    <div style="font-weight: 700; color: var(--gray-900); margin-bottom: 0.5rem;">
                        Procedure Result for Task #<?= e($proc_task_id) ?>:
                    </div>
                    <ul style="margin: 0; padding-left: 1.25rem; font-size: 0.9rem; color: var(--gray-800);">
                        <li><strong>Task Title:</strong> <?= e($proc_result['task_title'] ?? 'N/A') ?></li>
                        <li><strong>Task Status:</strong> <?= e($proc_result['task_status'] ?? 'N/A') ?></li>
                        <li><strong>Total Applications:</strong> <?= (int)($proc_result['total_applications'] ?? 0) ?></li>
                        <li><strong>Pending Applications:</strong> <?= (int)($proc_result['pending_applications'] ?? 0) ?></li>
                        <li><strong>Accepted Applications:</strong> <?= (int)($proc_result['accepted_applications'] ?? 0) ?></li>
                        <li><strong>Rejected Applications:</strong> <?= (int)($proc_result['rejected_applications'] ?? 0) ?></li>
                    </ul>
                </div>
            <?php elseif ($proc_task_id > 0): ?>
                <div class="flash-alert flash-info" style="font-size: 0.85rem;">
                    No task found with ID #<?= e($proc_task_id) ?>.
                </div>
            <?php endif; ?>
        </div>
    </div>
</div>

<!-- 4. Quick Overview of Recent Tasks in System -->
<div class="card">
    <div class="card-header">
        <h3 class="card-title">Recent System Tasks</h3>
        <a href="<?= BASE_URL ?>/admin/tasks.php" class="btn btn-outline btn-sm">View Moderation Page</a>
    </div>
    <div class="card-body" style="padding: 0;">
        <?php if (empty($recent_tasks)): ?>
            <div class="empty-state">
                <div class="empty-state-title">No tasks in database</div>
            </div>
        <?php else: ?>
            <div class="table-responsive">
                <table class="table table-striped table-hover">
                    <thead>
                        <tr>
                            <th>Task ID</th>
                            <th>Title</th>
                            <th>Posted By</th>
                            <th>Budget</th>
                            <th>Status</th>
                            <th>Created Date</th>
                            <th style="text-align: right;">Test Lookup</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($recent_tasks as $rt): ?>
                            <tr>
                                <td>#<?= e($rt['task_id']) ?></td>
                                <td><strong><?= e($rt['title']) ?></strong></td>
                                <td><?= e($rt['poster_name']) ?></td>
                                <td>$<?= number_format((float)$rt['budget'], 2) ?></td>
                                <td>
                                    <?php
                                    $st_badge = match ($rt['status']) {
                                        'OPEN'        => 'badge-open',
                                        'IN_PROGRESS' => 'badge-in-progress',
                                        'COMPLETED'   => 'badge-completed',
                                        default       => 'badge-cancelled',
                                    };
                                    ?>
                                    <span class="badge <?= $st_badge ?>"><?= e($rt['status']) ?></span>
                                </td>
                                <td><?= date('M d, Y', strtotime($rt['created_at'])) ?></td>
                                <td style="text-align: right;">
                                    <a href="<?= BASE_URL ?>/admin/dashboard.php?lookup_task_id=<?= e($rt['task_id']) ?>" class="btn btn-secondary btn-sm">
                                        Lookup Stats
                                    </a>
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
