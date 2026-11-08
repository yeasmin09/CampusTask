# CampusTask: Project Demonstration Video Script

**Course:** CSE 2291 — Database Management System (DBMS)  
**Project:** CampusTask (University Student Freelance Marketplace)  
**Video Length:** ~8 to 10 minutes  
**Lead Presenter:** Person B (Workflow, Authentication & Database Implementation)  
**Co-Presenter:** Person A (Task Marketplace & Profile Management)  

---

## Overview of Demo Video

This script guides the team through recording the final project demonstration video required for the course evaluation.

```
+----------------------------------------------------------------------------+
| 00:00 - 01:00  | Scene 1: Introduction & Technology Architecture           |
| 01:00 - 02:15  | Scene 2: Student Authentication, Security & RBAC          |
| 02:15 - 04:00  | Scene 3: Task Posting, Marketplace & Submitting Proposals |
| 04:00 - 05:30  | Scene 4: Managing Applications & Database Transaction     |
| 05:30 - 06:45  | Scene 5: Task Completion, Reviews & Student Reputation   |
| 06:45 - 08:00  | Scene 6: Dashboards & Administrative Moderation           |
| 08:00 - 09:30  | Scene 7: phpMyAdmin Schema, Stored Procedure & Triggers   |
| 09:30 - 10:00  | Scene 8: Git Commit History & Conclusion                  |
+----------------------------------------------------------------------------+
```

---

## Detailed Scene Breakdown

### Scene 1: Introduction & Project Architecture (00:00 – 01:00)
* **Visual on Screen:** CampusTask Home Page (`/CampusTask/index.php`) and Visual Architecture Slide.
* **Speaker (Person B):**
  > "Hello respected faculty and evaluators. Welcome to the demonstration of **CampusTask**, a University-Based Student Freelance Task Marketplace developed for course CSE 2291 (Database Management System).
  > I am Person B, responsible for the workflow architecture, authentication, application handling, peer reviews, dashboards, and database procedures. My teammate Person A handled task marketplace listings, task CRUD, and student profile management.
  > Our technology stack uses plain PHP 8 with PDO, MySQL running on XAMPP, and vanilla HTML5, CSS3, and JavaScript — with zero external libraries or frameworks to ensure complete transparency during viva examination."

---

### Scene 2: Authentication, Security & Role-Based Access Control (01:00 – 02:15)
* **Visual on Screen:** Browser showing `/CampusTask/login.php`.
* **Actions:**
  1. Demonstrate login validation by attempting empty submission.
  2. Log in using student account `rafi@student.test` / `Student@123`.
  3. Show the dynamic navigation bar switching to student menu (Dashboard, Browse Tasks, Post Task, My Applications, Profile).
  4. Manually type `http://localhost/CampusTask/admin/dashboard.php` into the browser URL bar to prove that Student cannot open Admin pages.
* **Speaker (Person B):**
  > "Security and data integrity are enforced at every layer. Passwords are encrypted using bcrypt via `password_hash()` and verified with `password_verify()`. On every protected page, our RBAC helpers in `includes/auth.php` verify sessions and account status.
  > Notice when I try to directly enter an Admin URL while logged in as a student, the server immediately denies access and redirects back to the student dashboard."

---

### Scene 3: Task Marketplace & Submitting Proposals (02:15 – 04:00)
* **Visual on Screen:** Browser showing `/student/tasks.php` and `/student/task-details.php`.
* **Actions:**
  1. Log out, then log in as second student `nusrat@student.test`.
  2. Browse available tasks. Filter by category (e.g. "Presentation") and sort by nearest deadline.
  3. Open Task #1 ("Make 10-slide PowerPoint presentation").
  4. Fill out the application form: Enter proposal *"I can deliver high quality visual slides in 48 hours"* with proposed budget `$25.00`.
  5. Submit the application.
  6. Attempt to submit the application a second time to show duplicate prevention.
* **Speaker (Person B / Person A):**
  > "Here, Nusrat finds an OPEN task posted by another student. Notice that our business rules prevent students from applying to their own tasks.
  > When she submits her proposal, `student/apply.php` validates input and records the proposal as `PENDING`. When we attempt to submit again, the database UNIQUE constraint `(task_id, applicant_id)` immediately catches the duplicate and displays a friendly notice."

---

### Scene 4: Managing Applications & Database Transaction (04:00 – 05:30)
* **Visual on Screen:** Log out and log back in as Task Owner (`rafi@student.test`). Open `/student/applications.php?task_id=1`.
* **Actions:**
  1. Show applicant list: displays Nusrat's name, student ID, department, proposed budget, and proposal.
  2. Click **"Accept"** on Nusrat's application.
  3. Show that:
     - Nusrat's application status is now `ACCEPTED`.
     - Other pending applications for this task are automatically set to `REJECTED`.
     - The task status transitions to `IN_PROGRESS`.
* **Speaker (Person B):**
  > "This is a key technical requirement of our DBMS project: **Multi-step state transition executed via a PDO Database Transaction**.
  > Inside `student/applications.php`, we execute `$pdo->beginTransaction()`. In a single atomic operation, we set the chosen applicant to `ACCEPTED`, mark all competing proposals as `REJECTED`, and update the task status to `IN_PROGRESS`. If any query fails, `$pdo->rollBack()` guarantees zero data inconsistency."

---

### Scene 5: Task Completion, Reviews & Student Reputation (05:30 – 06:45)
* **Visual on Screen:** Owner views the `IN_PROGRESS` task on `/student/applications.php`.
* **Actions:**
  1. Click **"Mark as Completed"**. Task status changes to `COMPLETED`.
  2. Click **"Leave Review for Freelancer"**, opening `/student/reviews.php`.
  3. Submit a 5-star rating with review comment.
  4. Show the updated reputation card with average rating and star visual.
* **Speaker (Person B):**
  > "Once the work is done, the owner marks the task `COMPLETED`. This unlocks the review system.
  > In `student/reviews.php`, the server strictly verifies that only the poster and the accepted freelancer can submit a review, and only once per task. Reviewer and reviewee roles are derived on the server to prevent spoofing, and ratings are constrained between 1 and 5 stars."

---

### Scene 6: Dashboards & System Administration (06:45 – 08:00)
* **Visual on Screen:** Open `/student/dashboard.php`, then log in as Admin (`admin@campustask.test`) and open `/admin/dashboard.php`.
* **Actions:**
  1. Point out live statistic cards computed from live SQL queries.
  2. Show "Tasks per Category" table using `GROUP BY`.
  3. Demonstrate the **Task Stats Lookup** tool by inputting Task ID #1 and executing the Stored Procedure `GetTaskStatistics`.
  4. Navigate to `/admin/users.php` and demonstrate search, filter, and user account status activation/deactivation.
  5. Navigate to `/admin/reviews.php` to show review moderation.
* **Speaker (Person B):**
  > "Our dashboards feature live SQL computations. On the admin dashboard, we demonstrate real-time aggregates and grouping.
  > Furthermore, our stored procedure `GetTaskStatistics` calculates application totals by status with a single database call. Administrators can also moderate reviews and manage user accounts with safeguard protections against self-deactivation."

---

### Scene 7: phpMyAdmin Schema, Stored Procedure & Triggers (08:00 – 09:30)
* **Visual on Screen:** phpMyAdmin interface for database `campustask`.
* **Actions:**
  1. Open Designer / Relations view: show all 8 tables (`users`, `profiles`, `categories`, `tasks`, `skills`, `task_skills`, `applications`, `reviews`).
  2. Show Foreign Key constraints with `ON DELETE CASCADE`.
  3. Open SQL tab and demonstrate Triggers:
     - Execute an illegal review insert on an OPEN task: show MySQL raising `SIGNAL SQLSTATE '45000'`.
     - Execute the multi-table JOIN query joining `users` twice from `b_queries.sql`.
* **Speaker (Person B):**
  > "In phpMyAdmin, you can see all 8 normalized tables meeting 3NF standards. All foreign keys enforce referential integrity.
  > To guarantee business rules at the database level, our triggers `before_review_insert` and `before_application_insert` prevent illegal insertions directly in MySQL."

---

### Scene 8: Git Commit History & Conclusion (09:30 – 10:00)
* **Visual on Screen:** Terminal showing `git log --oneline` and GitHub repository.
* **Speaker (Person B):**
  > "Throughout the project, we followed strict Git version control with atomic daily commits for each feature, maintaining separate file ownership without merge conflicts.
  > Thank you for watching our demonstration. We look forward to answering your questions in the viva examination!"
