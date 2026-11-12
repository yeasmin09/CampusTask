<?php
/**
 * CampusTask - Edit & Delete Own Task (Update & Delete)
 * 
 * Course: CSE 2291 - Database Management System (DBMS)
 * Role: Task Management (Person A - Feature A4)
 * Rules:
 *  - Only the task owner (poster_id) can edit or delete
 *  - Only tasks with status 'OPEN' can be modified
 *  - Re-syncs Many-to-Many task_skills in a transaction
 */

$page_title = 'Edit Task';

require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/auth.php';

// Strict backend check: Only logged-in students
require_student();

$current_user_id = current_user_id();
$task_id = (int)($_GET['id'] ?? ($_POST['task_id'] ?? 0));

if ($task_id <= 0) {
    set_flash('error', 'Invalid task ID specified.');
    redirect('student/tasks.php');
}

// 1. Fetch task and verify ownership and OPEN status
$stmt = $pdo->prepare('SELECT * FROM tasks WHERE task_id = :id LIMIT 1');
$stmt->execute([':id' => $task_id]);
$task = $stmt->fetch();

if (!$task) {
    set_flash('error', 'Task not found.');
    redirect('student/tasks.php');
}

if ((int)$task['poster_id'] !== $current_user_id) {
    set_flash('error', 'Unauthorized. You can only edit your own tasks.');
    redirect('student/task-details.php?id=' . $task_id);
}

if ($task['status'] !== 'OPEN') {
    set_flash('error', 'Only tasks with OPEN status can be edited or deleted (Current Status: ' . $task['status'] . ').');
    redirect('student/task-details.php?id=' . $task_id);
}

// 2. Fetch categories and skills
$categories = $pdo->query('SELECT category_id, category_name FROM categories ORDER BY category_name ASC')->fetchAll();
$all_skills = $pdo->query('SELECT skill_id, skill_name FROM skills ORDER BY skill_name ASC')->fetchAll();

// Fetch currently associated skills for this task
$current_skills_stmt = $pdo->prepare('SELECT skill_id FROM task_skills WHERE task_id = :task_id');
$current_skills_stmt->execute([':task_id' => $task_id]);
$existing_skill_ids = $current_skills_stmt->fetchAll(PDO::FETCH_COLUMN);

$errors = [];
$title = $task['title'];
$category_id = (int)$task['category_id'];
$budget_raw = $task['budget'];
$deadline = $task['deadline'];
$description = $task['description'];
$selected_skills = $existing_skill_ids;

// 3. Process POST Actions (Update or Delete)
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? 'update';

    // ACTION: DELETE
    if ($action === 'delete') {
        try {
            $pdo->beginTransaction();

            // Deleting the task will cascade delete task_skills and applications via foreign key constraints
            $delete_stmt = $pdo->prepare('DELETE FROM tasks WHERE task_id = :id AND poster_id = :poster_id AND status = "OPEN"');
            $delete_stmt->execute([
                ':id'        => $task_id,
                ':poster_id' => $current_user_id
            ]);

            $pdo->commit();
            set_flash('success', 'Task deleted successfully.');
            redirect('student/dashboard.php');
        } catch (PDOException $e) {
            if ($pdo->inTransaction()) {
                $pdo->rollBack();
            }
            $errors[] = 'Failed to delete task: ' . $e->getMessage();
        }
    }

    // ACTION: UPDATE
    elseif ($action === 'update') {
        $title = trim($_POST['title'] ?? '');
        $category_id = (int)($_POST['category_id'] ?? 0);
        $budget_raw = trim($_POST['budget'] ?? '');
        $deadline = trim($_POST['deadline'] ?? '');
        $description = trim($_POST['description'] ?? '');

        $selected_skills = [];
        if (isset($_POST['skills'])) {
            if (is_array($_POST['skills'])) {
                $selected_skills = array_map('intval', $_POST['skills']);
            } else {
                $selected_skills = array_filter(array_map('intval', explode(',', (string)$_POST['skills'])));
            }
        }

        // Validation
        if ($title === '') {
            $errors[] = 'Task title is required.';
        } elseif (mb_strlen($title) < 5 || mb_strlen($title) > 200) {
            $errors[] = 'Task title must be between 5 and 200 characters.';
        }

        if ($category_id <= 0) {
            $errors[] = 'Please select a valid category.';
        }

        if ($budget_raw === '') {
            $errors[] = 'Budget is required.';
        } elseif (!is_numeric($budget_raw) || (float)$budget_raw < 0) {
            $errors[] = 'Budget must be a non-negative number.';
        }

        if ($deadline === '') {
            $errors[] = 'Deadline date is required.';
        }

        if ($description === '') {
            $errors[] = 'Task description is required.';
        } elseif (mb_strlen($description) < 15) {
            $errors[] = 'Please provide a detailed description (at least 15 characters).';
        }

        if (empty($errors)) {
            try {
                $pdo->beginTransaction();

                // 1. Update task core record
                $update_task_stmt = $pdo->prepare('
                    UPDATE tasks 
                    SET title = :title, 
                        category_id = :category_id, 
                        budget = :budget, 
                        deadline = :deadline, 
                        description = :description, 
                        updated_at = NOW() 
                    WHERE task_id = :id AND poster_id = :poster_id AND status = "OPEN"
                ');

                $update_task_stmt->execute([
                    ':title'       => $title,
                    ':category_id' => $category_id,
                    ':budget'      => (float)$budget_raw,
                    ':deadline'    => $deadline,
                    ':description' => $description,
                    ':id'          => $task_id,
                    ':poster_id'   => $current_user_id
                ]);

                // 2. Re-sync skills in Many-to-Many junction table
                $delete_skills_stmt = $pdo->prepare('DELETE FROM task_skills WHERE task_id = :task_id');
                $delete_skills_stmt->execute([':task_id' => $task_id]);

                if (!empty($selected_skills)) {
                    $insert_skill_stmt = $pdo->prepare('INSERT INTO task_skills (task_id, skill_id) VALUES (:task_id, :skill_id)');
                    foreach ($selected_skills as $skill_id) {
                        if ($skill_id > 0) {
                            $insert_skill_stmt->execute([
                                ':task_id'  => $task_id,
                                ':skill_id' => $skill_id
                            ]);
                        }
                    }
                }

                $pdo->commit();

                set_flash('success', 'Task updated successfully!');
                redirect('student/task-details.php?id=' . $task_id);

            } catch (PDOException $e) {
                if ($pdo->inTransaction()) {
                    $pdo->rollBack();
                }
                $errors[] = 'Database error while saving changes: ' . $e->getMessage();
            }
        }
    }
}

require_once __DIR__ . '/../includes/header.php';
?>

<div style="max-width: 800px; margin: 2rem auto;">
    <div style="margin-bottom: 1.5rem; display: flex; justify-content: space-between; align-items: center; flex-wrap: wrap; gap: 1rem;">
        <div>
            <h1 style="font-size: 1.75rem; font-weight: 700; color: var(--gray-900); margin-bottom: 0.25rem;">
                Edit Task
            </h1>
            <p style="color: var(--gray-600); font-size: 0.95rem;">
                Update task specifications, budget, deadline, or required skills.
            </p>
        </div>
        <div style="display: flex; gap: 0.5rem;">
            <a href="<?= BASE_URL ?>/student/task-details.php?id=<?= $task_id ?>" class="btn btn-secondary">
                View Task
            </a>
            <a href="<?= BASE_URL ?>/student/dashboard.php" class="btn btn-secondary">
                Dashboard
            </a>
        </div>
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

    <div class="card">
        <div class="card-body">
            <form action="<?= BASE_URL ?>/student/edit-task.php?id=<?= $task_id ?>" method="POST" novalidate>
                <input type="hidden" name="action" value="update">
                <input type="hidden" name="task_id" value="<?= $task_id ?>">

                <div class="form-group">
                    <label for="title">Task Title <span style="color: var(--danger-color);">*</span></label>
                    <input 
                        type="text" 
                        id="title" 
                        name="title" 
                        class="form-control" 
                        value="<?= e($title) ?>" 
                        required
                    >
                </div>

                <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 1.25rem;">
                    <div class="form-group">
                        <label for="category_id">Category <span style="color: var(--danger-color);">*</span></label>
                        <select id="category_id" name="category_id" class="form-control" required>
                            <?php foreach ($categories as $cat): ?>
                                <option value="<?= (int)$cat['category_id'] ?>" <?= (int)$category_id === (int)$cat['category_id'] ? 'selected' : '' ?>>
                                    <?= e($cat['category_name']) ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>

                    <div class="form-group">
                        <label for="budget">Budget (৳ / USD) <span style="color: var(--danger-color);">*</span></label>
                        <input 
                            type="number" 
                            id="budget" 
                            name="budget" 
                            class="form-control" 
                            step="0.01" 
                            min="0" 
                            value="<?= e((string)$budget_raw) ?>" 
                            required
                        >
                    </div>
                </div>

                <div class="form-group">
                    <label for="deadline">Target Deadline <span style="color: var(--danger-color);">*</span></label>
                    <input 
                        type="date" 
                        id="deadline" 
                        name="deadline" 
                        class="form-control" 
                        value="<?= e($deadline) ?>" 
                        required
                    >
                </div>

                <div class="form-group">
                    <label for="description">Detailed Description <span style="color: var(--danger-color);">*</span></label>
                    <textarea 
                        id="description" 
                        name="description" 
                        rows="5" 
                        class="form-control" 
                        required
                    ><?= e($description) ?></textarea>
                </div>

                <div class="form-group">
                    <label style="display: block; margin-bottom: 0.5rem;">
                        Required Skills (Many-to-Many Re-sync)
                    </label>
                    <div style="display: grid; grid-template-columns: repeat(auto-fill, minmax(180px, 1fr)); gap: 0.5rem; background-color: var(--gray-50); padding: 1rem; border-radius: var(--radius-md); border: 1px solid var(--gray-200);">
                        <?php foreach ($all_skills as $skill): ?>
                            <label style="font-size: 0.9rem; font-weight: normal; cursor: pointer; display: inline-flex; align-items: center; gap: 0.4rem;">
                                <input 
                                    type="checkbox" 
                                    name="skills[]" 
                                    value="<?= (int)$skill['skill_id'] ?>"
                                    <?= in_array((int)$skill['skill_id'], $selected_skills, false) ? 'checked' : '' ?>
                                >
                                <?= e($skill['skill_name']) ?>
                            </label>
                        <?php endforeach; ?>
                    </div>
                </div>

                <div style="margin-top: 2rem; display: flex; justify-content: space-between; align-items: center; flex-wrap: wrap; gap: 1rem; border-top: 1px solid var(--gray-200); padding-top: 1.25rem;">
                    <div style="display: flex; gap: 0.75rem;">
                        <button type="submit" class="btn btn-primary">
                            Save Changes
                        </button>
                        <a href="<?= BASE_URL ?>/student/task-details.php?id=<?= $task_id ?>" class="btn btn-secondary">
                            Cancel
                        </a>
                    </div>
                </div>
            </form>

            <!-- Separate Delete Task Form for Security (POST only, owner only) -->
            <div style="margin-top: 1.5rem; padding: 1rem; background-color: #fef2f2; border: 1px solid #fecaca; border-radius: var(--radius-md); display: flex; justify-content: space-between; align-items: center; flex-wrap: wrap; gap: 1rem;">
                <div>
                    <strong style="color: #991b1b; display: block;">Danger Zone: Delete Task</strong>
                    <span style="color: #b91c1c; font-size: 0.85rem;">Permanently remove this task and its applicant records. This action cannot be undone.</span>
                </div>
                <form action="<?= BASE_URL ?>/student/edit-task.php?id=<?= $task_id ?>" method="POST" onsubmit="return confirm('Are you sure you want to permanently delete this task? This action cannot be undone.');">
                    <input type="hidden" name="action" value="delete">
                    <input type="hidden" name="task_id" value="<?= $task_id ?>">
                    <button type="submit" class="btn" style="background-color: var(--danger-color); color: #fff;">
                        Delete Task
                    </button>
                </form>
            </div>
        </div>
    </div>
</div>

<?php
require_once __DIR__ . '/../includes/footer.php';
?>
