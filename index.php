<?php
/**
 * CampusTask - Redesigned Corporate Homepage
 * 
 * Course: CSE 2291 - Database Management System (DBMS)
 * Role: Public Landing Page (Person A - Feature A10)
 * 
 * DESIGN PALETTE:
 *  - Deep Navy:  #102F46
 *  - Dark Blue:  #163D57
 *  - Teal:       #367C89
 *  - Steel Blue: #7597A8
 *  - Background: #F4F7F8
 *  - White:      #FFFFFF
 */

$page_title = 'Find Opportunities. Share Your Skills.';

// Load configuration and database connection
$config_path = __DIR__ . '/config/database.php';
if (file_exists($config_path)) {
    require_once $config_path;
} else {
    require_once __DIR__ . '/config/database.example.php';
}

require_once __DIR__ . '/includes/auth.php';

// 1. Fetch categories with task counts for the category section and dropdown
$categories_stmt = $pdo->query('
    SELECT 
        c.category_id, 
        c.category_name, 
        c.description,
        COUNT(t.task_id) AS task_count 
    FROM categories c 
    LEFT JOIN tasks t ON c.category_id = t.category_id AND t.status != "CANCELLED"
    GROUP BY c.category_id, c.category_name, c.description
    ORDER BY c.category_name ASC
');
$all_categories = $categories_stmt->fetchAll();

// Index categories by name for easy card mapping
$cat_map = [];
foreach ($all_categories as $c) {
    $cat_map[strtolower(trim($c['category_name']))] = $c;
}

// 2. Fetch Latest OPEN Tasks
$latest_tasks_stmt = $pdo->query('
    SELECT 
        t.task_id,
        t.title,
        t.description,
        t.budget,
        t.deadline,
        t.status,
        t.created_at,
        u.full_name AS poster_name,
        c.category_name,
        (SELECT COUNT(*) FROM applications a WHERE a.task_id = t.task_id) AS app_count,
        GROUP_CONCAT(DISTINCT s.skill_name ORDER BY s.skill_name ASC SEPARATOR "||") AS skills_list
    FROM tasks t
    JOIN users u ON t.poster_id = u.user_id
    JOIN categories c ON t.category_id = c.category_id
    LEFT JOIN task_skills ts ON t.task_id = ts.task_id
    LEFT JOIN skills s ON ts.skill_id = s.skill_id
    WHERE t.status = "OPEN"
    GROUP BY t.task_id
    ORDER BY t.created_at DESC
    LIMIT 6
');
$latest_tasks = $latest_tasks_stmt->fetchAll();

// 3. Overall marketplace KPI statistics
$stats = [
    'total_tasks'     => (int)$pdo->query('SELECT COUNT(*) FROM tasks')->fetchColumn(),
    'active_students' => (int)$pdo->query('SELECT COUNT(*) FROM users WHERE role = "STUDENT" AND account_status = "ACTIVE"')->fetchColumn(),
    'completed_tasks' => (int)$pdo->query('SELECT COUNT(*) FROM tasks WHERE status = "COMPLETED"')->fetchColumn()
];

$is_logged = is_logged_in();
$user_role = current_user_role();
$user_name = $_SESSION['full_name'] ?? 'User';

// Include shared header layout (Person B)
require_once __DIR__ . '/includes/header.php';
?>

<!-- =============================================================
     CORPORATE REDESIGN STYLESHEET (SCOPED TO HOMEPAGE)
     Deep Navy: #102F46 | Dark Blue: #163D57 | Teal: #367C89
     Steel Blue: #7597A8 | Canvas: #F4F7F8 | White: #FFFFFF
============================================================= -->
<style>
/* 1. Global Layout Overrides for Full-Width Experience */
body {
    background-color: #F4F7F8 !important;
    color: #102F46 !important;
    font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, "Helvetica Neue", Arial, sans-serif !important;
    margin: 0 !important;
    padding: 0 !important;
}

/* Hide the default navbar and footer from shared header so we can render the new corporate navigation */
header.navbar {
    display: none !important;
}
footer.footer {
    display: none !important;
}

/* Break out of .container.main-wrapper to allow 100% full-width immersive hero & sections */
.container.main-wrapper {
    max-width: 100% !important;
    width: 100% !important;
    margin: 0 !important;
    padding: 0 !important;
}

/* 2. Custom Premium Corporate Navbar */
.corp-navbar {
    background: #102F46;
    border-bottom: 1px solid rgba(117, 151, 168, 0.25);
    position: sticky;
    top: 0;
    z-index: 1000;
    box-shadow: 0 4px 20px rgba(16, 47, 70, 0.15);
}

.corp-nav-inner {
    max-width: 1240px;
    margin: 0 auto;
    padding: 0.95rem 1.5rem;
    display: flex;
    justify-content: space-between;
    align-items: center;
}

.corp-brand {
    display: flex;
    align-items: center;
    gap: 0.65rem;
    text-decoration: none;
    color: #FFFFFF;
    font-size: 1.45rem;
    font-weight: 800;
    letter-spacing: -0.02em;
}

.corp-brand-icon {
    width: 38px;
    height: 38px;
    background: linear-gradient(135deg, #367C89, #7597A8);
    border-radius: 8px;
    display: inline-flex;
    align-items: center;
    justify-content: center;
    color: #FFFFFF;
    font-size: 1.15rem;
    box-shadow: 0 4px 10px rgba(54, 124, 137, 0.35);
}

.corp-brand span.teal-text {
    color: #367C89;
}

.corp-nav-links {
    display: flex;
    align-items: center;
    gap: 1.75rem;
}

.corp-nav-link {
    color: #E2E8F0;
    text-decoration: none;
    font-size: 0.95rem;
    font-weight: 500;
    transition: color 0.2s ease;
    display: inline-flex;
    align-items: center;
}

.corp-nav-link:hover {
    color: #367C89;
    text-decoration: none;
}

.corp-nav-actions {
    display: flex;
    align-items: center;
    gap: 0.85rem;
}

.corp-btn-login {
    color: #FFFFFF;
    text-decoration: none;
    font-size: 0.95rem;
    font-weight: 600;
    padding: 0.55rem 1.15rem;
    border-radius: 6px;
    transition: all 0.2s ease;
}

.corp-btn-login:hover {
    color: #367C89;
    text-decoration: none;
}

.corp-btn-register {
    background: #367C89;
    color: #FFFFFF;
    text-decoration: none;
    font-size: 0.95rem;
    font-weight: 600;
    padding: 0.55rem 1.35rem;
    border-radius: 6px;
    transition: all 0.2s ease;
    box-shadow: 0 4px 12px rgba(54, 124, 137, 0.3);
}

.corp-btn-register:hover {
    background: #2b646e;
    color: #FFFFFF;
    text-decoration: none;
    transform: translateY(-1px);
    box-shadow: 0 6px 16px rgba(54, 124, 137, 0.4);
}

.corp-badge-role {
    background: rgba(54, 124, 137, 0.25);
    color: #7597A8;
    border: 1px solid rgba(117, 151, 168, 0.4);
    font-size: 0.75rem;
    padding: 0.25rem 0.65rem;
    border-radius: 20px;
    font-weight: 600;
    text-transform: uppercase;
}

/* 3. Hero Section */
.corp-hero {
    position: relative;
    width: 100%;
    min-height: 520px;
    background: 
        linear-gradient(135deg, rgba(16, 47, 70, 0.92) 0%, rgba(22, 61, 87, 0.86) 60%, rgba(54, 124, 137, 0.80) 100%),
        url('https://images.unsplash.com/photo-1523240795612-9a054b0db644?auto=format&fit=crop&w=1920&q=80') center center / cover no-repeat;
    display: flex;
    align-items: center;
    justify-content: center;
    text-align: center;
    padding: 5rem 1.5rem 6.5rem 1.5rem;
    color: #FFFFFF;
}

.corp-hero-content {
    max-width: 820px;
    margin: 0 auto;
}

.corp-hero-pill {
    display: inline-flex;
    align-items: center;
    gap: 0.5rem;
    background: rgba(255, 255, 255, 0.12);
    backdrop-filter: blur(8px);
    border: 1px solid rgba(255, 255, 255, 0.2);
    padding: 0.4rem 1rem;
    border-radius: 50px;
    font-size: 0.85rem;
    font-weight: 600;
    color: #E2E8F0;
    margin-bottom: 1.5rem;
    letter-spacing: 0.04em;
    text-transform: uppercase;
}

.corp-hero-title {
    font-size: 3.25rem;
    font-weight: 800;
    line-height: 1.18;
    color: #FFFFFF;
    margin-bottom: 1.25rem;
    letter-spacing: -0.025em;
}

.corp-hero-subtitle {
    font-size: 1.25rem;
    line-height: 1.6;
    color: #F4F7F8;
    margin-bottom: 2.25rem;
    max-width: 680px;
    margin-left: auto;
    margin-right: auto;
    font-weight: 400;
}

.corp-hero-cta-group {
    display: flex;
    gap: 1rem;
    justify-content: center;
    flex-wrap: wrap;
}

.corp-btn-cta-primary {
    background: #367C89;
    color: #FFFFFF;
    padding: 0.85rem 2rem;
    font-size: 1.05rem;
    font-weight: 700;
    border-radius: 8px;
    text-decoration: none;
    transition: all 0.2s ease;
    box-shadow: 0 6px 20px rgba(54, 124, 137, 0.45);
    display: inline-flex;
    align-items: center;
    gap: 0.5rem;
}

.corp-btn-cta-primary:hover {
    background: #2b646e;
    color: #FFFFFF;
    text-decoration: none;
    transform: translateY(-2px);
    box-shadow: 0 8px 24px rgba(54, 124, 137, 0.55);
}

.corp-btn-cta-secondary {
    background: rgba(255, 255, 255, 0.15);
    color: #FFFFFF;
    border: 1px solid rgba(255, 255, 255, 0.35);
    backdrop-filter: blur(6px);
    padding: 0.85rem 1.85rem;
    font-size: 1.05rem;
    font-weight: 600;
    border-radius: 8px;
    text-decoration: none;
    transition: all 0.2s ease;
}

.corp-btn-cta-secondary:hover {
    background: rgba(255, 255, 255, 0.25);
    color: #FFFFFF;
    text-decoration: none;
}

/* 4. Floating Search Panel */
.corp-search-wrapper {
    max-width: 1040px;
    margin: -50px auto 0 auto;
    padding: 0 1.5rem;
    position: relative;
    z-index: 20;
}

.corp-search-card {
    background: #FFFFFF;
    border-radius: 16px;
    padding: 1.75rem 2rem;
    box-shadow: 0 20px 45px -10px rgba(16, 47, 70, 0.18), 0 0 1px 1px rgba(117, 151, 168, 0.15);
}

.corp-search-form {
    display: grid;
    grid-template-columns: 2fr 1.3fr auto;
    gap: 1rem;
    align-items: center;
}

.corp-input-wrapper {
    position: relative;
    display: flex;
    align-items: center;
}

.corp-input-icon {
    position: absolute;
    left: 1rem;
    color: #7597A8;
    pointer-events: none;
}

.corp-search-input {
    width: 100%;
    padding: 0.85rem 1rem 0.85rem 2.85rem;
    font-size: 1rem;
    border: 1.5px solid #E2E8F0;
    border-radius: 8px;
    outline: none;
    color: #102F46;
    background-color: #F8FAFC;
    transition: all 0.2s ease;
}

.corp-search-input:focus {
    border-color: #367C89;
    background-color: #FFFFFF;
    box-shadow: 0 0 0 3px rgba(54, 124, 137, 0.15);
}

.corp-search-select {
    width: 100%;
    padding: 0.85rem 1rem;
    font-size: 0.95rem;
    border: 1.5px solid #E2E8F0;
    border-radius: 8px;
    outline: none;
    color: #102F46;
    background-color: #F8FAFC;
    transition: all 0.2s ease;
    cursor: pointer;
}

.corp-search-select:focus {
    border-color: #367C89;
    background-color: #FFFFFF;
    box-shadow: 0 0 0 3px rgba(54, 124, 137, 0.15);
}

.corp-btn-search {
    background: #102F46;
    color: #FFFFFF;
    border: none;
    padding: 0.85rem 1.85rem;
    font-size: 1rem;
    font-weight: 700;
    border-radius: 8px;
    cursor: pointer;
    transition: all 0.2s ease;
    display: inline-flex;
    align-items: center;
    gap: 0.5rem;
    white-space: nowrap;
}

.corp-btn-search:hover {
    background: #163D57;
    transform: translateY(-1px);
    box-shadow: 0 6px 16px rgba(16, 47, 70, 0.25);
}

/* Popular Category Chips */
.corp-search-chips {
    margin-top: 1.25rem;
    display: flex;
    align-items: center;
    gap: 0.65rem;
    flex-wrap: wrap;
    font-size: 0.85rem;
    color: #7597A8;
}

.corp-chip {
    background: #F4F7F8;
    color: #163D57;
    border: 1px solid #E2E8F0;
    padding: 0.3rem 0.85rem;
    border-radius: 30px;
    text-decoration: none;
    font-weight: 500;
    transition: all 0.15s ease;
}

.corp-chip:hover {
    background: #367C89;
    color: #FFFFFF;
    border-color: #367C89;
    text-decoration: none;
    transform: translateY(-1px);
}

/* 5. Section Containers & Headers */
.corp-section {
    max-width: 1240px;
    margin: 4.5rem auto;
    padding: 0 1.5rem;
}

.corp-section-header {
    text-align: center;
    max-width: 650px;
    margin: 0 auto 3rem auto;
}

.corp-section-tag {
    display: inline-block;
    color: #367C89;
    font-size: 0.85rem;
    font-weight: 700;
    text-transform: uppercase;
    letter-spacing: 0.08em;
    margin-bottom: 0.5rem;
}

.corp-section-title {
    font-size: 2.15rem;
    font-weight: 800;
    color: #102F46;
    letter-spacing: -0.02em;
    margin-bottom: 0.75rem;
}

.corp-section-desc {
    font-size: 1.05rem;
    color: #7597A8;
    line-height: 1.6;
}

/* 6. Featured Categories (6 Image-Based Cards) */
.corp-categories-grid {
    display: grid;
    grid-template-columns: repeat(3, 1fr);
    gap: 1.75rem;
}

.corp-category-card {
    position: relative;
    height: 230px;
    border-radius: 14px;
    overflow: hidden;
    text-decoration: none;
    display: flex;
    flex-direction: column;
    justify-content: flex-end;
    padding: 1.5rem;
    color: #FFFFFF;
    transition: all 0.3s cubic-bezier(0.165, 0.84, 0.44, 1);
    box-shadow: 0 10px 25px -5px rgba(16, 47, 70, 0.12);
}

.corp-category-card:hover {
    transform: translateY(-5px);
    box-shadow: 0 18px 35px -5px rgba(16, 47, 70, 0.22);
    text-decoration: none;
}

.corp-category-card::before {
    content: '';
    position: absolute;
    top: 0; left: 0; right: 0; bottom: 0;
    background-size: cover;
    background-position: center;
    transition: transform 0.4s ease;
    z-index: 1;
}

.corp-category-card:hover::before {
    transform: scale(1.06);
}

.corp-category-card::after {
    content: '';
    position: absolute;
    top: 0; left: 0; right: 0; bottom: 0;
    background: linear-gradient(180deg, rgba(16, 47, 70, 0.15) 0%, rgba(16, 47, 70, 0.88) 100%);
    z-index: 2;
    transition: background 0.3s ease;
}

.corp-category-card:hover::after {
    background: linear-gradient(180deg, rgba(54, 124, 137, 0.2) 0%, rgba(16, 47, 70, 0.94) 100%);
}

.corp-cat-content {
    position: relative;
    z-index: 3;
}

.corp-cat-badge {
    display: inline-block;
    background: rgba(54, 124, 137, 0.85);
    backdrop-filter: blur(4px);
    color: #FFFFFF;
    font-size: 0.75rem;
    font-weight: 700;
    padding: 0.25rem 0.65rem;
    border-radius: 20px;
    margin-bottom: 0.5rem;
}

.corp-cat-title {
    font-size: 1.35rem;
    font-weight: 700;
    color: #FFFFFF;
    margin-bottom: 0.25rem;
    display: flex;
    align-items: center;
    justify-content: space-between;
}

.corp-cat-arrow {
    width: 28px;
    height: 28px;
    background: rgba(255, 255, 255, 0.2);
    border-radius: 50%;
    display: flex;
    align-items: center;
    justify-content: center;
    font-size: 0.9rem;
    transition: all 0.2s ease;
}

.corp-category-card:hover .corp-cat-arrow {
    background: #367C89;
    transform: translateX(4px);
}

.corp-cat-desc {
    font-size: 0.85rem;
    color: #E2E8F0;
    line-height: 1.4;
    margin-bottom: 0;
}

/* Category Background Photos */
.cat-bg-design::before { background-image: url('https://images.unsplash.com/photo-1626785774573-4b799315345d?auto=format&fit=crop&w=600&q=80'); }
.cat-bg-web::before    { background-image: url('https://images.unsplash.com/photo-1498050108023-c5249f4df085?auto=format&fit=crop&w=600&q=80'); }
.cat-bg-pres::before   { background-image: url('https://images.unsplash.com/photo-1557804506-669a67965ba0?auto=format&fit=crop&w=600&q=80'); }
.cat-bg-write::before  { background-image: url('https://images.unsplash.com/photo-1455390582262-044cdead277a?auto=format&fit=crop&w=600&q=80'); }
.cat-bg-tutor::before  { background-image: url('https://images.unsplash.com/photo-1531482615713-2afd69097998?auto=format&fit=crop&w=600&q=80'); }
.cat-bg-data::before   { background-image: url('https://images.unsplash.com/photo-1460925895917-afdab827c52f?auto=format&fit=crop&w=600&q=80'); }

/* 7. Latest Tasks Cards */
.corp-tasks-grid {
    display: grid;
    grid-template-columns: repeat(3, 1fr);
    gap: 1.75rem;
}

.corp-task-card {
    background: #FFFFFF;
    border-radius: 14px;
    border: 1px solid rgba(117, 151, 168, 0.25);
    padding: 1.5rem;
    display: flex;
    flex-direction: column;
    justify-content: space-between;
    transition: all 0.25s ease;
    box-shadow: 0 4px 15px rgba(16, 47, 70, 0.05);
}

.corp-task-card:hover {
    transform: translateY(-4px);
    box-shadow: 0 15px 30px rgba(16, 47, 70, 0.12);
    border-color: #367C89;
}

.corp-task-header {
    display: flex;
    justify-content: space-between;
    align-items: center;
    margin-bottom: 0.85rem;
}

.corp-task-cat-badge {
    background: #F4F7F8;
    color: #163D57;
    font-size: 0.8rem;
    font-weight: 600;
    padding: 0.25rem 0.75rem;
    border-radius: 6px;
    border: 1px solid #E2E8F0;
}

.corp-task-status-badge {
    background: rgba(54, 124, 137, 0.15);
    color: #367C89;
    font-size: 0.75rem;
    font-weight: 700;
    padding: 0.2rem 0.6rem;
    border-radius: 20px;
    text-transform: uppercase;
}

.corp-task-title {
    font-size: 1.18rem;
    font-weight: 700;
    color: #102F46;
    line-height: 1.4;
    margin-bottom: 0.5rem;
}

.corp-task-title a {
    color: #102F46;
    text-decoration: none;
    transition: color 0.2s ease;
}

.corp-task-title a:hover {
    color: #367C89;
    text-decoration: none;
}

.corp-task-meta {
    font-size: 0.85rem;
    color: #7597A8;
    margin-bottom: 0.85rem;
}

.corp-task-meta strong {
    color: #163D57;
}

.corp-task-desc {
    font-size: 0.9rem;
    color: #475569;
    line-height: 1.55;
    margin-bottom: 1.25rem;
    display: -webkit-box;
    -webkit-line-clamp: 2;
    -webkit-box-orient: vertical;
    overflow: hidden;
}

.corp-task-skills {
    display: flex;
    flex-wrap: wrap;
    gap: 0.4rem;
    margin-bottom: 1.25rem;
}

.corp-skill-pill {
    background: #F4F7F8;
    color: #367C89;
    font-size: 0.75rem;
    font-weight: 600;
    padding: 0.2rem 0.55rem;
    border-radius: 4px;
    border: 1px solid rgba(54, 124, 137, 0.2);
}

.corp-task-footer {
    border-top: 1px solid #F1F5F9;
    padding-top: 1.15rem;
    display: flex;
    justify-content: space-between;
    align-items: center;
}

.corp-task-budget {
    font-size: 1.35rem;
    font-weight: 800;
    color: #102F46;
    letter-spacing: -0.01em;
}

.corp-task-budget span {
    font-size: 0.85rem;
    font-weight: 500;
    color: #7597A8;
    display: block;
}

.corp-btn-view-details {
    background: #102F46;
    color: #FFFFFF;
    text-decoration: none;
    font-size: 0.88rem;
    font-weight: 600;
    padding: 0.55rem 1.15rem;
    border-radius: 6px;
    transition: all 0.2s ease;
}

.corp-btn-view-details:hover {
    background: #367C89;
    color: #FFFFFF;
    text-decoration: none;
}

/* 8. Featured Split Section ("Build Your Skills. Build Your Future.") */
.corp-split-section {
    background: #FFFFFF;
    border-radius: 20px;
    padding: 4rem 3.5rem;
    margin: 5rem auto;
    max-width: 1240px;
    display: grid;
    grid-template-columns: 1fr 1.15fr;
    gap: 4rem;
    align-items: center;
    box-shadow: 0 15px 40px rgba(16, 47, 70, 0.06);
    border: 1px solid rgba(117, 151, 168, 0.2);
}

.corp-split-image-container {
    position: relative;
}

.corp-split-image {
    width: 100%;
    height: 440px;
    object-fit: cover;
    border-radius: 16px;
    box-shadow: 0 20px 40px rgba(16, 47, 70, 0.16);
    display: block;
}

.corp-split-floating-badge {
    position: absolute;
    bottom: -20px;
    right: -20px;
    background: #102F46;
    color: #FFFFFF;
    padding: 1.25rem 1.5rem;
    border-radius: 12px;
    box-shadow: 0 15px 30px rgba(16, 47, 70, 0.25);
    border: 1px solid rgba(54, 124, 137, 0.4);
    display: flex;
    align-items: center;
    gap: 1rem;
}

.corp-split-badge-num {
    font-size: 1.85rem;
    font-weight: 800;
    color: #367C89;
    line-height: 1;
}

.corp-split-badge-label {
    font-size: 0.8rem;
    color: #F4F7F8;
    line-height: 1.3;
    font-weight: 500;
}

.corp-split-content .tag {
    color: #367C89;
    font-size: 0.85rem;
    font-weight: 700;
    text-transform: uppercase;
    letter-spacing: 0.08em;
    display: inline-block;
    margin-bottom: 0.65rem;
}

.corp-split-heading {
    font-size: 2.35rem;
    font-weight: 800;
    color: #102F46;
    line-height: 1.2;
    margin-bottom: 1.25rem;
    letter-spacing: -0.02em;
}

.corp-split-desc {
    font-size: 1.05rem;
    color: #475569;
    line-height: 1.65;
    margin-bottom: 2rem;
}

.corp-split-features {
    display: flex;
    flex-direction: column;
    gap: 1.15rem;
    margin-bottom: 2.5rem;
}

.corp-feature-item {
    display: flex;
    align-items: flex-start;
    gap: 0.85rem;
}

.corp-feature-icon {
    width: 26px;
    height: 26px;
    border-radius: 50%;
    background: rgba(54, 124, 137, 0.15);
    color: #367C89;
    display: inline-flex;
    align-items: center;
    justify-content: center;
    font-size: 0.85rem;
    font-weight: 800;
    flex-shrink: 0;
    margin-top: 0.15rem;
}

.corp-feature-text {
    font-size: 0.95rem;
    color: #163D57;
    font-weight: 500;
    line-height: 1.45;
}

/* 9. How It Works Section */
.corp-steps-grid {
    display: grid;
    grid-template-columns: repeat(3, 1fr);
    gap: 2rem;
}

.corp-step-card {
    background: #FFFFFF;
    border-radius: 16px;
    padding: 2.5rem 2rem;
    text-align: center;
    border: 1px solid rgba(117, 151, 168, 0.2);
    box-shadow: 0 8px 25px rgba(16, 47, 70, 0.05);
    position: relative;
    transition: all 0.25s ease;
}

.corp-step-card:hover {
    transform: translateY(-4px);
    box-shadow: 0 16px 35px rgba(16, 47, 70, 0.1);
    border-color: #367C89;
}

.corp-step-num-badge {
    width: 58px;
    height: 58px;
    border-radius: 50%;
    background: linear-gradient(135deg, #102F46, #163D57);
    color: #FFFFFF;
    font-size: 1.45rem;
    font-weight: 800;
    display: inline-flex;
    align-items: center;
    justify-content: center;
    margin-bottom: 1.5rem;
    box-shadow: 0 8px 20px rgba(16, 47, 70, 0.2);
    border: 3px solid #FFFFFF;
    outline: 2px solid #367C89;
}

.corp-step-card:nth-child(2) .corp-step-num-badge {
    background: linear-gradient(135deg, #367C89, #7597A8);
    outline-color: #102F46;
}

.corp-step-card:nth-child(3) .corp-step-num-badge {
    background: linear-gradient(135deg, #163D57, #367C89);
    outline-color: #7597A8;
}

.corp-step-title {
    font-size: 1.3rem;
    font-weight: 700;
    color: #102F46;
    margin-bottom: 0.75rem;
}

.corp-step-desc {
    font-size: 0.95rem;
    color: #7597A8;
    line-height: 1.6;
    margin-bottom: 0;
}

/* 10. Corporate Footer */
.corp-footer {
    background: #102F46;
    color: #7597A8;
    padding: 5rem 1.5rem 2.5rem 1.5rem;
    border-top: 3px solid #367C89;
    margin-top: 6rem;
}

.corp-footer-inner {
    max-width: 1240px;
    margin: 0 auto;
    display: grid;
    grid-template-columns: 2fr 1.2fr 1.2fr 1.5fr;
    gap: 3.5rem;
    margin-bottom: 3.5rem;
}

.corp-footer-brand {
    font-size: 1.5rem;
    font-weight: 800;
    color: #FFFFFF;
    margin-bottom: 1rem;
    display: inline-flex;
    align-items: center;
    gap: 0.5rem;
}

.corp-footer-brand span {
    color: #367C89;
}

.corp-footer-text {
    font-size: 0.92rem;
    line-height: 1.65;
    color: #A0B4C0;
    margin-bottom: 1.5rem;
    max-width: 320px;
}

.corp-footer-title {
    color: #FFFFFF;
    font-size: 1.05rem;
    font-weight: 700;
    margin-bottom: 1.25rem;
    letter-spacing: -0.01em;
}

.corp-footer-links {
    list-style: none;
    padding: 0;
    margin: 0;
    display: flex;
    flex-direction: column;
    gap: 0.75rem;
}

.corp-footer-links a {
    color: #A0B4C0;
    text-decoration: none;
    font-size: 0.9rem;
    transition: all 0.2s ease;
}

.corp-footer-links a:hover {
    color: #367C89;
    padding-left: 4px;
    text-decoration: none;
}

.corp-footer-bottom {
    max-width: 1240px;
    margin: 0 auto;
    padding-top: 2rem;
    border-top: 1px solid rgba(117, 151, 168, 0.2);
    display: flex;
    justify-content: space-between;
    align-items: center;
    flex-wrap: wrap;
    gap: 1rem;
    font-size: 0.85rem;
    color: #7597A8;
}

/* 11. Responsive Media Queries */
@media (max-width: 1024px) {
    .corp-categories-grid,
    .corp-tasks-grid,
    .corp-steps-grid {
        grid-template-columns: repeat(2, 1fr);
    }
    .corp-split-section {
        grid-template-columns: 1fr;
        padding: 3rem 2rem;
        gap: 3rem;
    }
    .corp-split-image {
        height: 350px;
    }
    .corp-footer-inner {
        grid-template-columns: 1fr 1fr;
        gap: 2.5rem;
    }
}

@media (max-width: 768px) {
    .corp-hero-title {
        font-size: 2.25rem;
    }
    .corp-hero-subtitle {
        font-size: 1.05rem;
    }
    .corp-search-form {
        grid-template-columns: 1fr;
    }
    .corp-categories-grid,
    .corp-tasks-grid,
    .corp-steps-grid {
        grid-template-columns: 1fr;
    }
    .corp-nav-links {
        display: none; /* Mobile collapse */
    }
    .corp-footer-inner {
        grid-template-columns: 1fr;
    }
}
</style>

<!-- =============================================================
     1. CUSTOM CORPORATE NAVBAR
============================================================= -->
<nav class="corp-navbar">
    <div class="corp-nav-inner">
        <!-- Logo -->
        <a href="<?= BASE_URL ?>/index.php" class="corp-brand">
            <div class="corp-brand-icon">🎓</div>
            Campus<span class="teal-text">Task</span>
        </a>

        <!-- Center Links -->
        <div class="corp-nav-links">
            <a href="<?= BASE_URL ?>/index.php" class="corp-nav-link">Home</a>
            <a href="<?= BASE_URL ?>/student/tasks.php" class="corp-nav-link">Explore Tasks</a>
            <a href="#categories" class="corp-nav-link">Categories</a>
            <a href="#about" class="corp-nav-link">About</a>
        </div>

        <!-- Right User Actions -->
        <div class="corp-nav-actions">
            <?php if (!$is_logged): ?>
                <a href="<?= BASE_URL ?>/login.php" class="corp-btn-login">Login</a>
                <a href="<?= BASE_URL ?>/register.php" class="corp-btn-register">Register</a>
            <?php elseif ($user_role === 'ADMIN'): ?>
                <span class="corp-badge-role">Admin</span>
                <a href="<?= BASE_URL ?>/admin/dashboard.php" class="corp-btn-register" style="background: #163D57;">Dashboard</a>
                <a href="<?= BASE_URL ?>/logout.php" class="corp-btn-login" style="color: #7597A8;">Logout</a>
            <?php elseif ($user_role === 'STUDENT'): ?>
                <span class="corp-badge-role"><?= e($user_name) ?></span>
                <a href="<?= BASE_URL ?>/student/dashboard.php" class="corp-btn-login">Dashboard</a>
                <a href="<?= BASE_URL ?>/student/post-task.php" class="corp-btn-register">+ Post Task</a>
                <a href="<?= BASE_URL ?>/logout.php" class="corp-btn-login" style="color: #7597A8;">Logout</a>
            <?php endif; ?>
        </div>
    </div>
</nav>

<!-- =============================================================
     2. HERO SECTION
============================================================= -->
<header class="corp-hero">
    <div class="corp-hero-content">
        <div class="corp-hero-pill">
            <span>🏛️</span> University Student Freelance Network
        </div>
        <h1 class="corp-hero-title">
            Find Opportunities.<br>Share Your Skills.
        </h1>
        <p class="corp-hero-subtitle">
            A smarter way for university students to connect, collaborate and grow. Get assistance with academic slide decks, code debugging, design, or research formatting.
        </p>
        <div class="corp-hero-cta-group">
            <a href="<?= BASE_URL ?>/student/tasks.php" class="corp-btn-cta-primary">
                Explore Tasks &rarr;
            </a>
            <a href="#how-it-works" class="corp-btn-cta-secondary">
                How It Works
            </a>
        </div>
    </div>
</header>

<!-- =============================================================
     3. FLOATING SEARCH PANEL
============================================================= -->
<div class="corp-search-wrapper">
    <div class="corp-search-card">
        <form action="<?= BASE_URL ?>/student/tasks.php" method="GET" class="corp-search-form">
            <!-- Keyword Input with SVG Search Icon -->
            <div class="corp-input-wrapper">
                <svg class="corp-input-icon" width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                    <circle cx="11" cy="11" r="8"></circle>
                    <line x1="21" y1="21" x2="16.65" y2="16.65"></line>
                </svg>
                <input 
                    type="text" 
                    name="q" 
                    class="corp-search-input" 
                    placeholder="Search tasks, e.g. Python script, Presentation slides, LaTeX formatting..."
                >
            </div>

            <!-- Category Selector Dropdown -->
            <select name="category" class="corp-search-select">
                <option value="">All Categories</option>
                <?php foreach ($all_categories as $cat): ?>
                    <option value="<?= (int)$cat['category_id'] ?>">
                        <?= e($cat['category_name']) ?> (<?= (int)$cat['task_count'] ?>)
                    </option>
                <?php endforeach; ?>
            </select>

            <!-- Submit Button -->
            <button type="submit" class="corp-btn-search">
                <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                    <circle cx="11" cy="11" r="8"></circle>
                    <line x1="21" y1="21" x2="16.65" y2="16.65"></line>
                </svg>
                Search Tasks
            </button>
        </form>

        <!-- Popular Category Chips Underneath -->
        <div class="corp-search-chips">
            <strong style="color: #102F46;">Popular:</strong>
            <?php
            // Six key popular categories
            $popular_chips = [
                ['name' => 'Graphic Design',    'id' => $cat_map['graphic design']['category_id'] ?? 2],
                ['name' => 'Web Development',   'id' => $cat_map['web development']['category_id'] ?? 1],
                ['name' => 'Presentation',      'id' => $cat_map['presentation']['category_id'] ?? 3],
                ['name' => 'Writing',           'id' => $cat_map['academic writing']['category_id'] ?? 5],
                ['name' => 'Tutoring',          'id' => $cat_map['tutoring']['category_id'] ?? 6],
                ['name' => 'Data Entry',        'id' => $cat_map['data entry']['category_id'] ?? 7]
            ];
            foreach ($popular_chips as $chip):
            ?>
                <a href="<?= BASE_URL ?>/student/tasks.php?category=<?= (int)$chip['id'] ?>" class="corp-chip">
                    <?= e($chip['name']) ?>
                </a>
            <?php endforeach; ?>
        </div>
    </div>
</div>

<!-- =============================================================
     4. FEATURED CATEGORIES (6 ELEGANT IMAGE CARDS)
============================================================= -->
<section id="categories" class="corp-section">
    <div class="corp-section-header">
        <span class="corp-section-tag">Featured Disciplines</span>
        <h2 class="corp-section-title">Explore by Category</h2>
        <p class="corp-section-desc">
            Discover peer expertise or offer your skills in specialized academic and digital domains.
        </p>
    </div>

    <div class="corp-categories-grid">
        <!-- 1. Graphic Design -->
        <?php $cat_gd = $cat_map['graphic design'] ?? null; ?>
        <a href="<?= BASE_URL ?>/student/tasks.php?category=<?= (int)($cat_gd['category_id'] ?? 2) ?>" class="corp-category-card cat-bg-design">
            <div class="corp-cat-content">
                <span class="corp-cat-badge"><?= (int)($cat_gd['task_count'] ?? 1) ?> Open Tasks</span>
                <div class="corp-cat-title">
                    <span>Graphic Design</span>
                    <div class="corp-cat-arrow">&rarr;</div>
                </div>
                <p class="corp-cat-desc">Event posters, banners, vector illustrations, and slide branding.</p>
            </div>
        </a>

        <!-- 2. Web Development -->
        <?php $cat_wd = $cat_map['web development'] ?? null; ?>
        <a href="<?= BASE_URL ?>/student/tasks.php?category=<?= (int)($cat_wd['category_id'] ?? 1) ?>" class="corp-category-card cat-bg-web">
            <div class="corp-cat-content">
                <span class="corp-cat-badge"><?= (int)($cat_wd['task_count'] ?? 1) ?> Open Tasks</span>
                <div class="corp-cat-title">
                    <span>Web Development</span>
                    <div class="corp-cat-arrow">&rarr;</div>
                </div>
                <p class="corp-cat-desc">Frontend HTML/CSS/JS, responsive layouts, and student portfolio sites.</p>
            </div>
        </a>

        <!-- 3. Presentation -->
        <?php $cat_pr = $cat_map['presentation'] ?? null; ?>
        <a href="<?= BASE_URL ?>/student/tasks.php?category=<?= (int)($cat_pr['category_id'] ?? 3) ?>" class="corp-category-card cat-bg-pres">
            <div class="corp-cat-content">
                <span class="corp-cat-badge"><?= (int)($cat_pr['task_count'] ?? 1) ?> Open Tasks</span>
                <div class="corp-cat-title">
                    <span>Presentation</span>
                    <div class="corp-cat-arrow">&rarr;</div>
                </div>
                <p class="corp-cat-desc">Academic slide decks, project defense slides, and seminar pitch visuals.</p>
            </div>
        </a>

        <!-- 4. Writing -->
        <?php $cat_wr = $cat_map['academic writing'] ?? null; ?>
        <a href="<?= BASE_URL ?>/student/tasks.php?category=<?= (int)($cat_wr['category_id'] ?? 5) ?>" class="corp-category-card cat-bg-write">
            <div class="corp-cat-content">
                <span class="corp-cat-badge"><?= (int)($cat_wr['task_count'] ?? 1) ?> Open Tasks</span>
                <div class="corp-cat-title">
                    <span>Writing</span>
                    <div class="corp-cat-arrow">&rarr;</div>
                </div>
                <p class="corp-cat-desc">Research paper formatting, LaTeX typesetting, and grammar synthesis.</p>
            </div>
        </a>

        <!-- 5. Tutoring -->
        <?php $cat_tu = $cat_map['tutoring'] ?? null; ?>
        <a href="<?= BASE_URL ?>/student/tasks.php?category=<?= (int)($cat_tu['category_id'] ?? 6) ?>" class="corp-category-card cat-bg-tutor">
            <div class="corp-cat-content">
                <span class="corp-cat-badge"><?= (int)($cat_tu['task_count'] ?? 0) ?> Open Tasks</span>
                <div class="corp-cat-title">
                    <span>Tutoring</span>
                    <div class="corp-cat-arrow">&rarr;</div>
                </div>
                <p class="corp-cat-desc">Peer-to-peer concept reviews, lab assistance, and exam preparations.</p>
            </div>
        </a>

        <!-- 6. Data Entry -->
        <?php $cat_de = $cat_map['data entry'] ?? null; ?>
        <a href="<?= BASE_URL ?>/student/tasks.php?category=<?= (int)($cat_de['category_id'] ?? 7) ?>" class="corp-category-card cat-bg-data">
            <div class="corp-cat-content">
                <span class="corp-cat-badge"><?= (int)($cat_de['task_count'] ?? 0) ?> Open Tasks</span>
                <div class="corp-cat-title">
                    <span>Data Entry</span>
                    <div class="corp-cat-arrow">&rarr;</div>
                </div>
                <p class="corp-cat-desc">CSV cleaning, spreadsheet analysis, survey input, and formatting.</p>
            </div>
        </a>
    </div>
</section>

<!-- =============================================================
     5. LATEST TASKS (PREMIUM WHITE TASK CARDS)
============================================================= -->
<section class="corp-section">
    <div style="display: flex; justify-content: space-between; align-items: flex-end; flex-wrap: wrap; gap: 1rem; margin-bottom: 2.5rem;">
        <div>
            <span class="corp-section-tag">Marketplace Listings</span>
            <h2 class="corp-section-title" style="margin-bottom: 0.25rem;">Latest Available Tasks</h2>
            <p class="corp-section-desc" style="margin-bottom: 0;">Recently posted opportunities ready for student proposals</p>
        </div>
        <a href="<?= BASE_URL ?>/student/tasks.php" class="corp-btn-cta-primary" style="padding: 0.65rem 1.4rem; font-size: 0.95rem;">
            View All Tasks &rarr;
        </a>
    </div>

    <?php if (empty($latest_tasks)): ?>
        <div style="background: #FFFFFF; border-radius: 14px; text-align: center; padding: 3rem 1.5rem; border: 2px dashed #E2E8F0;">
            <p style="color: #7597A8; font-size: 1.05rem; margin-bottom: 1rem;">
                No open tasks available at the moment.
            </p>
            <?php if ($is_logged && $user_role === 'STUDENT'): ?>
                <a href="<?= BASE_URL ?>/student/post-task.php" class="corp-btn-cta-primary">
                    Post the First Task
                </a>
            <?php endif; ?>
        </div>
    <?php else: ?>
        <div class="corp-tasks-grid">
            <?php foreach ($latest_tasks as $t): ?>
                <?php
                $skills_array = !empty($t['skills_list']) ? explode('||', $t['skills_list']) : [];
                ?>
                <div class="corp-task-card">
                    <div>
                        <!-- Header Badges -->
                        <div class="corp-task-header">
                            <span class="corp-task-cat-badge">
                                <?= e($t['category_name']) ?>
                            </span>
                            <span class="corp-task-status-badge">
                                <?= e($t['status']) ?>
                            </span>
                        </div>

                        <!-- Title -->
                        <h3 class="corp-task-title">
                            <a href="<?= BASE_URL ?>/student/task-details.php?id=<?= (int)$t['task_id'] ?>">
                                <?= e($t['title']) ?>
                            </a>
                        </h3>

                        <!-- Poster & Meta -->
                        <div class="corp-task-meta">
                            By <strong><?= e($t['poster_name']) ?></strong> &bull; <?= date('M d, Y', strtotime($t['created_at'])) ?>
                        </div>

                        <!-- Snippet -->
                        <p class="corp-task-desc">
                            <?= e($t['description']) ?>
                        </p>

                        <!-- Required Skills Tags -->
                        <?php if (!empty($skills_array)): ?>
                            <div class="corp-task-skills">
                                <?php foreach (array_slice($skills_array, 0, 3) as $sk): ?>
                                    <span class="corp-skill-pill">
                                        <?= e($sk) ?>
                                    </span>
                                <?php endforeach; ?>
                            </div>
                        <?php endif; ?>
                    </div>

                    <!-- Card Footer: Budget & Details Button -->
                    <div class="corp-task-footer">
                        <div class="corp-task-budget">
                            $<?= number_format((float)$t['budget'], 2) ?>
                            <span>Due <?= date('M d', strtotime($t['deadline'])) ?></span>
                        </div>
                        <a href="<?= BASE_URL ?>/student/task-details.php?id=<?= (int)$t['task_id'] ?>" class="corp-btn-view-details">
                            View Details
                        </a>
                    </div>
                </div>
            <?php endforeach; ?>
        </div>
    <?php endif; ?>
</section>

<!-- =============================================================
     6. FEATURED SECTION (SPLIT LAYOUT)
============================================================= -->
<section id="about" class="corp-split-section">
    <!-- Left Column: Image with Floating Stat Badge -->
    <div class="corp-split-image-container">
        <img 
            src="https://images.unsplash.com/photo-1522202176988-66273c2fd55f?auto=format&fit=crop&w=1000&q=80" 
            alt="University students collaborating" 
            class="corp-split-image"
        >
        <div class="corp-split-floating-badge">
            <div class="corp-split-badge-num">100%</div>
            <div class="corp-split-badge-label">
                <strong>Campus Verified</strong><br>Peer Collaborations
            </div>
        </div>
    </div>

    <!-- Right Column: Heading & Value Proposition -->
    <div class="corp-split-content">
        <span class="tag">WHY CAMPUSTASK</span>
        <h2 class="corp-split-heading">
            Build Your Skills.<br>Build Your Future.
        </h2>
        <p class="corp-split-desc">
            CampusTask connects university students to turn academic challenges into hands-on freelance collaboration. Gain verified experience, sharpen technical competencies, and build a lasting reputation on campus.
        </p>

        <div class="corp-split-features">
            <div class="corp-feature-item">
                <div class="corp-feature-icon">&#10003;</div>
                <div class="corp-feature-text">
                    <strong>Direct Peer-to-Peer Marketplace:</strong> Connect directly with fellow students across departments without middlemen or bureaucracy.
                </div>
            </div>

            <div class="corp-feature-item">
                <div class="corp-feature-icon">&#10003;</div>
                <div class="corp-feature-text">
                    <strong>Zero Platform Fees:</strong> 100% student-focused mission with transparent budget agreements and zero hidden platform cuts.
                </div>
            </div>

            <div class="corp-feature-item">
                <div class="corp-feature-icon">&#10003;</div>
                <div class="corp-feature-text">
                    <strong>Verified Bilateral Reviews:</strong> Build a trusted academic collaboration profile through authenticated 1 to 5 star ratings.
                </div>
            </div>
        </div>

        <a href="<?= BASE_URL ?>/student/tasks.php" class="corp-btn-cta-primary">
            Explore Marketplace &rarr;
        </a>
    </div>
</section>

<!-- =============================================================
     7. HOW IT WORKS (THREE-STEP WORKFLOW)
============================================================= -->
<section id="how-it-works" class="corp-section">
    <div class="corp-section-header">
        <span class="corp-section-tag">Simple & Transparent</span>
        <h2 class="corp-section-title">How It Works</h2>
        <p class="corp-section-desc">
            A structured three-step collaboration workflow backed by atomic database transactions.
        </p>
    </div>

    <div class="corp-steps-grid">
        <!-- Step 1 -->
        <div class="corp-step-card">
            <div class="corp-step-num-badge">1</div>
            <h3 class="corp-step-title">Post a Task</h3>
            <p class="corp-step-desc">
                Outline your academic requirements, project category, target budget, deadline, and the specific skill tags required for execution.
            </p>
        </div>

        <!-- Step 2 -->
        <div class="corp-step-card">
            <div class="corp-step-num-badge">2</div>
            <h3 class="corp-step-title">Apply for Tasks</h3>
            <p class="corp-step-desc">
                Peers review listings, submit custom proposals, and negotiate rates. The task owner selects one applicant in an atomic database transaction.
            </p>
        </div>

        <!-- Step 3 -->
        <div class="corp-step-card">
            <div class="corp-step-num-badge">3</div>
            <h3 class="corp-step-title">Complete & Review</h3>
            <p class="corp-step-desc">
                Once work is delivered, the owner marks the task as complete. Both collaborator and owner exchange verified 1–5 star reviews and feedback.
            </p>
        </div>
    </div>
</section>

<!-- =============================================================
     8. PROFESSIONAL DARK NAVY FOOTER
============================================================= -->
<footer class="corp-footer">
    <div class="corp-footer-inner">
        <!-- Col 1: Brand Info -->
        <div>
            <div class="corp-footer-brand">
                🎓 Campus<span>Task</span>
            </div>
            <p class="corp-footer-text">
                A university-based student freelance marketplace empowering academic collaboration, peer assistance, and technical skill development.
            </p>
            <div style="font-size: 0.85rem; color: #A0B4C0;">
                Campus Platform &bull; CSE 2291 DBMS
            </div>
        </div>

        <!-- Col 2: Navigation Links -->
        <div>
            <h4 class="corp-footer-title">Navigation</h4>
            <ul class="corp-footer-links">
                <li><a href="<?= BASE_URL ?>/index.php">Home</a></li>
                <li><a href="<?= BASE_URL ?>/student/tasks.php">Explore Tasks</a></li>
                <li><a href="#categories">Categories</a></li>
                <li><a href="#about">About Platform</a></li>
                <li><a href="#how-it-works">How It Works</a></li>
            </ul>
        </div>

        <!-- Col 3: Student Tools -->
        <div>
            <h4 class="corp-footer-title">Student Portal</h4>
            <ul class="corp-footer-links">
                <li><a href="<?= BASE_URL ?>/student/post-task.php">Post a Task</a></li>
                <li><a href="<?= BASE_URL ?>/student/dashboard.php">Student Dashboard</a></li>
                <li><a href="<?= BASE_URL ?>/student/my-applications.php">My Applications</a></li>
                <li><a href="<?= BASE_URL ?>/student/profile.php">Academic Profile</a></li>
                <li><a href="<?= BASE_URL ?>/register.php">Create Account</a></li>
            </ul>
        </div>

        <!-- Col 4: Academic Project Metadata -->
        <div>
            <h4 class="corp-footer-title">Project Information</h4>
            <p style="font-size: 0.88rem; line-height: 1.6; color: #A0B4C0; margin-bottom: 0.85rem;">
                <strong>Course:</strong> CSE 2291 — Database Management System (DBMS)<br>
                <strong>Architecture:</strong> Plain PHP 8 (PDO), MySQL / MariaDB, Vanilla CSS3 & JS.<br>
                <strong>Role Assignment:</strong> Person A (Task Side)
            </p>
            <div style="display: inline-block; background: rgba(54, 124, 137, 0.2); padding: 0.35rem 0.75rem; border-radius: 6px; border: 1px solid rgba(54, 124, 137, 0.4); font-size: 0.8rem; color: #7597A8;">
                ✓ 8 Core Normalized Tables (1NF &ndash; 3NF)
            </div>
        </div>
    </div>

    <!-- Bottom Bar -->
    <div class="corp-footer-bottom">
        <div>
            &copy; <?= date('Y') ?> CampusTask Marketplace. Developed for University Academic Demonstration.
        </div>
        <div style="display: flex; gap: 1.5rem;">
            <a href="<?= BASE_URL ?>/student/tasks.php" style="color: #7597A8; text-decoration: none;">Browse All Tasks</a>
            <a href="<?= BASE_URL ?>/login.php" style="color: #7597A8; text-decoration: none;">Portal Login</a>
        </div>
    </div>
</footer>

<?php
// Include shared footer layout (Person B)
require_once __DIR__ . '/includes/footer.php';
?>
