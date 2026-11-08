-- =====================================================================
-- CampusTask Database: b_procedures.sql
-- Course: CSE 2291 - Database Management System (DBMS)
-- Owner: Person B (Workflow Side)
-- Description: Stored Procedure GetTaskStatistics
-- =====================================================================

USE `campustask`;

DROP PROCEDURE IF EXISTS `GetTaskStatistics`;

DELIMITER //

-- -------------------------------------------------------------
-- Stored Procedure: GetTaskStatistics
-- Parameters: IN p_task_id INT
-- Returns: Task title, status, and breakdown of applications by status
-- -------------------------------------------------------------
CREATE PROCEDURE `GetTaskStatistics`(IN `p_task_id` INT)
BEGIN
    SELECT 
        t.task_id,
        t.title AS task_title,
        t.status AS task_status,
        COUNT(a.application_id) AS total_applications,
        COALESCE(SUM(CASE WHEN a.status = 'PENDING' THEN 1 ELSE 0 END), 0) AS pending_applications,
        COALESCE(SUM(CASE WHEN a.status = 'ACCEPTED' THEN 1 ELSE 0 END), 0) AS accepted_applications,
        COALESCE(SUM(CASE WHEN a.status = 'REJECTED' THEN 1 ELSE 0 END), 0) AS rejected_applications,
        COALESCE(SUM(CASE WHEN a.status = 'WITHDRAWN' THEN 1 ELSE 0 END), 0) AS withdrawn_applications
    FROM tasks t
    LEFT JOIN applications a ON t.task_id = a.task_id
    WHERE t.task_id = p_task_id
    GROUP BY t.task_id, t.title, t.status;
END //

DELIMITER ;
