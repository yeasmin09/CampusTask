<?php
/**
 * CampusTask - Admin Skill Management
 * 
 * Course: CSE 2291 - Database Management System (DBMS)
 * Role: Administration (Person A - Feature A8)
 * Demonstrates:
 *  - Strict RBAC: Admin only
 *  - CRUD operations on skills table
 *  - Referential integrity check before deleting in-use skills in task_skills
 *  - Duplicate skill name prevention with friendly user feedback
 */

$page_title = 'Skill Management';

require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/auth.php';

// Strict backend check: Only ADMIN can access
require_admin();

$errors = [];
$edit_id = (int)($_GET['edit_id'] ?? 0);
$edit_skill = null;

// If editing, fetch current skill
if ($edit_id > 0) {
    $edit_stmt = $pdo->prepare('SELECT * FROM skills WHERE skill_id = :id LIMIT 1');
    $edit_stmt->execute([':id' => $edit_id]);
    $edit_skill = $edit_stmt->fetch();
    if (!$edit_skill) {
        set_flash('error', 'Skill not found for editing.');
        redirect('admin/skills.php');
    }
}

// Handle POST actions (Add, Edit, Delete)
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';

    // ACTION: ADD SKILL
    if ($action === 'add') {
        $skill_name = trim($_POST['skill_name'] ?? '');
        $description = trim($_POST['description'] ?? '');

        if ($skill_name === '') {
            $errors[] = 'Skill name is required.';
        } elseif (mb_strlen($skill_name) > 100) {
            $errors[] = 'Skill name cannot exceed 100 characters.';
        }

        if (empty($errors)) {
            // Check for duplicate name
            $dup_check = $pdo->prepare('SELECT skill_id FROM skills WHERE LOWER(skill_name) = LOWER(:name) LIMIT 1');
            $dup_check->execute([':name' => $skill_name]);
            if ($dup_check->fetch()) {
                $errors[] = "A skill named '{$skill_name}' already exists.";
            } else {
                try {
                    $insert_stmt = $pdo->prepare('INSERT INTO skills (skill_name, description, created_at) VALUES (:name, :desc, NOW())');
                    $insert_stmt->execute([
                        ':name' => $skill_name,
                        ':desc' => $description ?: null
                    ]);

                    set_flash('success', "Skill '{$skill_name}' created successfully!");
                    redirect('admin/skills.php');
                } catch (PDOException $e) {
                    $errors[] = 'Database error creating skill: ' . $e->getMessage();
                }
            }
        }
    }

    // ACTION: UPDATE SKILL
    elseif ($action === 'update') {
        $skill_id = (int)($_POST['skill_id'] ?? 0);
        $skill_name = trim($_POST['skill_name'] ?? '');
        $description = trim($_POST['description'] ?? '');

        if ($skill_id <= 0) {
            $errors[] = 'Invalid skill ID specified.';
        }

        if ($skill_name === '') {
            $errors[] = 'Skill name is required.';
        } elseif (mb_strlen($skill_name) > 100) {
            $errors[] = 'Skill name cannot exceed 100 characters.';
        }

        if (empty($errors)) {
            // Check duplicate name excluding itself
            $dup_check = $pdo->prepare('SELECT skill_id FROM skills WHERE LOWER(skill_name) = LOWER(:name) AND skill_id != :id LIMIT 1');
            $dup_check->execute([':name' => $skill_name, ':id' => $skill_id]);
            if ($dup_check->fetch()) {
                $errors[] = "Another skill named '{$skill_name}' already exists.";
            } else {
                try {
                    $update_stmt = $pdo->prepare('UPDATE skills SET skill_name = :name, description = :desc WHERE skill_id = :id');
                    $update_stmt->execute([
                        ':name' => $skill_name,
                        ':desc' => $description ?: null,
                        ':id'   => $skill_id
                    ]);

                    set_flash('success', "Skill '{$skill_name}' updated successfully!");
                    redirect('admin/skills.php');
                } catch (PDOException $e) {
                    $errors[] = 'Database error updating skill: ' . $e->getMessage();
                }
            }
        }
    }

    // ACTION: DELETE SKILL
    elseif ($action === 'delete') {
        $skill_id = (int)($_POST['skill_id'] ?? 0);

        if ($skill_id <= 0) {
            set_flash('error', 'Invalid skill ID.');
            redirect('admin/skills.php');
        }

        // Safety check: is this skill tagged on any tasks in task_skills?
        $use_stmt = $pdo->prepare('SELECT COUNT(*) FROM task_skills WHERE skill_id = :id');
        $use_stmt->execute([':id' => $skill_id]);
        $tasks_tagged = (int)$use_stmt->fetchColumn();

        if ($tasks_tagged > 0) {
            set_flash('error', "Cannot delete skill: it is currently tagged on {$tasks_tagged} task(s). Remove the skill tag from those tasks first.");
            redirect('admin/skills.php');
        }

        try {
            $del_stmt = $pdo->prepare('DELETE FROM skills WHERE skill_id = :id');
            $del_stmt->execute([':id' => $skill_id]);

            set_flash('success', 'Skill deleted successfully.');
            redirect('admin/skills.php');
        } catch (PDOException $e) {
            set_flash('error', 'Database error deleting skill: ' . $e->getMessage());
            redirect('admin/skills.php');
        }
    }
}

// Fetch all skills with real-time usage counts from task_skills junction table
$skills_stmt = $pdo->query('
    SELECT 
        s.skill_id,
        s.skill_name,
        s.description,
        s.created_at,
        COUNT(ts.task_id) AS task_count
    FROM skills s
    LEFT JOIN task_skills ts ON s.skill_id = ts.skill_id
    GROUP BY s.skill_id
    ORDER BY s.skill_name ASC
');
$skills = $skills_stmt->fetchAll();

require_once __DIR__ . '/../includes/header.php';
?>

<div style="max-width: 1100px; margin: 2rem auto;">
    <!-- Page Header -->
    <div style="display: flex; justify-content: space-between; align-items: center; flex-wrap: wrap; gap: 1rem; margin-bottom: 1.5rem;">
        <div>
            <h1 style="font-size: 1.85rem; font-weight: 700; color: var(--gray-900); margin-bottom: 0.25rem;">
                Skill Management
            </h1>
            <p style="color: var(--gray-600); font-size: 0.95rem;">
                Manage technical and academic skills tagged on marketplace tasks (Many-to-Many).
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
        <!-- Left Column: Add / Edit Skill Form -->
        <div class="card">
            <div class="card-header">
                <h3 class="card-title">
                    <?= $edit_skill ? 'Edit Skill' : 'Add New Skill' ?>
                </h3>
            </div>
            <div class="card-body">
                <form action="<?= BASE_URL ?>/admin/skills.php" method="POST">
                    <input type="hidden" name="action" value="<?= $edit_skill ? 'update' : 'add' ?>">
                    <?php if ($edit_skill): ?>
                        <input type="hidden" name="skill_id" value="<?= (int)$edit_skill['skill_id'] ?>">
                    <?php endif; ?>

                    <div class="form-group">
                        <label for="skill_name">Skill Name <span style="color: var(--danger-color);">*</span></label>
                        <input 
                            type="text" 
                            id="skill_name" 
                            name="skill_name" 
                            class="form-control" 
                            placeholder="e.g. Flutter, React, SPSS"
                            value="<?= e($edit_skill ? $edit_skill['skill_name'] : ($_POST['skill_name'] ?? '')) ?>" 
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
                            placeholder="Brief description of the skill competency..."
                        ><?= e($edit_skill ? $edit_skill['description'] : ($_POST['description'] ?? '')) ?></textarea>
                    </div>

                    <div style="display: flex; gap: 0.75rem; align-items: center; margin-top: 1rem;">
                        <button type="submit" class="btn btn-primary">
                            <?= $edit_skill ? 'Save Changes' : 'Create Skill' ?>
                        </button>
                        <?php if ($edit_skill): ?>
                            <a href="<?= BASE_URL ?>/admin/skills.php" class="btn btn-secondary">
                                Cancel
                            </a>
                        <?php endif; ?>
                    </div>
                </form>
            </div>
        </div>

        <!-- Right Column: Skills List Table -->
        <div class="card">
            <div class="card-header" style="display: flex; justify-content: space-between; align-items: center;">
                <h3 class="card-title">All Skills (<?= count($skills) ?>)</h3>
            </div>
            <div class="card-body" style="padding: 0;">
                <div style="overflow-x: auto;">
                    <table class="table" style="margin-bottom: 0;">
                        <thead>
                            <tr>
                                <th style="width: 50px;">#</th>
                                <th>Skill Name</th>
                                <th>Description</th>
                                <th style="text-align: center;">Tasks Tagged</th>
                                <th style="text-align: right;">Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if (empty($skills)): ?>
                                <tr>
                                    <td colspan="5" style="text-align: center; color: var(--gray-500); padding: 2rem;">
                                        No skills found. Create one using the form on the left.
                                    </td>
                                </tr>
                            <?php else: ?>
                                <?php foreach ($skills as $sk): ?>
                                    <tr>
                                        <td style="color: var(--gray-500);"><?= (int)$sk['skill_id'] ?></td>
                                        <td>
                                            <strong style="color: var(--gray-900);"><?= e($sk['skill_name']) ?></strong>
                                        </td>
                                        <td style="color: var(--gray-600); font-size: 0.85rem; max-width: 260px;">
                                            <?= !empty($sk['description']) ? e($sk['description']) : '<span style="color: var(--gray-400);">No description</span>' ?>
                                        </td>
                                        <td style="text-align: center;">
                                            <span class="badge" style="background-color: var(--primary-light); color: var(--primary-color);">
                                                <?= (int)$sk['task_count'] ?>
                                            </span>
                                        </td>
                                        <td style="text-align: right; white-space: nowrap;">
                                            <a href="<?= BASE_URL ?>/admin/skills.php?edit_id=<?= (int)$sk['skill_id'] ?>" class="btn btn-secondary btn-sm" style="margin-right: 0.25rem;">
                                                Edit
                                            </a>
                                            <form action="<?= BASE_URL ?>/admin/skills.php" method="POST" style="display: inline;" onsubmit="return confirm('Are you sure you want to delete skill \'<?= e($sk['skill_name']) ?>\'?');">
                                                <input type="hidden" name="action" value="delete">
                                                <input type="hidden" name="skill_id" value="<?= (int)$sk['skill_id'] ?>">
                                                <button type="submit" class="btn btn-sm" style="background-color: var(--danger-color); color: #fff;" <?= (int)$sk['task_count'] > 0 ? 'title="Tagged on active tasks"' : '' ?>>
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
