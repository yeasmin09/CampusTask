<?php
/**
 * CampusTask - Post a New Task (Create)
 * 
 * Course: CSE 2291 - Database Management System (DBMS)
 * Role: Task Management (Person A - Feature A3)
 * Demonstrates: Multi-table database transaction inserting into tasks and task_skills (M:N)
 */

$page_title = 'Post a Task';

require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/auth.php';

// Strict backend check: Only logged-in students can post tasks
require_student();

$current_user_id = current_user_id();
$errors = [];

// 1. Fetch available categories and skills for selection
$categories_stmt = $pdo->query('SELECT category_id, category_name FROM categories ORDER BY category_name ASC');
$categories = $categories_stmt->fetchAll();

$skills_stmt = $pdo->query('SELECT skill_id, skill_name FROM skills ORDER BY skill_name ASC');
$skills = $skills_stmt->fetchAll();

// Form default values
$title = '';
$category_id = '';
$budget = '';
$deadline = date('Y-m-d', strtotime('+3 days'));
$description = '';
$selected_skills = [];

// 2. Process Form Submission (POST)
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
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
    } else {
        $cat_check = $pdo->prepare('SELECT category_id FROM categories WHERE category_id = :id');
        $cat_check->execute([':id' => $category_id]);
        if (!$cat_check->fetch()) {
            $errors[] = 'The selected category does not exist.';
        }
    }

    if ($budget_raw === '') {
        $errors[] = 'Budget is required.';
    } elseif (!is_numeric($budget_raw) || (float)$budget_raw < 0) {
        $errors[] = 'Budget must be a non-negative number.';
    } else {
        $budget = (float)$budget_raw;
    }

    if ($deadline === '') {
        $errors[] = 'Deadline date is required.';
    } else {
        $today = date('Y-m-d');
        if ($deadline < $today) {
            $errors[] = 'Deadline cannot be in the past.';
        }
    }

    if ($description === '') {
        $errors[] = 'Task description is required.';
    } elseif (mb_strlen($description) < 15) {
        $errors[] = 'Please provide a detailed description (at least 15 characters).';
    }

    // 3. Database Transaction: Insert into tasks and task_skills
    if (empty($errors)) {
        try {
            $pdo->beginTransaction();

            $insert_task_stmt = $pdo->prepare('
                INSERT INTO tasks (poster_id, category_id, title, description, budget, deadline, status, created_at)
                VALUES (:poster_id, :category_id, :title, :description, :budget, :deadline, "OPEN", NOW())
            ');

            $insert_task_stmt->execute([
                ':poster_id'   => $current_user_id,
                ':category_id' => $category_id,
                ':title'       => $title,
                ':description' => $description,
                ':budget'      => $budget,
                ':deadline'    => $deadline
            ]);

            $new_task_id = (int)$pdo->lastInsertId();

            // Insert Many-to-Many skills into task_skills junction table
            if (!empty($selected_skills)) {
                $insert_skill_stmt = $pdo->prepare('
                    INSERT INTO task_skills (task_id, skill_id) 
                    VALUES (:task_id, :skill_id)
                ');

                foreach ($selected_skills as $skill_id) {
                    if ($skill_id > 0) {
                        $insert_skill_stmt->execute([
                            ':task_id'  => $new_task_id,
                            ':skill_id' => $skill_id
                        ]);
                    }
                }
            }

            // Commit all changes atomically
            $pdo->commit();

            set_flash('success', 'Task posted successfully! Students can now view and apply.');
            redirect('student/task-details.php?id=' . $new_task_id);

        } catch (PDOException $e) {
            if ($pdo->inTransaction()) {
                $pdo->rollBack();
            }
            $errors[] = 'Failed to post task due to a database error: ' . $e->getMessage();
        }
    }
}

require_once __DIR__ . '/../includes/header.php';
?>

<div style="max-width: 800px; margin: 2rem auto;">
    <div style="margin-bottom: 1.5rem; display: flex; justify-content: space-between; align-items: center; flex-wrap: wrap; gap: 1rem;">
        <div>
            <h1 style="font-size: 1.75rem; font-weight: 700; color: var(--gray-900); margin-bottom: 0.25rem;">
                Post a New Task
            </h1>
            <p style="color: var(--gray-600); font-size: 0.95rem;">
                Describe the academic or everyday task you need assistance with on campus.
            </p>
        </div>
        <a href="<?= BASE_URL ?>/student/tasks.php" class="btn btn-secondary">
            &larr; Browse Tasks
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

    <div class="card">
        <div class="card-body">
            <form action="<?= BASE_URL ?>/student/post-task.php" method="POST" novalidate>
                <div class="form-group">
                    <label for="title">Task Title <span style="color: var(--danger-color);">*</span></label>
                    <input 
                        type="text" 
                        id="title" 
                        name="title" 
                        class="form-control" 
                        placeholder="e.g. Design 10 PowerPoint Presentation Slides for CSE 2291"
                        value="<?= e($title) ?>" 
                        required
                    >
                    <small style="color: var(--gray-500); font-size: 0.8rem;">
                        Be clear and concise. A good title helps peers understand your requirements quickly.
                    </small>
                </div>

                <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 1.25rem;">
                    <div class="form-group">
                        <label for="category_id">Category <span style="color: var(--danger-color);">*</span></label>
                        <select id="category_id" name="category_id" class="form-control" required>
                            <option value="">-- Choose Category --</option>
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
                            placeholder="e.g. 35.00"
                            value="<?= e((string)$budget_raw) ?>" 
                            required
                        >
                        <small style="color: var(--gray-500); font-size: 0.8rem;">
                            Set 0 for volunteer/unpaid collaboration.
                        </small>
                    </div>
                </div>

                <div class="form-group">
                    <label for="deadline">Target Deadline <span style="color: var(--danger-color);">*</span></label>
                    <input 
                        type="date" 
                        id="deadline" 
                        name="deadline" 
                        class="form-control" 
                        min="<?= date('Y-m-d') ?>"
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
                        placeholder="Explain exactly what needs to be done, specific requirements, files to be supplied, formatting expectations..."
                        required
                    ><?= e($description) ?></textarea>
                </div>

                <div class="form-group">
                    <label style="display: block; margin-bottom: 0.5rem;">
                        Required Skills (Optional, select all that apply)
                    </label>
                    <div style="display: grid; grid-template-columns: repeat(auto-fill, minmax(180px, 1fr)); gap: 0.5rem; background-color: var(--gray-50); padding: 1rem; border-radius: var(--radius-md); border: 1px solid var(--gray-200);">
                        <?php foreach ($skills as $skill): ?>
                            <label style="font-size: 0.9rem; font-weight: normal; cursor: pointer; display: inline-flex; align-items: center; gap: 0.4rem;">
                                <input 
                                    type="checkbox" 
                                    name="skills[]" 
                                    value="<?= (int)$skill['skill_id'] ?>"
                                    <?= in_array((int)$skill['skill_id'], $selected_skills, true) ? 'checked' : '' ?>
                                >
                                <?= e($skill['skill_name']) ?>
                            </label>
                        <?php endforeach; ?>
                    </div>
                </div>

                <div style="margin-top: 1.5rem; display: flex; gap: 1rem; align-items: center;">
                    <button type="submit" class="btn btn-primary">
                        Publish Task
                    </button>
                    <a href="<?= BASE_URL ?>/student/tasks.php" class="btn btn-secondary">
                        Cancel
                    </a>
                </div>
            </form>
        </div>
    </div>
</div>

<?php
require_once __DIR__ . '/../includes/footer.php';
?>
