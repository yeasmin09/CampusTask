-- =====================================================================
-- CampusTask Database: a_triggers.sql
-- Course: CSE 2291 - Database Management System (DBMS)
-- Owner: Person A (Task Side)
-- Description: Task Integrity & Audit Trigger:
--              1. before_task_update
-- =====================================================================

USE `campustask`;

-- =============================================================
-- Trigger: before_task_update
-- Table: tasks
-- Event: BEFORE UPDATE
-- Enforces:
--  1. Budget must remain non-negative (>= 0).
--  2. Automatically updates the `updated_at` audit timestamp to current time.
-- =============================================================

DROP TRIGGER IF EXISTS `before_task_update`;

DELIMITER //

CREATE TRIGGER `before_task_update`
BEFORE UPDATE ON `tasks`
FOR EACH ROW
BEGIN
    -- 1. Business Rule: Budget cannot be negative
    IF NEW.budget < 0 THEN
        SIGNAL SQLSTATE '45000'
            SET MESSAGE_TEXT = 'Trigger validation error: Task budget cannot be negative.';
    END IF;

    -- 2. Audit Trail: Automatically refresh updated_at timestamp
    SET NEW.updated_at = NOW();
END //

DELIMITER ;
