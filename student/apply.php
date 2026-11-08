<?php
/**
 * CampusTask - Submit Task Application
 * 
 * Course: CSE 2291 - Database Management System (DBMS)
 * Role: Task Applications (Person B)
 * 
 * Invoked via POST from student/task-details.php (Person A)
 */

require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/auth.php';

// Strict backend check: Only logged-in STUDENT users can apply
require_student();

// Only accept POST requests
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    redirect('student/tasks.php');
}

$task_id = (int)($_POST['task_id'] ?? 0);
$proposal = trim($_POST['proposal'] ?? '');
$proposed_budget_input = trim($_POST['proposed_budget'] ?? '');
$current_student_id = current_user_id();

// Helper redirect back to task details page
$redirect_url = 'student/task-details.php?id=' . $task_id;

// 1. Basic validation of task_id
if ($task_id <= 0) {
    set_flash('error', 'Invalid task specified.');
    redirect('student/tasks.php');
}

// 2. Validate proposal text
if ($proposal === '') {
    set_flash('error', 'Please write a proposal explaining how you will complete this task.');
    redirect($redirect_url);
}

if (strlen($proposal) < 10) {
    set_flash('error', 'Proposal is too short. Please provide at least 10 characters.');
    redirect($redirect_url);
}

// 3. Validate proposed budget
if ($proposed_budget_input === '' || !is_numeric($proposed_budget_input)) {
    set_flash('error', 'Please enter a valid numeric proposed budget.');
    redirect($redirect_url);
}

$proposed_budget = (float)$proposed_budget_input;
if ($proposed_budget < 0) {
    set_flash('error', 'Proposed budget cannot be negative.');
    redirect($redirect_url);
}

// 4. Query task details from database
try {
    $stmt = $pdo->prepare('SELECT task_id, poster_id, status FROM tasks WHERE task_id = :id LIMIT 1');
    $stmt->execute([':id' => $task_id]);
    $task = $stmt->fetch();

    if (!$task) {
        set_flash('error', 'The requested task does not exist.');
        redirect('student/tasks.php');
    }

    // Rule: Task must be OPEN
    if ($task['status'] !== 'OPEN') {
        set_flash('error', 'This task is not open for applications (Status: ' . $task['status'] . ').');
        redirect($redirect_url);
    }

    // Rule: Student cannot apply to their own task
    if ((int)$task['poster_id'] === $current_student_id) {
        set_flash('error', 'You cannot apply to a task that you posted yourself.');
        redirect($redirect_url);
    }

    // Rule: One student cannot apply twice to the same task
    $check_stmt = $pdo->prepare('SELECT application_id FROM applications WHERE task_id = :task_id AND applicant_id = :applicant_id LIMIT 1');
    $check_stmt->execute([
        ':task_id'      => $task_id,
        ':applicant_id' => $current_student_id
    ]);
    if ($check_stmt->fetch()) {
        set_flash('error', 'You have already submitted an application for this task.');
        redirect($redirect_url);
    }

    // 5. Insert new application with PENDING status
    $insert_stmt = $pdo->prepare('
        INSERT INTO applications (task_id, applicant_id, proposal, proposed_budget, status, applied_at)
        VALUES (:task_id, :applicant_id, :proposal, :proposed_budget, "PENDING", NOW())
    ');
    $insert_stmt->execute([
        ':task_id'         => $task_id,
        ':applicant_id'    => $current_student_id,
        ':proposal'        => $proposal,
        ':proposed_budget' => $proposed_budget
    ]);

    set_flash('success', 'Your application has been submitted successfully!');
    redirect($redirect_url);

} catch (PDOException $e) {
    // Catch unique constraint violation (duplicate key error)
    if ($e->getCode() == 23000) {
        set_flash('error', 'You have already submitted an application for this task.');
    } else {
        set_flash('error', 'A database error occurred while submitting your application.');
    }
    redirect($redirect_url);
}
