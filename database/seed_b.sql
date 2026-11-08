-- =====================================================================
-- CampusTask Database: seed_b.sql
-- Course: CSE 2291 - Database Management System (DBMS)
-- Owner: Person B (Workflow Side)
-- Description: Sample data for skills, task_skills, applications, reviews
-- Import Order: Must be imported AFTER seed_a.sql
-- =====================================================================

USE `campustask`;

-- -------------------------------------------------------------
-- 1. Seed Skills
-- -------------------------------------------------------------
INSERT INTO `skills` (`skill_id`, `skill_name`, `description`) VALUES
(1, 'Python', 'Python scripting, data manipulation, automation'),
(2, 'PHP', 'Backend web development with PHP and PDO'),
(3, 'MySQL', 'Relational database schema design and SQL querying'),
(4, 'HTML & CSS', 'Responsive web page design and modern CSS layouts'),
(5, 'JavaScript', 'Client-side scripting, DOM manipulation, vanilla JS'),
(6, 'PowerPoint', 'Professional academic and business presentation slides'),
(7, 'Graphic Design', 'Flyers, social media banners, vector illustration in Photoshop/Illustrator'),
(8, 'Academic Writing', 'Research paper formatting, grammar review, literature review assistance'),
(9, 'Data Analysis', 'Data visualization with Excel, Pandas, or SPSS'),
(10, 'Video Editing', 'Short video editing, trimming, captions, and audio cleanup')
ON DUPLICATE KEY UPDATE `skill_name` = VALUES(`skill_name`);

-- -------------------------------------------------------------
-- 2. Seed Task Skills (M:N Junction Table)
-- Associates sample tasks with relevant skills
-- -------------------------------------------------------------
INSERT INTO `task_skills` (`task_id`, `skill_id`) VALUES
(1, 6),  -- Task 1: Presentation Slides (PowerPoint)
(1, 7),  -- Task 1: Presentation Slides (Graphic Design)
(2, 1),  -- Task 2: Python Script (Python)
(2, 9),  -- Task 2: Python Script (Data Analysis)
(3, 4),  -- Task 3: Portfolio Webpage (HTML & CSS)
(3, 5),  -- Task 3: Portfolio Webpage (JavaScript)
(3, 2)   -- Task 3: Portfolio Webpage (PHP)
ON DUPLICATE KEY UPDATE `task_id` = VALUES(`task_id`);

-- -------------------------------------------------------------
-- 3. Seed Applications
-- User IDs:
-- 2 = Rafi (Student)
-- 3 = Nusrat (Student)
-- (Assuming Seed A creates users 1: Admin, 2: Rafi, 3: Nusrat)
-- -------------------------------------------------------------

-- Application on Task 1 (OPEN task): Rafi posted task 1, Nusrat applied
INSERT INTO `applications` (`application_id`, `task_id`, `applicant_id`, `proposal`, `proposed_budget`, `status`, `applied_at`) VALUES
(1, 1, 3, 'Hello! I have created multiple departmental slide presentations and can deliver high-quality visual slides within 48 hours.', 25.00, 'PENDING', NOW() - INTERVAL 2 DAY)
ON DUPLICATE KEY UPDATE `status` = VALUES(`status`);

-- Application on Task 2 (IN_PROGRESS task): Nusrat posted task 2, Rafi applied and was ACCEPTED
INSERT INTO `applications` (`application_id`, `task_id`, `applicant_id`, `proposal`, `proposed_budget`, `status`, `applied_at`) VALUES
(2, 2, 2, 'I have strong Python experience with pandas and regex. I can automate your CSV data cleaning reliably.', 40.00, 'ACCEPTED', NOW() - INTERVAL 4 DAY)
ON DUPLICATE KEY UPDATE `status` = VALUES(`status`);

-- Application on Task 3 (COMPLETED task): Rafi posted task 3, Nusrat applied and was ACCEPTED
INSERT INTO `applications` (`application_id`, `task_id`, `applicant_id`, `proposal`, `proposed_budget`, `status`, `applied_at`) VALUES
(3, 3, 3, 'I specialize in clean HTML5/CSS3 and responsive design. Will build your portfolio page with clean code.', 50.00, 'ACCEPTED', NOW() - INTERVAL 7 DAY)
ON DUPLICATE KEY UPDATE `status` = VALUES(`status`);

-- -------------------------------------------------------------
-- 4. Seed Reviews
-- Task 3 is COMPLETED, so both parties exchanged reviews
-- -------------------------------------------------------------
INSERT INTO `reviews` (`review_id`, `task_id`, `reviewer_id`, `reviewee_id`, `rating`, `comment`, `created_at`) VALUES
-- Task Poster (Rafi - ID 2) reviewing Accepted Freelancer (Nusrat - ID 3)
(1, 3, 2, 3, 5, 'Exceptional work! Delivered a clean, perfectly responsive portfolio page ahead of deadline. Very polite and easy to communicate with.', NOW() - INTERVAL 3 DAY),
-- Freelancer (Nusrat - ID 3) reviewing Task Poster (Rafi - ID 2)
(2, 3, 3, 2, 5, 'Great collaborator! Clear requirements and prompt feedback. Would definitely work on their tasks again.', NOW() - INTERVAL 3 DAY)
ON DUPLICATE KEY UPDATE `rating` = VALUES(`rating`);
