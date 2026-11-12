-- =====================================================================
-- CampusTask Database: a_queries.sql
-- Course: CSE 2291 - Database Management System (DBMS)
-- Owner: Person A (Task Side)
-- Description: Practical SQL Demonstrations for Viva Defense:
--              1. Basic CRUD (SELECT, INSERT, UPDATE, DELETE)
--              2. Filtering (WHERE, LIKE, BETWEEN, IN, IS NULL)
--              3. Multi-Column Sorting (ORDER BY)
--              4. Aggregate Functions (COUNT, SUM, AVG, MIN, MAX)
--              5. Grouping with Aggregates & Filtering (GROUP BY, HAVING)
-- =====================================================================

USE `campustask`;

-- =============================================================
-- 1. BASIC CRUD ON TASKS
-- =============================================================

-- [C] Create: Insert a new task listing
INSERT INTO `tasks` (`poster_id`, `category_id`, `title`, `description`, `budget`, `deadline`, `status`, `created_at`)
VALUES (2, 4, 'Build Binary Search Tree in C++', 'Need help implementing an AVL balanced tree lab assignment with insertion and rotation methods.', 40.00, DATE_ADD(CURRENT_DATE, INTERVAL 6 DAY), 'OPEN', NOW());

-- [R] Read: Fetch task details with poster and category info
SELECT 
    t.task_id,
    t.title,
    t.description,
    t.budget,
    t.deadline,
    t.status,
    u.full_name AS poster_name,
    u.email AS poster_email,
    c.category_name
FROM `tasks` t
JOIN `users` u ON t.poster_id = u.user_id
JOIN `categories` c ON t.category_id = c.category_id
WHERE t.task_id = 1;

-- [U] Update: Modify budget and description of an open task
UPDATE `tasks`
SET `budget` = 35.00,
    `description` = CONCAT(`description`, ' [Updated: Deliverable slides in .pptx and .pdf]'),
    `updated_at` = NOW()
WHERE `task_id` = 1 AND `status` = 'OPEN';

-- [D] Delete: Remove a newly posted task (using subquery to target the demo inserted task)
DELETE FROM `tasks`
WHERE `title` = 'Build Binary Search Tree in C++' AND `status` = 'OPEN';


-- =============================================================
-- 2. FILTERING QUERIES (WHERE, LIKE, BETWEEN, IN, IS NULL)
-- =============================================================

-- 2.1 Keyword Search using LIKE
SELECT `task_id`, `title`, `budget`, `status`
FROM `tasks`
WHERE `title` LIKE '%Slide%' OR `description` LIKE '%Presentation%';

-- 2.2 Range filtering using BETWEEN
SELECT `task_id`, `title`, `budget`, `deadline`
FROM `tasks`
WHERE `budget` BETWEEN 25.00 AND 50.00;

-- 2.3 Set membership using IN (Tasks belonging to specific categories)
SELECT t.`task_id`, t.`title`, c.`category_name`, t.`budget`
FROM `tasks` t
JOIN `categories` c ON t.`category_id` = c.`category_id`
WHERE c.`category_name` IN ('Web Development', 'Programming', 'Presentation');

-- 2.4 Checking for NULL values (Users who have not set a bio in their profile)
SELECT u.`user_id`, u.`full_name`, u.`email`, p.`bio`
FROM `users` u
LEFT JOIN `profiles` p ON u.`user_id` = p.`user_id`
WHERE p.`bio` IS NULL OR p.`bio` = '';


-- =============================================================
-- 3. MULTI-COLUMN SORTING (ORDER BY)
-- =============================================================

-- Order tasks primarily by budget (highest first), secondarily by deadline (nearest first)
SELECT 
    `task_id`, 
    `title`, 
    `budget`, 
    `deadline`, 
    `status`
FROM `tasks`
WHERE `status` != 'CANCELLED'
ORDER BY `budget` DESC, `deadline` ASC;


-- =============================================================
-- 4. AGGREGATE FUNCTIONS (COUNT, SUM, AVG, MIN, MAX)
-- =============================================================

-- Calculate aggregate marketplace indicators on active tasks
SELECT 
    COUNT(*) AS total_active_tasks,
    COALESCE(SUM(`budget`), 0) AS total_posted_budget,
    COALESCE(ROUND(AVG(`budget`), 2), 0) AS average_budget,
    COALESCE(MIN(`budget`), 0) AS lowest_budget,
    COALESCE(MAX(`budget`), 0) AS highest_budget
FROM `tasks`
WHERE `status` != 'CANCELLED';


-- =============================================================
-- 5. GROUPING & FILTERING (GROUP BY & HAVING)
-- =============================================================

-- 5.1 GROUP BY: Count tasks and average budget per category
SELECT 
    c.`category_name`,
    COUNT(t.`task_id`) AS task_count,
    COALESCE(ROUND(AVG(t.`budget`), 2), 0) AS average_category_budget
FROM `categories` c
LEFT JOIN `tasks` t ON c.`category_id` = t.`category_id`
GROUP BY c.`category_id`, c.`category_name`
ORDER BY task_count DESC;

-- 5.2 GROUP BY with HAVING: Categories with at least 1 posted task and average budget >= 30
SELECT 
    c.`category_name`,
    COUNT(t.`task_id`) AS task_count,
    ROUND(AVG(t.`budget`), 2) AS avg_budget
FROM `categories` c
JOIN `tasks` t ON c.`category_id` = t.`category_id`
GROUP BY c.`category_id`, c.`category_name`
HAVING COUNT(t.`task_id`) >= 1 AND AVG(t.`budget`) >= 30.00
ORDER BY avg_budget DESC;
