<?php
/**
 * CampusTask - Task Reviews and Ratings
 * 
 * Course: CSE 2291 - Database Management System (DBMS)
 * Role: Reviews & Reputation (Person B)
 */

$page_title = 'Task Reviews';

require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/auth.php';

// Strict backend check: Student role required
require_student();

$current_user_id = current_user_id();
$task_id = (int)($_REQUEST['task_id'] ?? 0);

if ($task_id <= 0) {
    set_flash('error', 'Invalid task specified.');
    redirect('student/dashboard.php');
}

// 1. Fetch Task Details
$task_stmt = $pdo->prepare('
    SELECT t.task_id, t.title, t.description, t.budget, t.deadline, t.status, t.poster_id,
           poster.full_name AS poster_name
    FROM tasks t
    JOIN users poster ON t.poster_id = poster.user_id
    WHERE t.task_id = :id
    LIMIT 1
');
$task_stmt->execute([':id' => $task_id]);
$task = $task_stmt->fetch();

if (!$task) {
    set_flash('error', 'Task not found.');
    redirect('student/dashboard.php');
}

// Rule: Reviews are allowed ONLY for COMPLETED tasks
if ($task['status'] !== 'COMPLETED') {
    set_flash('error', 'Reviews can only be submitted for COMPLETED tasks (Current Status: ' . $task['status'] . ').');
    redirect('student/dashboard.php');
}

// 2. Fetch the Accepted Freelancer for this Task
$app_stmt = $pdo->prepare('
    SELECT a.application_id, a.applicant_id, freelancer.full_name AS freelancer_name
    FROM applications a
    JOIN users freelancer ON a.applicant_id = freelancer.user_id
    WHERE a.task_id = :task_id AND a.status = "ACCEPTED"
    LIMIT 1
');
$app_stmt->execute([':task_id' => $task_id]);
$accepted_applicant = $app_stmt->fetch();

if (!$accepted_applicant) {
    set_flash('error', 'No accepted freelancer found for this task.');
    redirect('student/dashboard.php');
}

$poster_id = (int)$task['poster_id'];
$freelancer_id = (int)$accepted_applicant['applicant_id'];

// 3. Derive Reviewer and Reviewee strictly on the server
$is_poster = ($current_user_id === $poster_id);
$is_freelancer = ($current_user_id === $freelancer_id);

if (!$is_poster && !$is_freelancer) {
    set_flash('error', 'Only the task poster and the accepted freelancer can submit a review for this task.');
    redirect('student/dashboard.php');
}

if ($is_poster) {
    $reviewer_id = $poster_id;
    $reviewee_id = $freelancer_id;
    $reviewee_name = $accepted_applicant['freelancer_name'];
    $reviewee_role_label = 'Freelancer';
} else {
    $reviewer_id = $freelancer_id;
    $reviewee_id = $poster_id;
    $reviewee_name = $task['poster_name'];
    $reviewee_role_label = 'Task Poster';
}

// 4. Check if current user has already submitted a review for this task
$existing_review_stmt = $pdo->prepare('
    SELECT review_id, rating, comment, created_at
    FROM reviews
    WHERE task_id = :task_id AND reviewer_id = :reviewer_id
    LIMIT 1
');
$existing_review_stmt->execute([
    ':task_id'     => $task_id,
    ':reviewer_id' => $reviewer_id
]);
$my_review = $existing_review_stmt->fetch();

// 5. Handle New Review Submission (POST Request)
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if ($my_review) {
        set_flash('error', 'You have already submitted a review for this task.');
        redirect('student/reviews.php?task_id=' . $task_id);
    }

    $rating = (int)($_POST['rating'] ?? 0);
    $comment = trim($_POST['comment'] ?? '');

    $errors = [];

    // Validation: Rating must be 1 to 5
    if ($rating < 1 || $rating > 5) {
        $errors[] = 'Please select a valid rating between 1 and 5 stars.';
    }

    // Validation: Comment required
    if ($comment === '') {
        $errors[] = 'Please provide a feedback comment.';
    } elseif (strlen($comment) < 5) {
        $errors[] = 'Comment is too short. Please provide at least 5 characters.';
    }

    if (empty($errors)) {
        try {
            $insert_review = $pdo->prepare('
                INSERT INTO reviews (task_id, reviewer_id, reviewee_id, rating, comment, created_at)
                VALUES (:task_id, :reviewer_id, :reviewee_id, :rating, :comment, NOW())
            ');
            $insert_review->execute([
                ':task_id'     => $task_id,
                ':reviewer_id' => $reviewer_id,
                ':reviewee_id' => $reviewee_id,
                ':rating'      => $rating,
                ':comment'     => $comment
            ]);

            set_flash('success', 'Your review has been submitted successfully!');
            redirect('student/reviews.php?task_id=' . $task_id);
        } catch (PDOException $e) {
            if ($e->getCode() == 23000) {
                set_flash('error', 'You have already submitted a review for this task.');
            } else {
                set_flash('error', 'Database error: ' . $e->getMessage());
            }
            redirect('student/reviews.php?task_id=' . $task_id);
        }
    }
}

// 6. Fetch All Reviews for this Task
$all_task_reviews_stmt = $pdo->prepare('
    SELECT r.review_id, r.rating, r.comment, r.created_at,
           reviewer.full_name AS reviewer_name,
           reviewee.full_name AS reviewee_name
    FROM reviews r
    JOIN users reviewer ON r.reviewer_id = reviewer.user_id
    JOIN users reviewee ON r.reviewee_id = reviewee.user_id
    WHERE r.task_id = :task_id
    ORDER BY r.created_at ASC
');
$all_task_reviews_stmt->execute([':task_id' => $task_id]);
$task_reviews = $all_task_reviews_stmt->fetchAll();

// 7. Calculate Reviewee Overall Average Rating
$avg_stmt = $pdo->prepare('
    SELECT AVG(rating) AS avg_rating, COUNT(*) AS total_count
    FROM reviews
    WHERE reviewee_id = :reviewee_id
');
$avg_stmt->execute([':reviewee_id' => $reviewee_id]);
$reviewee_stats = $avg_stmt->fetch();
$avg_rating = $reviewee_stats['avg_rating'] ? round((float)$reviewee_stats['avg_rating'], 1) : null;
$total_reviews = (int)($reviewee_stats['total_count'] ?? 0);

require_once __DIR__ . '/../includes/header.php';
?>

<!-- Task Information Card -->
<div class="card" style="margin-bottom: 1.5rem;">
    <div class="card-header">
        <div>
            <span style="font-size: 0.85rem; color: var(--gray-500); text-transform: uppercase; font-weight: 600;">Completed Task Review</span>
            <h2 class="card-title" style="margin-top: 0.25rem;">
                <a href="<?= BASE_URL ?>/student/task-details.php?id=<?= e($task['task_id']) ?>"><?= e($task['title']) ?></a>
            </h2>
        </div>
        <span class="badge badge-completed">Completed</span>
    </div>
    <div class="card-body">
        <div style="display: flex; gap: 2rem; flex-wrap: wrap; font-size: 0.95rem; margin-bottom: 0.5rem;">
            <div><strong>Task Poster:</strong> <?= e($task['poster_name']) ?></div>
            <div><strong>Accepted Freelancer:</strong> <?= e($accepted_applicant['freelancer_name']) ?></div>
            <div><strong>Agreed Budget:</strong> $<?= number_format((float)$task['budget'], 2) ?></div>
        </div>
    </div>
</div>

<div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(320px, 1fr)); gap: 1.5rem; margin-bottom: 2rem;">
    <!-- Left Column: Submit or View My Review -->
    <div>
        <?php if ($my_review): ?>
            <!-- Already Submitted Box -->
            <div class="card">
                <div class="card-header">
                    <h3 class="card-title">Your Review for <?= e($reviewee_name) ?></h3>
                </div>
                <div class="card-body">
                    <div style="margin-bottom: 0.75rem;">
                        <span class="rating-stars">
                            <?php for ($i = 1; $i <= 5; $i++): ?>
                                <span class="<?= ($i <= $my_review['rating']) ? 'star-filled' : '' ?>">&starf;</span>
                            <?php endfor; ?>
                        </span>
                        <strong style="margin-left: 0.5rem; color: var(--gray-800);"><?= (int)$my_review['rating'] ?> / 5 Stars</strong>
                    </div>
                    <p style="color: var(--gray-700); background-color: var(--gray-50); padding: 0.75rem 1rem; border-radius: var(--border-radius); border-left: 3px solid var(--primary);">
                        &ldquo;<?= nl2br(e($my_review['comment'])) ?>&rdquo;
                    </p>
                    <div style="font-size: 0.8rem; color: var(--gray-500);">
                        Submitted on <?= date('M d, Y h:i A', strtotime($my_review['created_at'])) ?>
                    </div>
                </div>
            </div>
        <?php else: ?>
            <!-- Submission Form -->
            <div class="card">
                <div class="card-header">
                    <h3 class="card-title">Review <?= e($reviewee_name) ?> (<?= e($reviewee_role_label) ?>)</h3>
                </div>
                <div class="card-body">
                    <?php if (!empty($errors)): ?>
                        <div class="flash-alert flash-error" style="margin-bottom: 1rem;">
                            <ul style="margin-left: 1.25rem; margin-bottom: 0;">
                                <?php foreach ($errors as $err): ?>
                                    <li><?= e($err) ?></li>
                                <?php endforeach; ?>
                            </ul>
                        </div>
                    <?php endif; ?>

                    <form action="<?= BASE_URL ?>/student/reviews.php?task_id=<?= e($task_id) ?>" method="POST">
                        <div class="form-group">
                            <label for="rating">Rating (1 to 5 Stars)</label>
                            <select id="rating" name="rating" class="form-control" required>
                                <option value="">-- Select Rating --</option>
                                <option value="5" <?= (($_POST['rating'] ?? '') === '5') ? 'selected' : '' ?>>5 Stars - Excellent Collaboration</option>
                                <option value="4" <?= (($_POST['rating'] ?? '') === '4') ? 'selected' : '' ?>>4 Stars - Very Good</option>
                                <option value="3" <?= (($_POST['rating'] ?? '') === '3') ? 'selected' : '' ?>>3 Stars - Average / Satisfactory</option>
                                <option value="2" <?= (($_POST['rating'] ?? '') === '2') ? 'selected' : '' ?>>2 Stars - Needs Improvement</option>
                                <option value="1" <?= (($_POST['rating'] ?? '') === '1') ? 'selected' : '' ?>>1 Star - Poor Experience</option>
                            </select>
                        </div>

                        <div class="form-group">
                            <label for="comment">Feedback &amp; Comments</label>
                            <textarea 
                                id="comment" 
                                name="comment" 
                                class="form-control" 
                                rows="4" 
                                placeholder="Describe your experience working together on this task..."
                                required
                            ><?= e($_POST['comment'] ?? '') ?></textarea>
                            <div class="form-text">Your honest feedback helps maintain quality and trust in our campus community.</div>
                        </div>

                        <button type="submit" class="btn btn-primary btn-block">
                            Submit Review
                        </button>
                    </form>
                </div>
            </div>
        <?php endif; ?>
    </div>

    <!-- Right Column: Reviewee Reputation & Task Reviews -->
    <div>
        <!-- Reviewee Reputation Card -->
        <div class="card" style="margin-bottom: 1.5rem;">
            <div class="card-header">
                <h3 class="card-title"><?= e($reviewee_name) ?>'s Overall Rating</h3>
            </div>
            <div class="card-body" style="text-align: center; padding: 1.5rem;">
                <?php if ($avg_rating !== null): ?>
                    <div style="font-size: 2.5rem; font-weight: 800; color: #b45309; line-height: 1;">
                        <?= number_format($avg_rating, 1) ?>
                    </div>
                    <div class="rating-stars" style="font-size: 1.4rem; margin: 0.5rem 0;">
                        <?php for ($i = 1; $i <= 5; $i++): ?>
                            <span class="<?= ($i <= round($avg_rating)) ? 'star-filled' : '' ?>">&starf;</span>
                        <?php endfor; ?>
                    </div>
                    <div style="font-size: 0.85rem; color: var(--gray-500);">
                        Based on <?= $total_reviews ?> completed <?= ($total_reviews === 1) ? 'review' : 'reviews' ?>
                    </div>
                <?php else: ?>
                    <div style="color: var(--gray-500); font-size: 0.95rem;">
                        No previous reviews yet for this student.
                    </div>
                <?php endif; ?>
            </div>
        </div>

        <!-- All Reviews on this Task -->
        <div class="card">
            <div class="card-header">
                <h3 class="card-title">Reviews on this Task (<?= count($task_reviews) ?>/2)</h3>
            </div>
            <div class="card-body">
                <?php if (empty($task_reviews)): ?>
                    <p style="color: var(--gray-500); font-size: 0.9rem; margin: 0;">No reviews recorded yet for this task.</p>
                <?php else: ?>
                    <?php foreach ($task_reviews as $r): ?>
                        <div style="padding-bottom: 0.75rem; margin-bottom: 0.75rem; border-bottom: 1px solid var(--gray-200);">
                            <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 0.25rem;">
                                <strong><?= e($r['reviewer_name']) ?> &rarr; <?= e($r['reviewee_name']) ?></strong>
                                <span class="rating-stars" style="font-size: 0.95rem;">
                                    <?php for ($i = 1; $i <= 5; $i++): ?>
                                        <span class="<?= ($i <= $r['rating']) ? 'star-filled' : '' ?>">&starf;</span>
                                    <?php endfor; ?>
                                </span>
                            </div>
                            <p style="font-size: 0.9rem; color: var(--gray-700); margin-bottom: 0.25rem;">
                                &ldquo;<?= nl2br(e($r['comment'])) ?>&rdquo;
                            </p>
                            <div style="font-size: 0.75rem; color: var(--gray-400);">
                                <?= date('M d, Y', strtotime($r['created_at'])) ?>
                            </div>
                        </div>
                    <?php endforeach; ?>
                <?php endif; ?>
            </div>
        </div>
    </div>
</div>

<div style="margin-bottom: 2rem;">
    <a href="<?= BASE_URL ?>/student/dashboard.php" class="btn btn-secondary btn-sm">&larr; Return to Student Dashboard</a>
</div>

<?php
require_once __DIR__ . '/../includes/footer.php';
?>
