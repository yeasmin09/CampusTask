-- =====================================================================
-- CampusTask Database: seed_a.sql
-- Course: CSE 2291 - Database Management System (DBMS)
-- Owner: Person A (Task Side)
-- Description: Core Seed Data - Admin account, sample students,
--              initial categories, and foundation tasks
-- Import Order: Step 3 (After 01_schema_core.sql and 02_schema_workflow.sql)
-- =====================================================================

USE `campustask`;

-- -------------------------------------------------------------
-- 1. Seed Users
-- Demo Passwords:
-- Admin:   Admin@123
-- Student: Student@123
-- -------------------------------------------------------------
INSERT INTO `users` (`user_id`, `full_name`, `email`, `password_hash`, `role`, `account_status`, `created_at`) VALUES
(1, 'System Administrator', 'admin@campustask.test', '$2y$12$vgwdckKynuaFbuaFkREOqOctW.JvOmCX.yUpfuuLSvW0yxpTwa.yy', 'ADMIN', 'ACTIVE', NOW() - INTERVAL 10 DAY),
(2, 'Rafi Ahmed', 'rafi@student.test', '$2y$12$xkkbquZR8YkhNZ3aSqEB3eFxkwbj3TBjgl4kafi29YJrsQecLtqka', 'STUDENT', 'ACTIVE', NOW() - INTERVAL 9 DAY),
(3, 'Nusrat Jahan', 'nusrat@student.test', '$2y$12$xkkbquZR8YkhNZ3aSqEB3eFxkwbj3TBjgl4kafi29YJrsQecLtqka', 'STUDENT', 'ACTIVE', NOW() - INTERVAL 8 DAY)
ON DUPLICATE KEY UPDATE `full_name` = VALUES(`full_name`);

-- -------------------------------------------------------------
-- 2. Seed Profiles (One-to-One with users)
-- -------------------------------------------------------------
INSERT INTO `profiles` (`profile_id`, `user_id`, `student_id`, `department`, `bio`, `profile_image`, `created_at`) VALUES
(1, 2, 'ST-2022-001', 'Computer Science & Engineering', 'Enthusiastic CS student skilled in web technologies, UI/UX design, and presentation slide curation. Always eager to collaborate on creative campus projects.', NULL, NOW() - INTERVAL 9 DAY),
(2, 3, 'ST-2022-045', 'Software Engineering', 'Passionate software engineering undergraduate. Strong foundation in Python, data analysis, and technical report writing. Happy to help peers with coursework.', NULL, NOW() - INTERVAL 8 DAY)
ON DUPLICATE KEY UPDATE `bio` = VALUES(`bio`);

-- -------------------------------------------------------------
-- 3. Seed Categories
-- -------------------------------------------------------------
INSERT INTO `categories` (`category_id`, `category_name`, `description`, `created_at`) VALUES
(1, 'Web Development', 'Frontend HTML/CSS/JS, backend scripts, bug fixes, responsive designs, and student portfolio websites', NOW() - INTERVAL 10 DAY),
(2, 'Graphic Design', 'Campus event posters, club logos, social media flyers, vector art, and infographic illustrations', NOW() - INTERVAL 10 DAY),
(3, 'Presentation', 'Professional academic slide decks, PowerPoint styling, pitch decks, and seminar visuals', NOW() - INTERVAL 10 DAY),
(4, 'Programming', 'Python scripts, C/C++ debugging, data structures, algorithms, and automated test scripts', NOW() - INTERVAL 10 DAY),
(5, 'Academic Writing', 'Research paper formatting, LaTeX typesetting, grammar review, literature review synthesis', NOW() - INTERVAL 10 DAY),
(6, 'Tutoring', 'Peer-to-peer explanation for difficult course concepts, lab task guidance, and exam preparation', NOW() - INTERVAL 10 DAY)
ON DUPLICATE KEY UPDATE `category_name` = VALUES(`category_name`);

-- -------------------------------------------------------------
-- 4. Seed Tasks
-- Base tasks required for application & review workflow (seed_b)
-- -------------------------------------------------------------
INSERT INTO `tasks` (`task_id`, `poster_id`, `category_id`, `title`, `description`, `budget`, `deadline`, `status`, `created_at`) VALUES
(1, 2, 3, 'Design 10 Academic Presentation Slides for DBMS Project', 'Need a visually appealing, professional PowerPoint presentation deck for our CSE 2291 final project defense. Content and key points will be supplied. Good typography and diagrams required.', 30.00, DATE_ADD(CURRENT_DATE, INTERVAL 5 DAY), 'OPEN', NOW() - INTERVAL 2 DAY),
(2, 3, 4, 'Python Script for Cleaning CSV Dataset', 'Need a Python script utilizing pandas and regex to remove duplicated records, format irregular dates, and replace missing values in a 5,000-row research survey CSV.', 45.00, DATE_ADD(CURRENT_DATE, INTERVAL 3 DAY), 'IN_PROGRESS', NOW() - INTERVAL 4 DAY),
(3, 2, 1, 'Build Responsive Student Portfolio Webpage', 'Looking for a clean, single-page responsive student portfolio website created with semantic HTML5 and vanilla CSS. Must include about me, project cards, and a contact form section.', 50.00, DATE_SUB(CURRENT_DATE, INTERVAL 1 DAY), 'COMPLETED', NOW() - INTERVAL 7 DAY),
(4, 3, 2, 'Design Departmental Fest Poster and Banner', 'Creative event poster required for the upcoming CSE Annual Tech Week. Needs digital illustration, high-res printable PDF format, and an aspect ratio suitable for social media sharing.', 35.00, DATE_ADD(CURRENT_DATE, INTERVAL 7 DAY), 'OPEN', NOW() - INTERVAL 1 DAY),
(5, 2, 5, 'Review and Format Research Literature Review in LaTeX', 'Assistance needed to format a 6-page literature review section into IEEE standard 2-column LaTeX format with BibTeX citation entries and cross-referenced figure captions.', 25.00, DATE_ADD(CURRENT_DATE, INTERVAL 4 DAY), 'OPEN', NOW() - INTERVAL 1 DAY)
ON DUPLICATE KEY UPDATE `title` = VALUES(`title`);
