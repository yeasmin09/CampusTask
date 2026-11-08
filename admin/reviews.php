<?php
/**
 * CampusTask - Admin Review Moderation
 * 
 * Course: CSE 2291 - Database Management System (DBMS)
 * Role: Moderation (Person B)
 */

$page_title = 'Review Moderation';

require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/auth.php';

// Strict backend check: Only ADMIN can moderate reviews
require_admin();

// Handle Delete Review (POST Request)
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';
    $review_id = (int)($_POST['review_id'] ?? 0);

    if ($action === 'delete' && $review_id > 0) {
        $stmt = $pdo->prepare('DELETE FROM reviews WHERE review_id = :id');
        $stmt->execute([':id' => $review_id]);

        set_flash('success', "Review #{$review_id} has been permanently deleted.");
        redirect('admin/reviews.php');
    }
}

// Search and Filter Parameters
$search = trim($_GET['search'] ?? '');
$rating_filter = (int)($_GET['rating'] ?? 0);
$sort = trim($_GET['sort'] ?? 'newest');

$sql = "
    SELECT 
        r.review_id,
        r.task_id,
        r.rating,
        r.comment,
        r.created_at,
        reviewer.user_id AS reviewer_id,
        reviewer.full_name AS reviewer_name,
        reviewer.email AS reviewer_email,
        reviewee.user_id AS reviewee_id,
        reviewee.full_name AS reviewee_name,
        reviewee.email AS reviewee_email,
        t.title AS task_title
    FROM reviews r
    JOIN users reviewer ON r.reviewer_id = reviewer.user_id
    JOIN users reviewee ON r.reviewee_id = reviewee.user_id
    JOIN tasks t ON r.task_id = t.task_id
    WHERE 1=1
";

$params = [];

if ($search !== '') {
    $sql .= " AND (reviewer.full_name LIKE :search OR reviewee.full_name LIKE :search OR t.title LIKE :search OR r.comment LIKE :search)";
    $params[':search'] = '%' . $search . '%';
}

if ($rating_filter >= 1 && $rating_filter <= 5) {
    $sql .= " AND r.rating = :rating";
    $params[':rating'] = $rating_filter;
}

$sort_map = [
    'newest'      => 'r.created_at DESC',
    'oldest'      => 'r.created_at ASC',
    'rating_high' => 'r.rating DESC, r.created_at DESC',
    'rating_low'  => 'r.rating ASC, r.created_at DESC',
];

$order_by = $sort_map[$sort] ?? 'r.created_at DESC';
$sql .= " ORDER BY " . $order_by;

$stmt = $pdo->prepare($sql);
$stmt->execute($params);
$reviews = $stmt->fetchAll();

require_once __DIR__ . '/../includes/header.php';
?>

<div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 1.5rem; flex-wrap: wrap; gap: 0.5rem;">
    <div>
        <h1>Review Moderation</h1>
        <p style="color: var(--gray-600); margin: 0;">Monitor, moderate, and remove inappropriate or abusive student reviews.</p>
    </div>
    <a href="<?= BASE_URL ?>/admin/dashboard.php" class="btn btn-secondary btn-sm">&larr; Back to Dashboard</a>
</div>

<!-- Search & Filter Form -->
<form action="<?= BASE_URL ?>/admin/reviews.php" method="GET" class="filter-bar">
    <input 
        type="text" 
        name="search" 
        class="form-control" 
        placeholder="Search reviews, student names, or tasks..." 
        value="<?= e($search) ?>"
    >

    <select name="rating" class="form-control">
        <option value="">All Star Ratings</option>
        <option value="5" <?= ($rating_filter === 5) ? 'selected' : '' ?>>5 Stars</option>
        <option value="4" <?= ($rating_filter === 4) ? 'selected' : '' ?>>4 Stars</option>
        <option value="3" <?= ($rating_filter === 3) ? 'selected' : '' ?>>3 Stars</option>
        <option value="2" <?= ($rating_filter === 2) ? 'selected' : '' ?>>2 Stars</option>
        <option value="1" <?= ($rating_filter === 1) ? 'selected' : '' ?>>1 Star</option>
    </select>

    <select name="sort" class="form-control">
        <option value="newest" <?= ($sort === 'newest') ? 'selected' : '' ?>>Newest First</option>
        <option value="oldest" <?= ($sort === 'oldest') ? 'selected' : '' ?>>Oldest First</option>
        <option value="rating_high" <?= ($sort === 'rating_high') ? 'selected' : '' ?>>Highest Rating</option>
        <option value="rating_low" <?= ($sort === 'rating_low') ? 'selected' : '' ?>>Lowest Rating</option>
    </select>

    <button type="submit" class="btn btn-primary btn-sm">Filter</button>
    <a href="<?= BASE_URL ?>/admin/reviews.php" class="btn btn-secondary btn-sm">Reset</a>
</form>

<!-- Reviews Table -->
<div class="card">
    <div class="card-header">
        <h3 class="card-title">Reviews in System (<?= count($reviews) ?>)</h3>
    </div>
    <div class="card-body" style="padding: 0;">
        <?php if (empty($reviews)): ?>
            <div class="empty-state">
                <div class="empty-state-title">No reviews found</div>
                <div class="empty-state-text">No reviews matched your search or filter criteria.</div>
            </div>
        <?php else: ?>
            <div class="table-responsive">
                <table class="table table-striped table-hover">
                    <thead>
                        <tr>
                            <th>Review ID</th>
                            <th>Task</th>
                            <th>Reviewer &rarr; Reviewee</th>
                            <th>Rating</th>
                            <th>Comment</th>
                            <th>Date</th>
                            <th style="text-align: right;">Action</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($reviews as $rev): ?>
                            <tr>
                                <td>#<?= e($rev['review_id']) ?></td>
                                <td>
                                    <strong>
                                        <a href="<?= BASE_URL ?>/student/task-details.php?id=<?= e($rev['task_id']) ?>">
                                            <?= e($rev['task_title']) ?>
                                        </a>
                                    </strong>
                                </td>
                                <td>
                                    <div style="font-size: 0.9rem;">
                                        <strong><?= e($rev['reviewer_name']) ?></strong>
                                        <span style="color: var(--gray-400); margin: 0 0.25rem;">&rarr;</span>
                                        <strong><?= e($rev['reviewee_name']) ?></strong>
                                    </div>
                                    <div style="font-size: 0.75rem; color: var(--gray-500);">
                                        <?= e($rev['reviewer_email']) ?> &bull; <?= e($rev['reviewee_email']) ?>
                                    </div>
                                </td>
                                <td>
                                    <span class="rating-stars" style="font-size: 0.9rem;">
                                        <?php for ($i = 1; $i <= 5; $i++): ?>
                                            <span class="<?= ($i <= $rev['rating']) ? 'star-filled' : '' ?>">&starf;</span>
                                        <?php endfor; ?>
                                    </span>
                                    <span style="font-weight: 700; font-size: 0.85rem; margin-left: 0.25rem;">
                                        (<?= (int)$rev['rating'] ?>)
                                    </span>
                                </td>
                                <td style="max-width: 280px;">
                                    <div style="font-size: 0.875rem; color: var(--gray-700);">
                                        &ldquo;<?= nl2br(e($rev['comment'])) ?>&rdquo;
                                    </div>
                                </td>
                                <td><?= date('M d, Y', strtotime($rev['created_at'])) ?></td>
                                <td style="text-align: right;">
                                    <form action="<?= BASE_URL ?>/admin/reviews.php" method="POST" style="display: inline-block;">
                                        <input type="hidden" name="action" value="delete">
                                        <input type="hidden" name="review_id" value="<?= e($rev['review_id']) ?>">
                                        <button 
                                            type="submit" 
                                            class="btn btn-danger btn-sm"
                                            onclick="return confirm('Permanently delete Review #<?= e($rev['review_id']) ?>? This action cannot be undone.');"
                                        >
                                            Delete
                                        </button>
                                    </form>
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
