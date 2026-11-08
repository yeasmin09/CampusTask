-- =====================================================================
-- CampusTask Database: b_queries.sql
-- Course: CSE 2291 - Database Management System (DBMS)
-- Owner: Person B (Workflow Side)
-- Description: Demonstrates INNER JOIN, LEFT JOIN, Multi-Table Double JOIN,
--              3 Meaningful Subqueries, Aggregates, GROUP BY & HAVING.
-- =====================================================================

USE `campustask`;

-- =====================================================================
-- 1. INNER JOIN Demonstration
-- Fetch all accepted freelance assignments with task details and applicant name
-- =====================================================================
SELECT 
    a.application_id,
    t.task_id,
    t.title AS task_title,
    t.budget AS original_budget,
    a.proposed_budget,
    u.full_name AS freelancer_name,
    u.email AS freelancer_email,
    a.applied_at
FROM applications a
INNER JOIN tasks t ON a.task_id = t.task_id
INNER JOIN users u ON a.applicant_id = u.user_id
WHERE a.status = 'ACCEPTED'
ORDER BY a.applied_at DESC;


-- =====================================================================
-- 2. LEFT JOIN Demonstration
-- List all registered students with their profile info and application counts.
-- (Shows students even if they have not created a profile or applied yet)
-- =====================================================================
SELECT 
    u.user_id,
    u.full_name,
    u.email,
    p.student_id,
    p.department,
    COUNT(a.application_id) AS total_applications_submitted
FROM users u
LEFT JOIN profiles p ON u.user_id = p.user_id
LEFT JOIN applications a ON u.user_id = a.applicant_id
WHERE u.role = 'STUDENT'
GROUP BY u.user_id, u.full_name, u.email, p.student_id, p.department
ORDER BY total_applications_submitted DESC, u.full_name ASC;


-- =====================================================================
-- 3. Multi-Table Join (Joining `users` Twice)
-- Mandatory Viva Requirement: Joins `users` table once as the reviewer,
-- and a second time as the reviewee, alongside the associated task.
-- =====================================================================
SELECT 
    r.review_id,
    t.task_id,
    t.title AS task_title,
    t.status AS task_status,
    reviewer.user_id AS reviewer_id,
    reviewer.full_name AS reviewer_name,
    reviewee.user_id AS reviewee_id,
    reviewee.full_name AS reviewee_name,
    r.rating,
    r.comment,
    r.created_at AS review_date
FROM reviews r
JOIN tasks t ON r.task_id = t.task_id
JOIN users reviewer ON r.reviewer_id = reviewer.user_id
JOIN users reviewee ON r.reviewee_id = reviewee.user_id
ORDER BY r.created_at DESC;


-- =====================================================================
-- 4. Subquery 1: Tasks with Budget Above Average
-- Finds tasks offering a budget greater than the average across all non-cancelled tasks
-- =====================================================================
SELECT 
    task_id,
    title,
    budget,
    status,
    created_at
FROM tasks
WHERE budget > (
    SELECT AVG(budget) 
    FROM tasks 
    WHERE status != 'CANCELLED'
)
ORDER BY budget DESC;


-- =====================================================================
-- 5. Subquery 2: Students who applied at least once (using `IN`)
-- Identifies all active students who have actively participated in applications
-- =====================================================================
SELECT 
    user_id,
    full_name,
    email,
    created_at
FROM users
WHERE role = 'STUDENT' 
  AND user_id IN (
      SELECT DISTINCT applicant_id 
      FROM applications
  )
ORDER BY full_name ASC;


-- =====================================================================
-- 6. Subquery 3: Tasks with No Applications (using `NOT EXISTS`)
-- Identifies posted tasks that have received zero proposals so far
-- =====================================================================
SELECT 
    t.task_id,
    t.title,
    t.budget,
    t.deadline,
    t.status
FROM tasks t
WHERE NOT EXISTS (
    SELECT 1 
    FROM applications a 
    WHERE a.task_id = t.task_id
)
ORDER BY t.created_at DESC;


-- =====================================================================
-- 7. Aggregates with GROUP BY & HAVING
-- Categories that have more than 0 tasks and total budget exceeding $50
-- =====================================================================
SELECT 
    c.category_id,
    c.category_name,
    COUNT(t.task_id) AS total_tasks,
    AVG(t.budget) AS average_budget,
    SUM(t.budget) AS total_category_budget
FROM categories c
INNER JOIN tasks t ON c.category_id = t.category_id
GROUP BY c.category_id, c.category_name
HAVING COUNT(t.task_id) >= 1 AND total_category_budget >= 50.00
ORDER BY total_category_budget DESC;
