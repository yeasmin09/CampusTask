<?php
/**
 * CampusTask - Task Marketplace List, Search, Filter & Sort
 * 
 * Course: CSE 2291 - Database Management System (DBMS)
 * Role: Task Marketplace (Person A - Features A5 & A6)
 * Demonstrates:
 *  - Multi-table SQL JOINs (tasks, users, categories, task_skills, applications)
 *  - Dynamic parameterized WHERE clause with PDO prepared statements
 *  - Whitelisted ORDER BY sorting
 *  - Status filtering tabs and empty states
 */

$page_title = 'Browse Tasks';

// Load configuration and database connection
$config_path = __DIR__ . '/../config/database.php';
if (file_exists($config_path)) {
    require_once $config_path;
} else {
    require_once __DIR__ . '/../config/database.example.php';
}

require_once __DIR__ . '/../includes/auth.php';

// 1. Fetch Categories and Skills for Filter Controls
$categories = $pdo->query('SELECT category_id, category_name FROM categories ORDER BY category_name ASC')->fetchAll();
$all_skills = $pdo->query('SELECT skill_id, skill_name FROM skills ORDER BY skill_name ASC')->fetchAll();

// 2. Parse Search, Filter, and Sort Query Parameters
$search_query = trim($_GET['q'] ?? '');
$category_filter = (int)($_GET['category'] ?? 0);
$skill_filter = (int)($_GET['skill'] ?? 0);
$min_budget = trim($_GET['min_budget'] ?? '');
$max_budget = trim($_GET['max_budget'] ?? '');
$status_filter = strtolower(trim($_GET['status'] ?? 'all'));
$sort_by = trim($_GET['sort'] ?? 'newest');

// Whitelist array for ORDER BY (Security rule against SQL Injection)
$sort_map = [
    'newest'        => 't.created_at DESC',
    'oldest'        => 't.created_at ASC',
    'budget_low'    => 't.budget ASC',
    'budget_high'   => 't.budget DESC',
    'deadline_near' => 't.deadline ASC',
    'title_az'      => 't.title ASC',
    'title_za'      => 't.title DESC'
];
$order_sql = $sort_map[$sort_by] ?? 't.created_at DESC';

// 3. Build Dynamic WHERE Conditions and Parameters
$where_clauses = [];
$params = [];

// Keyword search across title and description
if ($search_query !== '') {
    $where_clauses[] = '(t.title LIKE :search_title OR t.description LIKE :search_desc)';
    $params[':search_title'] = '%' . $search_query . '%';
    $params[':search_desc']  = '%' . $search_query . '%';
}

// Category filter
if ($category_filter > 0) {
    $where_clauses[] = 't.category_id = :category_id';
    $params[':category_id'] = $category_filter;
}

// Skill filter using subquery
if ($skill_filter > 0) {
    $where_clauses[] = 'EXISTS (SELECT 1 FROM task_skills ts_filter WHERE ts_filter.task_id = t.task_id AND ts_filter.skill_id = :skill_id)';
    $params[':skill_id'] = $skill_filter;
}

// Budget range filters
if ($min_budget !== '' && is_numeric($min_budget)) {
    $where_clauses[] = 't.budget >= :min_budget';
    $params[':min_budget'] = (float)$min_budget;
}
if ($max_budget !== '' && is_numeric($max_budget)) {
    $where_clauses[] = 't.budget <= :max_budget';
    $params[':max_budget'] = (float)$max_budget;
}

// Status filter tabs: available (OPEN), assigned (IN_PROGRESS), completed (COMPLETED)
if ($status_filter === 'available' || $status_filter === 'open') {
    $where_clauses[] = 't.status = "OPEN"';
} elseif ($status_filter === 'assigned' || $status_filter === 'in_progress') {
    $where_clauses[] = 't.status = "IN_PROGRESS"';
} elseif ($status_filter === 'completed') {
    $where_clauses[] = 't.status = "COMPLETED"';
} elseif ($status_filter === 'cancelled') {
    $where_clauses[] = 't.status = "CANCELLED"';
}

$where_sql = !empty($where_clauses) ? 'WHERE ' . implode(' AND ', $where_clauses) : '';

// 4. Execute Main Query with Joins and GROUP_CONCAT for skills
$sql = "
    SELECT 
        t.task_id,
        t.poster_id,
        t.category_id,
        t.title,
        t.description,
        t.budget,
        t.deadline,
        t.status,
        t.created_at,
        u.full_name AS poster_name,
        c.category_name,
        COALESCE(app_counts.application_count, 0) AS application_count,
        GROUP_CONCAT(DISTINCT s.skill_name ORDER BY s.skill_name ASC SEPARATOR '||') AS skills_list
    FROM tasks t
    JOIN users u ON t.poster_id = u.user_id
    JOIN categories c ON t.category_id = c.category_id
    LEFT JOIN task_skills ts ON t.task_id = ts.task_id
    LEFT JOIN skills s ON ts.skill_id = s.skill_id
    LEFT JOIN (
        SELECT task_id, COUNT(*) AS application_count 
        FROM applications 
        GROUP BY task_id
    ) app_counts ON t.task_id = app_counts.task_id
    $where_sql
    GROUP BY t.task_id
    ORDER BY $order_sql
";

$stmt = $pdo->prepare($sql);
$stmt->execute($params);
$tasks = $stmt->fetchAll();

// 5. Query Counts for Status Tabs
$count_all = (int)$pdo->query('SELECT COUNT(*) FROM tasks')->fetchColumn();
$count_open = (int)$pdo->query('SELECT COUNT(*) FROM tasks WHERE status = "OPEN"')->fetchColumn();
$count_assigned = (int)$pdo->query('SELECT COUNT(*) FROM tasks WHERE status = "IN_PROGRESS"')->fetchColumn();
$count_completed = (int)$pdo->query('SELECT COUNT(*) FROM tasks WHERE status = "COMPLETED"')->fetchColumn();

// Include shared header layout
require_once __DIR__ . '/../includes/header.php';
?>

<style>
/* Corporate Palette Integration */
:root {
    --primary: #367C89 !important;
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

<div style="max-width: 1200px; margin: 2rem auto;">
    <!-- Page Header & Action -->
    <div style="display: flex; justify-content: space-between; align-items: center; flex-wrap: wrap; gap: 1rem; margin-bottom: 1.5rem;">
        <div>
            <h1 style="font-size: 1.85rem; font-weight: 700; color: var(--gray-900); margin-bottom: 0.25rem;">
                Task Marketplace
            </h1>
            <p style="color: var(--gray-600); font-size: 0.95rem;">
                Explore freelance tasks posted by university peers or offer your skills.
            </p>
        </div>
        <?php if (isset($_SESSION['user_id']) && ($_SESSION['role'] ?? '') === 'STUDENT'): ?>
            <a href="<?= BASE_URL ?>/student/post-task.php" class="btn btn-primary">
                + Post a Task
            </a>
        <?php endif; ?>
    </div>

    <!-- Status Filter Tabs -->
    <div style="display: flex; gap: 0.5rem; flex-wrap: wrap; margin-bottom: 1.5rem; border-bottom: 1px solid var(--gray-200); padding-bottom: 0.75rem;">
        <?php
        // Helper function to build status filter URL preserving other query params
        function make_status_url($status_val) {
            $params = $_GET;
            $params['status'] = $status_val;
            return '?' . http_build_query($params);
        }
        ?>
        <a href="<?= make_status_url('all') ?>" class="btn <?= $status_filter === 'all' ? 'btn-primary' : 'btn-secondary' ?>" style="font-size: 0.85rem; padding: 0.4rem 0.85rem;">
            All Tasks (<?= $count_all ?>)
        </a>
        <a href="<?= make_status_url('available') ?>" class="btn <?= ($status_filter === 'available' || $status_filter === 'open') ? 'btn-primary' : 'btn-secondary' ?>" style="font-size: 0.85rem; padding: 0.4rem 0.85rem;">
            Available / Open (<?= $count_open ?>)
        </a>
        <a href="<?= make_status_url('assigned') ?>" class="btn <?= ($status_filter === 'assigned' || $status_filter === 'in_progress') ? 'btn-primary' : 'btn-secondary' ?>" style="font-size: 0.85rem; padding: 0.4rem 0.85rem;">
            Assigned (<?= $count_assigned ?>)
        </a>
        <a href="<?= make_status_url('completed') ?>" class="btn <?= $status_filter === 'completed' ? 'btn-primary' : 'btn-secondary' ?>" style="font-size: 0.85rem; padding: 0.4rem 0.85rem;">
            Completed (<?= $count_completed ?>)
        </a>
    </div>

    <!-- Search & Filter Controls Card -->
    <div class="card" style="margin-bottom: 2rem;">
        <div class="card-body" style="padding: 1.25rem;">
            <form action="<?= BASE_URL ?>/student/tasks.php" method="GET">
                <input type="hidden" name="status" value="<?= e($status_filter) ?>">

                <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(200px, 1fr)); gap: 1rem; align-items: flex-end;">
                    <!-- Keyword Search -->
                    <div class="form-group" style="margin-bottom: 0;">
                        <label for="q" style="font-size: 0.85rem;">Search Title / Description</label>
                        <input 
                            type="text" 
                            id="q" 
                            name="q" 
                            class="form-control" 
                            placeholder="Keywords, e.g. Python, slides..."
                            value="<?= e($search_query) ?>"
                        >
                    </div>

                    <!-- Category Filter -->
                    <div class="form-group" style="margin-bottom: 0;">
                        <label for="category" style="font-size: 0.85rem;">Category</label>
                        <select id="category" name="category" class="form-control">
                            <option value="">All Categories</option>
                            <?php foreach ($categories as $cat): ?>
                                <option value="<?= (int)$cat['category_id'] ?>" <?= $category_filter === (int)$cat['category_id'] ? 'selected' : '' ?>>
                                    <?= e($cat['category_name']) ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>

                    <!-- Skill Filter -->
                    <div class="form-group" style="margin-bottom: 0;">
                        <label for="skill" style="font-size: 0.85rem;">Required Skill</label>
                        <select id="skill" name="skill" class="form-control">
                            <option value="">All Skills</option>
                            <?php foreach ($all_skills as $sk): ?>
                                <option value="<?= (int)$sk['skill_id'] ?>" <?= $skill_filter === (int)$sk['skill_id'] ? 'selected' : '' ?>>
                                    <?= e($sk['skill_name']) ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>

                    <!-- Min Budget -->
                    <div class="form-group" style="margin-bottom: 0;">
                        <label for="min_budget" style="font-size: 0.85rem;">Min Budget ($)</label>
                        <input 
                            type="number" 
                            id="min_budget" 
                            name="min_budget" 
                            class="form-control" 
                            step="0.01" 
                            min="0"
                            placeholder="0"
                            value="<?= e($min_budget) ?>"
                        >
                    </div>

                    <!-- Max Budget -->
                    <div class="form-group" style="margin-bottom: 0;">
                        <label for="max_budget" style="font-size: 0.85rem;">Max Budget ($)</label>
                        <input 
                            type="number" 
                            id="max_budget" 
                            name="max_budget" 
                            class="form-control" 
                            step="0.01" 
                            min="0"
                            placeholder="100"
                            value="<?= e($max_budget) ?>"
                        >
                    </div>

                    <!-- Sort Options -->
                    <div class="form-group" style="margin-bottom: 0;">
                        <label for="sort" style="font-size: 0.85rem;">Sort By</label>
                        <select id="sort" name="sort" class="form-control">
                            <option value="newest" <?= $sort_by === 'newest' ? 'selected' : '' ?>>Newest First</option>
                            <option value="oldest" <?= $sort_by === 'oldest' ? 'selected' : '' ?>>Oldest First</option>
                            <option value="budget_low" <?= $sort_by === 'budget_low' ? 'selected' : '' ?>>Budget: Low to High</option>
                            <option value="budget_high" <?= $sort_by === 'budget_high' ? 'selected' : '' ?>>Budget: High to Low</option>
                            <option value="deadline_near" <?= $sort_by === 'deadline_near' ? 'selected' : '' ?>>Nearest Deadline</option>
                            <option value="title_az" <?= $sort_by === 'title_az' ? 'selected' : '' ?>>Title: A to Z</option>
                            <option value="title_za" <?= $sort_by === 'title_za' ? 'selected' : '' ?>>Title: Z to A</option>
                        </select>
                    </div>
                </div>

                <div style="margin-top: 1rem; display: flex; gap: 0.75rem; align-items: center;">
                    <button type="submit" class="btn btn-primary" style="font-size: 0.9rem; padding: 0.5rem 1.25rem;">
                        Filter Tasks
                    </button>
                    <a href="<?= BASE_URL ?>/student/tasks.php" class="btn btn-secondary" style="font-size: 0.9rem; padding: 0.5rem 1.25rem;">
                        Reset Filters
                    </a>
                    <span style="color: var(--gray-500); font-size: 0.85rem; margin-left: auto;">
                        Found <strong><?= count($tasks) ?></strong> task<?= count($tasks) === 1 ? '' : 's' ?>
                    </span>
                </div>
            </form>
        </div>
    </div>

    <!-- Task Cards Grid -->
    <?php if (empty($tasks)): ?>
        <div class="card" style="text-align: center; padding: 3rem 1.5rem; background-color: var(--gray-50); border: 2px dashed var(--gray-300);">
            <div style="font-size: 3rem; margin-bottom: 0.75rem;">🔍</div>
            <h3 style="font-size: 1.35rem; font-weight: 600; color: var(--gray-800); margin-bottom: 0.5rem;">
                No tasks found
            </h3>
            <p style="color: var(--gray-600); max-width: 480px; margin: 0 auto 1.5rem auto; font-size: 0.95rem;">
                There are no tasks matching your selected filters and search keywords. Try adjusting your search query, budget range, or status tab.
            </p>
            <a href="<?= BASE_URL ?>/student/tasks.php" class="btn btn-primary">
                Clear All Filters
            </a>
        </div>
    <?php else: ?>
        <div style="display: grid; grid-template-columns: repeat(auto-fill, minmax(360px, 1fr)); gap: 1.5rem;">
            <?php foreach ($tasks as $task): ?>
                <?php
                // Status badge styling
                $status_class = 'badge-success';
                if ($task['status'] === 'IN_PROGRESS') {
                    $status_class = 'badge-warning';
                } elseif ($task['status'] === 'COMPLETED') {
                    $status_class = 'badge-info';
                } elseif ($task['status'] === 'CANCELLED') {
                    $status_class = 'badge-danger';
                }

                $skills_array = !empty($task['skills_list']) ? explode('||', $task['skills_list']) : [];
                ?>
                <div class="card" style="display: flex; flex-direction: column; justify-content: space-between; transition: transform 0.15s ease, box-shadow 0.15s ease;">
                    <div class="card-body">
                        <!-- Top Metadata: Category & Status -->
                        <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 0.75rem;">
                            <span class="badge" style="background-color: var(--gray-100); color: var(--gray-700); font-weight: 500;">
                                <?= e($task['category_name']) ?>
                            </span>
                            <span class="badge <?= $status_class ?>">
                                <?= e($task['status']) ?>
                            </span>
                        </div>

                        <!-- Title -->
                        <h3 style="font-size: 1.15rem; font-weight: 600; margin-bottom: 0.5rem; line-height: 1.4;">
                            <a href="<?= BASE_URL ?>/student/task-details.php?id=<?= (int)$task['task_id'] ?>" style="color: var(--gray-900); text-decoration: none;">
                                <?= e($task['title']) ?>
                            </a>
                        </h3>

                        <!-- Poster & Date -->
                        <p style="color: var(--gray-500); font-size: 0.85rem; margin-bottom: 0.75rem;">
                            Posted by <strong><?= e($task['poster_name']) ?></strong> &bull; <?= date('M d, Y', strtotime($task['created_at'])) ?>
                        </p>

                        <!-- Description Snippet -->
                        <p style="color: var(--gray-600); font-size: 0.9rem; line-height: 1.5; margin-bottom: 1rem; display: -webkit-box; -webkit-line-clamp: 3; -webkit-box-orient: vertical; overflow: hidden;">
                            <?= e($task['description']) ?>
                        </p>

                        <!-- Skills Badges -->
                        <?php if (!empty($skills_array)): ?>
                            <div style="display: flex; flex-wrap: wrap; gap: 0.35rem; margin-bottom: 1rem;">
                                <?php foreach ($skills_array as $sk_name): ?>
                                    <span style="font-size: 0.75rem; background-color: var(--primary-light); color: var(--primary-color); padding: 0.2rem 0.5rem; border-radius: var(--radius-sm); font-weight: 500;">
                                        <?= e($sk_name) ?>
                                    </span>
                                <?php endforeach; ?>
                            </div>
                        <?php endif; ?>
                    </div>

                    <!-- Footer: Budget, Deadline, Details Button -->
                    <div class="card-footer" style="background-color: var(--gray-50); border-top: 1px solid var(--gray-100); padding: 0.85rem 1.25rem; display: flex; justify-content: space-between; align-items: center;">
                        <div>
                            <div style="font-size: 1.15rem; font-weight: 700; color: var(--success-color);">
                                $<?= number_format((float)$task['budget'], 2) ?>
                            </div>
                            <div style="font-size: 0.75rem; color: var(--gray-500);">
                                Due: <?= date('M d, Y', strtotime($task['deadline'])) ?> &bull; <?= (int)$task['application_count'] ?> applicant<?= (int)$task['application_count'] === 1 ? '' : 's' ?>
                            </div>
                        </div>
                        <a href="<?= BASE_URL ?>/student/task-details.php?id=<?= (int)$task['task_id'] ?>" class="btn btn-primary-sm">
                            View Details
                        </a>
                    </div>
                </div>
            <?php endforeach; ?>
        </div>
    <?php endif; ?>
</div>

<?php
require_once __DIR__ . '/../includes/footer.php';
?>
