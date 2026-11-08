-- =====================================================================
-- CampusTask Database: 02_schema_workflow.sql
-- Course: CSE 2291 - Database Management System (DBMS)
-- Owner: Person B (Workflow Side)
-- Description: Schema for Skills, Task-Skills (M:N), Applications, Reviews
-- Import Order: Must be imported AFTER 01_schema_core.sql
-- =====================================================================

USE `campustask`;

-- -------------------------------------------------------------
-- Table 5: skills
-- Stores reusable skills tagged on tasks
-- -------------------------------------------------------------
CREATE TABLE IF NOT EXISTS `skills` (
    `skill_id` INT AUTO_INCREMENT PRIMARY KEY,
    `skill_name` VARCHAR(100) NOT NULL UNIQUE,
    `description` VARCHAR(255) NULL,
    `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- -------------------------------------------------------------
-- Table 6: task_skills (Junction Table: M:N Relationship)
-- Composite primary key (task_id, skill_id) satisfies 1NF & 2NF
-- -------------------------------------------------------------
CREATE TABLE IF NOT EXISTS `task_skills` (
    `task_id` INT NOT NULL,
    `skill_id` INT NOT NULL,
    PRIMARY KEY (`task_id`, `skill_id`),
    CONSTRAINT `fk_task_skills_task` 
        FOREIGN KEY (`task_id`) REFERENCES `tasks` (`task_id`) 
        ON DELETE CASCADE ON UPDATE CASCADE,
    CONSTRAINT `fk_task_skills_skill` 
        FOREIGN KEY (`skill_id`) REFERENCES `skills` (`skill_id`) 
        ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- -------------------------------------------------------------
-- Table 7: applications
-- Tracks task proposals submitted by students
-- Enforces: unique application per student per task, non-negative budget
-- -------------------------------------------------------------
CREATE TABLE IF NOT EXISTS `applications` (
    `application_id` INT AUTO_INCREMENT PRIMARY KEY,
    `task_id` INT NOT NULL,
    `applicant_id` INT NOT NULL,
    `proposal` TEXT NOT NULL,
    `proposed_budget` DECIMAL(10, 2) NOT NULL DEFAULT 0.00,
    `status` ENUM('PENDING', 'ACCEPTED', 'REJECTED', 'WITHDRAWN') NOT NULL DEFAULT 'PENDING',
    `applied_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    CONSTRAINT `uq_task_applicant` UNIQUE (`task_id`, `applicant_id`),
    CONSTRAINT `chk_proposed_budget_non_negative` CHECK (`proposed_budget` >= 0),
    CONSTRAINT `fk_applications_task` 
        FOREIGN KEY (`task_id`) REFERENCES `tasks` (`task_id`) 
        ON DELETE CASCADE ON UPDATE CASCADE,
    CONSTRAINT `fk_applications_applicant` 
        FOREIGN KEY (`applicant_id`) REFERENCES `users` (`user_id`) 
        ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- -------------------------------------------------------------
-- Table 8: reviews
-- Bilateral peer evaluations for completed tasks
-- Enforces: rating between 1 and 5, one review per reviewer per task
-- -------------------------------------------------------------
CREATE TABLE IF NOT EXISTS `reviews` (
    `review_id` INT AUTO_INCREMENT PRIMARY KEY,
    `task_id` INT NOT NULL,
    `reviewer_id` INT NOT NULL,
    `reviewee_id` INT NOT NULL,
    `rating` TINYINT NOT NULL,
    `comment` TEXT NOT NULL,
    `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    CONSTRAINT `uq_task_reviewer` UNIQUE (`task_id`, `reviewer_id`),
    CONSTRAINT `chk_rating_range` CHECK (`rating` >= 1 AND `rating` <= 5),
    CONSTRAINT `fk_reviews_task` 
        FOREIGN KEY (`task_id`) REFERENCES `tasks` (`task_id`) 
        ON DELETE CASCADE ON UPDATE CASCADE,
    CONSTRAINT `fk_reviews_reviewer` 
        FOREIGN KEY (`reviewer_id`) REFERENCES `users` (`user_id`) 
        ON DELETE CASCADE ON UPDATE CASCADE,
    CONSTRAINT `fk_reviews_reviewee` 
        FOREIGN KEY (`reviewee_id`) REFERENCES `users` (`user_id`) 
        ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
