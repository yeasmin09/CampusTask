-- =====================================================================
-- CampusTask Database: 01_schema_core.sql
-- Course: CSE 2291 - Database Management System (DBMS)
-- Owner: Person A (Task Side)
-- Description: Core Schema - Database, Users, Profiles, Categories, Tasks
-- Import Order: Step 1 (Must be imported FIRST before 02_schema_workflow.sql)
-- =====================================================================

DROP DATABASE IF EXISTS `campustask`;
CREATE DATABASE `campustask` DEFAULT CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
USE `campustask`;

-- -------------------------------------------------------------
-- Table 1: users
-- Core authentication table for Students and Admins
-- -------------------------------------------------------------
CREATE TABLE IF NOT EXISTS `users` (
    `user_id` INT AUTO_INCREMENT PRIMARY KEY,
    `full_name` VARCHAR(100) NOT NULL,
    `email` VARCHAR(150) NOT NULL UNIQUE,
    `password_hash` VARCHAR(255) NOT NULL,
    `role` ENUM('STUDENT', 'ADMIN') NOT NULL DEFAULT 'STUDENT',
    `account_status` ENUM('ACTIVE', 'DEACTIVATED') NOT NULL DEFAULT 'ACTIVE',
    `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- -------------------------------------------------------------
-- Table 2: profiles
-- One-to-One relationship with users table
-- Stores extended student academic information
-- -------------------------------------------------------------
CREATE TABLE IF NOT EXISTS `profiles` (
    `profile_id` INT AUTO_INCREMENT PRIMARY KEY,
    `user_id` INT NOT NULL UNIQUE,
    `student_id` VARCHAR(50) NULL,
    `department` VARCHAR(100) NULL,
    `bio` TEXT NULL,
    `profile_image` VARCHAR(255) NULL,
    `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    CONSTRAINT `fk_profiles_user` 
        FOREIGN KEY (`user_id`) REFERENCES `users` (`user_id`) 
        ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- -------------------------------------------------------------
-- Table 3: categories
-- Task classification (e.g., Programming, Presentation, Writing)
-- -------------------------------------------------------------
CREATE TABLE IF NOT EXISTS `categories` (
    `category_id` INT AUTO_INCREMENT PRIMARY KEY,
    `category_name` VARCHAR(100) NOT NULL UNIQUE,
    `description` TEXT NULL,
    `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- -------------------------------------------------------------
-- Table 4: tasks
-- Core task marketplace listings posted by students
-- Enforces: non-negative budget, valid foreign keys
-- -------------------------------------------------------------
CREATE TABLE IF NOT EXISTS `tasks` (
    `task_id` INT AUTO_INCREMENT PRIMARY KEY,
    `poster_id` INT NOT NULL,
    `category_id` INT NOT NULL,
    `title` VARCHAR(200) NOT NULL,
    `description` TEXT NOT NULL,
    `budget` DECIMAL(10, 2) NOT NULL DEFAULT 0.00,
    `deadline` DATE NOT NULL,
    `status` ENUM('OPEN', 'IN_PROGRESS', 'COMPLETED', 'CANCELLED') NOT NULL DEFAULT 'OPEN',
    `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `updated_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    CONSTRAINT `chk_task_budget_non_negative` CHECK (`budget` >= 0),
    CONSTRAINT `fk_tasks_poster` 
        FOREIGN KEY (`poster_id`) REFERENCES `users` (`user_id`) 
        ON DELETE CASCADE ON UPDATE CASCADE,
    CONSTRAINT `fk_tasks_category` 
        FOREIGN KEY (`category_id`) REFERENCES `categories` (`category_id`) 
        ON DELETE RESTRICT ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
