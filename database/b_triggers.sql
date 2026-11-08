-- =====================================================================
-- CampusTask Database: b_triggers.sql
-- Course: CSE 2291 - Database Management System (DBMS)
-- Owner: Person B (Workflow Side)
-- Description: Business Rule Enforcement Triggers
--              1. before_review_insert
--              2. before_application_insert
-- =====================================================================

USE `campustask`;

-- -------------------------------------------------------------
-- Trigger 1: before_review_insert
-- Enforces:
-- 1. Rating must be an integer between 1 and 5.
-- 2. The task being reviewed MUST have status = 'COMPLETED'.
-- -------------------------------------------------------------
DROP TRIGGER IF EXISTS `before_review_insert`;

DELIMITER //

CREATE TRIGGER `before_review_insert`
BEFORE INSERT ON `reviews`
FOR EACH ROW
BEGIN
    DECLARE v_task_status VARCHAR(20);

    -- 1. Check Rating Range
    IF NEW.rating < 1 OR NEW.rating > 5 THEN
        SIGNAL SQLSTATE '45000'
            SET MESSAGE_TEXT = 'Trigger validation error: Rating must be an integer between 1 and 5.';
    END IF;

    -- 2. Check Task Status
    SELECT `status` INTO v_task_status
    FROM `tasks`
    WHERE `task_id` = NEW.task_id
    LIMIT 1;

    IF v_task_status IS NULL THEN
        SIGNAL SQLSTATE '45000'
            SET MESSAGE_TEXT = 'Trigger validation error: Associated task does not exist.';
    ELSEIF v_task_status != 'COMPLETED' THEN
        SIGNAL SQLSTATE '45000'
            SET MESSAGE_TEXT = 'Trigger validation error: Reviews can only be submitted for COMPLETED tasks.';
    END IF;
END //

DELIMITER ;


-- -------------------------------------------------------------
-- Trigger 2: before_application_insert
-- Enforces:
-- 1. Applications can only be submitted for tasks with status = 'OPEN'.
-- 2. A student cannot apply to their own posted task.
-- -------------------------------------------------------------
DROP TRIGGER IF EXISTS `before_application_insert`;

DELIMITER //

CREATE TRIGGER `before_application_insert`
BEFORE INSERT ON `applications`
FOR EACH ROW
BEGIN
    DECLARE v_task_status VARCHAR(20);
    DECLARE v_poster_id INT;

    -- Retrieve task status and poster ID
    SELECT `status`, `poster_id` INTO v_task_status, v_poster_id
    FROM `tasks`
    WHERE `task_id` = NEW.task_id
    LIMIT 1;

    IF v_task_status IS NULL THEN
        SIGNAL SQLSTATE '45000'
            SET MESSAGE_TEXT = 'Trigger validation error: Target task does not exist.';
    ELSEIF v_task_status != 'OPEN' THEN
        SIGNAL SQLSTATE '45000'
            SET MESSAGE_TEXT = 'Trigger validation error: Applications can only be submitted to OPEN tasks.';
    ELSEIF NEW.applicant_id = v_poster_id THEN
        SIGNAL SQLSTATE '45000'
            SET MESSAGE_TEXT = 'Trigger validation error: A task poster cannot apply to their own task.';
    END IF;
END //

DELIMITER ;
