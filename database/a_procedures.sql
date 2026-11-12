-- =====================================================================
-- CampusTask Database: a_procedures.sql
-- Course: CSE 2291 - Database Management System (DBMS)
-- Owner: Person A (Task Side)
-- Description: Stored Procedure & Stored Function:
--              1. GetStudentTasks(IN p_user_id INT) [Procedure]
--              2. GetAverageTaskBudget() RETURNS DECIMAL(10,2) [Function]
-- =====================================================================

USE `campustask`;

-- =============================================================
-- 1. STORED PROCEDURE: GetStudentTasks
-- Parameters: IN p_user_id INT
-- Description: Retrieves all tasks posted by a given student along
--              with category name, application count, and current status.
-- =============================================================

DROP PROCEDURE IF EXISTS `GetStudentTasks`;

DELIMITER //

CREATE PROCEDURE `GetStudentTasks`(IN `p_user_id` INT)
BEGIN
    SELECT 
        t.task_id,
        t.title,
        c.category_name,
        t.budget,
        t.deadline,
        t.status,
        t.created_at,
        COUNT(a.application_id) AS total_applications
    FROM `tasks` t
    JOIN `categories` c ON t.category_id = c.category_id
    LEFT JOIN `applications` a ON t.task_id = a.task_id
    WHERE t.poster_id = p_user_id
    GROUP BY t.task_id, t.title, c.category_name, t.budget, t.deadline, t.status, t.created_at
    ORDER BY t.created_at DESC;
END //

DELIMITER ;


-- =============================================================
-- 2. STORED FUNCTION: GetAverageTaskBudget
-- Parameters: None
-- Returns: DECIMAL(10, 2)
-- Description: Calculates and returns the average budget of all
--              non-cancelled tasks across the marketplace.
-- =============================================================

DROP FUNCTION IF EXISTS `GetAverageTaskBudget`;

DELIMITER //

CREATE FUNCTION `GetAverageTaskBudget`() 
RETURNS DECIMAL(10, 2)
DETERMINISTIC
READS SQL DATA
BEGIN
    DECLARE v_avg DECIMAL(10, 2);

    SELECT COALESCE(ROUND(AVG(`budget`), 2), 0.00) INTO v_avg
    FROM `tasks`
    WHERE `status` != 'CANCELLED';

    RETURN v_avg;
END //

DELIMITER ;
