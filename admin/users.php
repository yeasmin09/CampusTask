<?php
/**
 * CampusTask - Admin User Management
 * 
 * Course: CSE 2291 - Database Management System (DBMS)
 * Role: Administration (Person B)
 */

$page_title = 'User Management';

require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/auth.php';

// Strict backend check: Only ADMIN can access
require_admin();

// Handle Account Status Toggle (POST Request)
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $target_id = (int)($_POST['user_id'] ?? 0);
    $action = $_POST['action'] ?? '';

    if ($target_id <= 0 || !in_array($action, ['activate', 'deactivate'], true)) {
        set_flash('error', 'Invalid request parameters.');
        redirect('admin/users.php');
    }

    // Safety Rule 1: Admin cannot deactivate their own account
    if ($target_id === current_user_id() && $action === 'deactivate') {
        set_flash('error', 'You cannot deactivate your own administrative account.');
        redirect('admin/users.php');
    }

    // Query target user to check role
    $stmt = $pdo->prepare('SELECT user_id, full_name, role, account_status FROM users WHERE user_id = :id LIMIT 1');
    $stmt->execute([':id' => $target_id]);
    $target_user = $stmt->fetch();

    if (!$target_user) {
        set_flash('error', 'User not found.');
        redirect('admin/users.php');
    }

    // Safety Rule 2: Cannot deactivate another admin
    if ($target_user['role'] === 'ADMIN' && $action === 'deactivate') {
        set_flash('error', 'Administrators cannot deactivate other administrator accounts.');
        redirect('admin/users.php');
    }

    // Update account status
    $new_status = ($action === 'activate') ? 'ACTIVE' : 'DEACTIVATED';
    $update_stmt = $pdo->prepare('UPDATE users SET account_status = :status WHERE user_id = :id');
    $update_stmt->execute([
        ':status' => $new_status,
        ':id'     => $target_id
    ]);

    $status_label = ($new_status === 'ACTIVE') ? 'activated' : 'deactivated';
    set_flash('success', "User '{$target_user['full_name']}' has been successfully {$status_label}.");
    redirect('admin/users.php');
}

// Search and Filtering Parameters (GET Request)
$search = trim($_GET['search'] ?? '');
$role_filter = trim($_GET['role'] ?? '');
$status_filter = trim($_GET['status'] ?? '');
$sort = trim($_GET['sort'] ?? 'newest');

// Base Query with Task and Application Counts
$sql = "
    SELECT 
        u.user_id,
        u.full_name,
        u.email,
        u.role,
        u.account_status,
        u.created_at,
        (SELECT COUNT(*) FROM tasks t WHERE t.poster_id = u.user_id) AS task_count,
        (SELECT COUNT(*) FROM applications a WHERE a.applicant_id = u.user_id) AS application_count
    FROM users u
    WHERE 1=1
";

$params = [];

// Apply Search Filter (Full Name or Email)
if ($search !== '') {
    $sql .= " AND (u.full_name LIKE :search OR u.email LIKE :search)";
    $params[':search'] = '%' . $search . '%';
}

// Apply Role Filter
if (in_array($role_filter, ['STUDENT', 'ADMIN'], true)) {
    $sql .= " AND u.role = :role";
    $params[':role'] = $role_filter;
}

// Apply Status Filter
if (in_array($status_filter, ['ACTIVE', 'DEACTIVATED'], true)) {
    $sql .= " AND u.account_status = :status";
    $params[':status'] = $status_filter;
}

// Whitelist-based Sorting to prevent SQL Injection
$sort_columns = [
    'newest'       => 'u.created_at DESC',
    'oldest'       => 'u.created_at ASC',
    'name_asc'     => 'u.full_name ASC',
    'name_desc'    => 'u.full_name DESC',
    'tasks'        => 'task_count DESC',
    'applications' => 'application_count DESC',
];

$order_by = $sort_columns[$sort] ?? 'u.created_at DESC';
$sql .= " ORDER BY " . $order_by;

// Execute query
$stmt = $pdo->prepare($sql);
$stmt->execute($params);
$users = $stmt->fetchAll();

// Include shared header layout
require_once __DIR__ . '/../includes/header.php';
?>

<div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 1.5rem; flex-wrap: wrap; gap: 0.5rem;">
    <div>
        <h1>User Management</h1>
        <p style="color: var(--gray-600); margin: 0;">View, search, filter, and manage student and administrator accounts.</p>
    </div>
</div>

<!-- Search, Filter & Sort Form -->
<form action="<?= BASE_URL ?>/admin/users.php" method="GET" class="filter-bar">
    <input 
        type="text" 
        name="search" 
        class="form-control" 
        placeholder="Search by name or email..." 
        value="<?= e($search) ?>"
    >

    <select name="role" class="form-control">
        <option value="">All Roles</option>
        <option value="STUDENT" <?= ($role_filter === 'STUDENT') ? 'selected' : '' ?>>Students</option>
        <option value="ADMIN" <?= ($role_filter === 'ADMIN') ? 'selected' : '' ?>>Admins</option>
    </select>

    <select name="status" class="form-control">
        <option value="">All Statuses</option>
        <option value="ACTIVE" <?= ($status_filter === 'ACTIVE') ? 'selected' : '' ?>>Active</option>
        <option value="DEACTIVATED" <?= ($status_filter === 'DEACTIVATED') ? 'selected' : '' ?>>Deactivated</option>
    </select>

    <select name="sort" class="form-control">
        <option value="newest" <?= ($sort === 'newest') ? 'selected' : '' ?>>Newest First</option>
        <option value="oldest" <?= ($sort === 'oldest') ? 'selected' : '' ?>>Oldest First</option>
        <option value="name_asc" <?= ($sort === 'name_asc') ? 'selected' : '' ?>>Name (A &rarr; Z)</option>
        <option value="name_desc" <?= ($sort === 'name_desc') ? 'selected' : '' ?>>Name (Z &rarr; A)</option>
        <option value="tasks" <?= ($sort === 'tasks') ? 'selected' : '' ?>>Most Tasks Posted</option>
        <option value="applications" <?= ($sort === 'applications') ? 'selected' : '' ?>>Most Applications</option>
    </select>

    <button type="submit" class="btn btn-primary btn-sm">Filter</button>
    <a href="<?= BASE_URL ?>/admin/users.php" class="btn btn-secondary btn-sm">Reset</a>
</form>

<!-- Users List Table -->
<div class="card">
    <div class="card-header">
        <h3 class="card-title">Registered Accounts (<?= count($users) ?>)</h3>
    </div>
    <div class="card-body" style="padding: 0;">
        <?php if (empty($users)): ?>
            <div class="empty-state">
                <div class="empty-state-title">No users found</div>
                <div class="empty-state-text">No accounts matched your search or filter criteria.</div>
            </div>
        <?php else: ?>
            <div class="table-responsive">
                <table class="table table-striped table-hover">
                    <thead>
                        <tr>
                            <th>User ID</th>
                            <th>Full Name</th>
                            <th>Email</th>
                            <th>Role</th>
                            <th>Status</th>
                            <th>Tasks Posted</th>
                            <th>Applications</th>
                            <th>Joined Date</th>
                            <th style="text-align: right;">Action</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($users as $u): ?>
                            <tr>
                                <td>#<?= e($u['user_id']) ?></td>
                                <td><strong><?= e($u['full_name']) ?></strong></td>
                                <td><?= e($u['email']) ?></td>
                                <td>
                                    <?php if ($u['role'] === 'ADMIN'): ?>
                                        <span class="badge admin-badge">Admin</span>
                                    <?php else: ?>
                                        <span class="badge student-badge">Student</span>
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <?php if ($u['account_status'] === 'ACTIVE'): ?>
                                        <span class="badge badge-active">Active</span>
                                    <?php else: ?>
                                        <span class="badge badge-deactivated">Deactivated</span>
                                    <?php endif; ?>
                                </td>
                                <td><?= (int)$u['task_count'] ?></td>
                                <td><?= (int)$u['application_count'] ?></td>
                                <td><?= date('M d, Y', strtotime($u['created_at'])) ?></td>
                                <td style="text-align: right;">
                                    <?php if ($u['user_id'] === current_user_id()): ?>
                                        <span style="font-size: 0.8rem; color: var(--gray-400);">(You)</span>
                                    <?php elseif ($u['role'] === 'ADMIN'): ?>
                                        <span style="font-size: 0.8rem; color: var(--gray-400);">Admin</span>
                                    <?php else: ?>
                                        <form action="<?= BASE_URL ?>/admin/users.php" method="POST" style="display: inline-block;">
                                            <input type="hidden" name="user_id" value="<?= e($u['user_id']) ?>">
                                            <?php if ($u['account_status'] === 'ACTIVE'): ?>
                                                <input type="hidden" name="action" value="deactivate">
                                                <button 
                                                    type="submit" 
                                                    class="btn btn-danger btn-sm"
                                                    onclick="return confirm('Are you sure you want to deactivate this student account?');"
                                                >
                                                    Deactivate
                                                </button>
                                            <?php else: ?>
                                                <input type="hidden" name="action" value="activate">
                                                <button 
                                                    type="submit" 
                                                    class="btn btn-success btn-sm"
                                                    onclick="return confirm('Activate this student account?');"
                                                >
                                                    Activate
                                                </button>
                                            <?php endif; ?>
                                        </form>
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
