<?php
/**
 * CampusTask - Admin Category Management
 * 
 * Course: CSE 2291 - Database Management System (DBMS)
 * Role: Administration (Person A - Feature A8)
 * Demonstrates:
 *  - Strict RBAC: Admin only
 *  - CRUD operations on categories table
 *  - Referential integrity check before deleting in-use categories
 *  - Duplicate category name prevention with friendly user feedback
 */

$page_title = 'Category Management';

require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/auth.php';

// Strict backend check: Only ADMIN can access
require_admin();

$errors = [];
$edit_id = (int)($_GET['edit_id'] ?? 0);
$edit_category = null;

// If editing, fetch current category
if ($edit_id > 0) {
    $edit_stmt = $pdo->prepare('SELECT * FROM categories WHERE category_id = :id LIMIT 1');
    $edit_stmt->execute([':id' => $edit_id]);
    $edit_category = $edit_stmt->fetch();
    if (!$edit_category) {
        set_flash('error', 'Category not found for editing.');
        redirect('admin/categories.php');
    }
}

// Handle POST actions (Add, Edit, Delete)
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';

    // ACTION: ADD CATEGORY
    if ($action === 'add') {
        $category_name = trim($_POST['category_name'] ?? '');
        $description = trim($_POST['description'] ?? '');

        if ($category_name === '') {
            $errors[] = 'Category name is required.';
        } elseif (mb_strlen($category_name) > 100) {
            $errors[] = 'Category name cannot exceed 100 characters.';
        }

        if (empty($errors)) {
            // Check for duplicate name
            $dup_check = $pdo->prepare('SELECT category_id FROM categories WHERE LOWER(category_name) = LOWER(:name) LIMIT 1');
            $dup_check->execute([':name' => $category_name]);
            if ($dup_check->fetch()) {
                $errors[] = "A category named '{$category_name}' already exists.";
            } else {
                try {
                    $insert_stmt = $pdo->prepare('INSERT INTO categories (category_name, description, created_at) VALUES (:name, :desc, NOW())');
                    $insert_stmt->execute([
                        ':name' => $category_name,
                        ':desc' => $description ?: null
                    ]);

                    set_flash('success', "Category '{$category_name}' created successfully!");
                    redirect('admin/categories.php');
                } catch (PDOException $e) {
                    $errors[] = 'Database error creating category: ' . $e->getMessage();
                }
            }
        }
    }

    // ACTION: UPDATE CATEGORY
    elseif ($action === 'update') {
        $category_id = (int)($_POST['category_id'] ?? 0);
        $category_name = trim($_POST['category_name'] ?? '');
        $description = trim($_POST['description'] ?? '');

        if ($category_id <= 0) {
            $errors[] = 'Invalid category ID specified.';
        }

        if ($category_name === '') {
            $errors[] = 'Category name is required.';
        } elseif (mb_strlen($category_name) > 100) {
            $errors[] = 'Category name cannot exceed 100 characters.';
        }

        if (empty($errors)) {
            // Check for duplicate name excluding itself
            $dup_check = $pdo->prepare('SELECT category_id FROM categories WHERE LOWER(category_name) = LOWER(:name) AND category_id != :id LIMIT 1');
            $dup_check->execute([':name' => $category_name, ':id' => $category_id]);
            if ($dup_check->fetch()) {
                $errors[] = "Another category named '{$category_name}' already exists.";
            } else {
                try {
                    $update_stmt = $pdo->prepare('UPDATE categories SET category_name = :name, description = :desc WHERE category_id = :id');
                    $update_stmt->execute([
                        ':name' => $category_name,
                        ':desc' => $description ?: null,
                        ':id'   => $category_id
                    ]);

                    set_flash('success', "Category '{$category_name}' updated successfully!");
                    redirect('admin/categories.php');
                } catch (PDOException $e) {
                    $errors[] = 'Database error updating category: ' . $e->getMessage();
                }
            }
        }
    }

    // ACTION: DELETE CATEGORY
    elseif ($action === 'delete') {
        $category_id = (int)($_POST['category_id'] ?? 0);

        if ($category_id <= 0) {
            set_flash('error', 'Invalid category ID.');
            redirect('admin/categories.php');
        }

        // Safety check: is this category used by any tasks?
        $use_stmt = $pdo->prepare('SELECT COUNT(*) FROM tasks WHERE category_id = :id');
        $use_stmt->execute([':id' => $category_id]);
        $tasks_using = (int)$use_stmt->fetchColumn();

        if ($tasks_using > 0) {
            set_flash('error', "Cannot delete category: it is currently referenced by {$tasks_using} task(s). Please reassign or remove those tasks first.");
            redirect('admin/categories.php');
        }

        try {
            $del_stmt = $pdo->prepare('DELETE FROM categories WHERE category_id = :id');
            $del_stmt->execute([':id' => $category_id]);

            set_flash('success', 'Category deleted successfully.');
            redirect('admin/categories.php');
        } catch (PDOException $e) {
            set_flash('error', 'Database error deleting category: ' . $e->getMessage());
            redirect('admin/categories.php');
        }
    }
}

// Fetch all categories with real-time task counts
$categories_stmt = $pdo->query('
    SELECT 
        c.category_id,
        c.category_name,
        c.description,
        c.created_at,
        COUNT(t.task_id) AS task_count
    FROM categories c
    LEFT JOIN tasks t ON c.category_id = t.category_id
    GROUP BY c.category_id
    ORDER BY c.category_name ASC
');
$categories = $categories_stmt->fetchAll();

require_once __DIR__ . '/../includes/header.php';
?>

<div style="max-width: 1100px; margin: 2rem auto;">
    <!-- Page Header -->
    <div style="display: flex; justify-content: space-between; align-items: center; flex-wrap: wrap; gap: 1rem; margin-bottom: 1.5rem;">
        <div>
            <h1 style="font-size: 1.85rem; font-weight: 700; color: var(--gray-900); margin-bottom: 0.25rem;">
                Category Management
            </h1>
            <p style="color: var(--gray-600); font-size: 0.95rem;">
                Create, update, and manage task categories across the campus marketplace.
            </p>
        </div>
        <a href="<?= BASE_URL ?>/admin/dashboard.php" class="btn btn-secondary">
            &larr; Admin Dashboard
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

    <div style="display: grid; grid-template-columns: 1fr 2fr; gap: 2rem; align-items: start;">
        <!-- Left Column: Add / Edit Category Form -->
        <div class="card">
            <div class="card-header">
                <h3 class="card-title">
                    <?= $edit_category ? 'Edit Category' : 'Add New Category' ?>
                </h3>
            </div>
            <div class="card-body">
                <form action="<?= BASE_URL ?>/admin/categories.php" method="POST">
                    <input type="hidden" name="action" value="<?= $edit_category ? 'update' : 'add' ?>">
                    <?php if ($edit_category): ?>
                        <input type="hidden" name="category_id" value="<?= (int)$edit_category['category_id'] ?>">
                    <?php endif; ?>

                    <div class="form-group">
                        <label for="category_name">Category Name <span style="color: var(--danger-color);">*</span></label>
                        <input 
                            type="text" 
                            id="category_name" 
                            name="category_name" 
                            class="form-control" 
                            placeholder="e.g. Data Science"
                            value="<?= e($edit_category ? $edit_category['category_name'] : ($_POST['category_name'] ?? '')) ?>" 
                            required
                        >
                    </div>

                    <div class="form-group">
                        <label for="description">Description</label>
                        <textarea 
                            id="description" 
                            name="description" 
                            rows="3" 
                            class="form-control" 
                            placeholder="Briefly describe what tasks fit into this category..."
                        ><?= e($edit_category ? $edit_category['description'] : ($_POST['description'] ?? '')) ?></textarea>
                    </div>

                    <div style="display: flex; gap: 0.75rem; align-items: center; margin-top: 1rem;">
                        <button type="submit" class="btn btn-primary">
                            <?= $edit_category ? 'Save Changes' : 'Create Category' ?>
                        </button>
                        <?php if ($edit_category): ?>
                            <a href="<?= BASE_URL ?>/admin/categories.php" class="btn btn-secondary">
                                Cancel
                            </a>
                        <?php endif; ?>
                    </div>
                </form>
            </div>
        </div>

        <!-- Right Column: Categories List Table -->
        <div class="card">
            <div class="card-header" style="display: flex; justify-content: space-between; align-items: center;">
                <h3 class="card-title">All Categories (<?= count($categories) ?>)</h3>
            </div>
            <div class="card-body" style="padding: 0;">
                <div style="overflow-x: auto;">
                    <table class="table" style="margin-bottom: 0;">
                        <thead>
                            <tr>
                                <th style="width: 50px;">#</th>
                                <th>Category Name</th>
                                <th>Description</th>
                                <th style="text-align: center;">Tasks</th>
                                <th style="text-align: right;">Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if (empty($categories)): ?>
                                <tr>
                                    <td colspan="5" style="text-align: center; color: var(--gray-500); padding: 2rem;">
                                        No categories found. Create one using the form on the left.
                                    </td>
                                </tr>
                            <?php else: ?>
                                <?php foreach ($categories as $cat): ?>
                                    <tr>
                                        <td style="color: var(--gray-500);"><?= (int)$cat['category_id'] ?></td>
                                        <td>
                                            <strong style="color: var(--gray-900);"><?= e($cat['category_name']) ?></strong>
                                        </td>
                                        <td style="color: var(--gray-600); font-size: 0.85rem; max-width: 260px;">
                                            <?= !empty($cat['description']) ? e($cat['description']) : '<span style="color: var(--gray-400);">No description</span>' ?>
                                        </td>
                                        <td style="text-align: center;">
                                            <span class="badge" style="background-color: var(--gray-100); color: var(--gray-700);">
                                                <?= (int)$cat['task_count'] ?>
                                            </span>
                                        </td>
                                        <td style="text-align: right; white-space: nowrap;">
                                            <a href="<?= BASE_URL ?>/admin/categories.php?edit_id=<?= (int)$cat['category_id'] ?>" class="btn btn-secondary btn-sm" style="margin-right: 0.25rem;">
                                                Edit
                                            </a>
                                            <form action="<?= BASE_URL ?>/admin/categories.php" method="POST" style="display: inline;" onsubmit="return confirm('Are you sure you want to delete category \'<?= e($cat['category_name']) ?>\'?');">
                                                <input type="hidden" name="action" value="delete">
                                                <input type="hidden" name="category_id" value="<?= (int)$cat['category_id'] ?>">
                                                <button type="submit" class="btn btn-sm" style="background-color: var(--danger-color); color: #fff;" <?= (int)$cat['task_count'] > 0 ? 'title="Used by active tasks"' : '' ?>>
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
</div>

<?php
require_once __DIR__ . '/../includes/footer.php';
?>
