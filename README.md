# CampusTask: A University-Based Student Freelance Task Marketplace

**Course:** CSE 2291 — Database Management System (DBMS)  
**Team Size:** 2 Members (Person A and Person B)  
**Duration:** 12 Days  
**Tech Stack:** Plain PHP 8 (PDO), MySQL / MariaDB, Vanilla HTML5 / CSS3 / JavaScript, XAMPP  
**Local URL:** `http://localhost/CampusTaskmain/` (or `http://localhost/CampusTask/`)

---

## 1. Project Overview

CampusTask is a secure peer-to-peer collaboration marketplace built for university students to exchange assistance on academic and everyday campus tasks (such as presentation slide design, dataset scripting, lab debugging, and research report formatting).

- **Task Posters:** Students post academic tasks with descriptions, categories, budgets, deadlines, and skill tags.
- **Freelancers:** Other students browse the marketplace, filter opportunities, and submit proposals with their proposed rates.
- **Atomic Selection:** The task poster reviews applicants and accepts exactly one student through an atomic database transaction.
- **Peer Evaluation:** Once completed, both students exchange 1–5 star ratings and reviews.
- **Administration:** Administrators supervise user statuses, moderate inappropriate tasks, and manage categories and skills.

> **Note on Payments:** As specified in the course syllabus, the system showcases realistic budget negotiations without an external online monetary payment gateway.

---

## 2. Team Division & File Ownership

The project features are strictly and equally divided into two distinct functional scopes:

| Component / Responsibility | Person A (Task Side) | Person B (Workflow Side) |
|---|---|---|
| **Authentication & Profile** | Registration (`register.php`), Profile (`student/profile.php`) | Login / Logout (`login.php`, `logout.php`), RBAC (`includes/auth.php`) |
| **Marketplace & Tasks** | Post (`student/post-task.php`), Edit/Delete (`student/edit-task.php`), Browse/Search/Filter/Sort (`student/tasks.php`), Details (`student/task-details.php`), Landing (`index.php`) | Apply (`student/apply.php`), My Applications (`student/my-applications.php`), Manage Applicants (`student/applications.php`) |
| **Reviews & Dashboard** | — | Peer Reviews (`student/reviews.php`), Student Dashboard (`student/dashboard.php`) |
| **Administration** | Category & Skill Management (`admin/categories.php`, `admin/skills.php`), Task Moderation (`admin/tasks.php`) | User Management (`admin/users.php`), Review Moderation (`admin/reviews.php`), Admin Dashboard (`admin/dashboard.php`) |
| **Database Design** | Core Schema (`01_schema_core.sql`), Core Seed (`seed_a.sql`), Queries (`a_queries.sql`), Stored Procedure & Function (`a_procedures.sql`), Trigger (`a_triggers.sql`) | Workflow Schema (`02_schema_workflow.sql`), Workflow Seed (`seed_b.sql`), Advanced Queries & Joins (`b_queries.sql`), Stored Procedure (`b_procedures.sql`), Triggers (`b_triggers.sql`) |
| **Documentation** | [ER Diagram](file:///docs/er-diagram.md), [1NF & 2NF Explanation](file:///docs/normalization_1nf_2nf.md), README | [3NF Explanation](file:///docs/normalization_3nf.md), [Demo Script](file:///docs/demo-script.md), [Test Cases](file:///tests/test-cases.md) |

---

## 3. Demo Credentials (from Database Seed Data)

| Role | University Email | Password | Capabilities |
|---|---|---|---|
| **ADMIN** | `admin@campustask.test` | `Admin@123` | System stats, user activation/deactivation, task moderation, category and skill CRUD, review removal |
| **STUDENT** | `rafi@student.test` | `Student@123` | Post tasks, manage own applicants, accept freelancers, complete tasks, review peers |
| **STUDENT** | `nusrat@student.test` | `Student@123` | Browse open tasks, apply with proposals and rates, manage profile, complete accepted tasks |

---

## 4. Local Installation & Setup Guide

### Prerequisites
- **XAMPP** with Apache and MySQL / MariaDB (PHP version 8.0 or higher).
- A modern web browser (Google Chrome, Firefox, or Edge).

---

### Step 1: Place Project in XAMPP `htdocs`
Clone or copy the project folder into your local XAMPP `htdocs` directory:
```
C:\xampp\htdocs\CampusTaskmain\
```
*(If your folder is named `CampusTask`, the project will auto-detect the base URL seamlessly).*

---

### Step 2: Configure Database Credentials
A pre-configured configuration file is included. If setting up manually:
1. Make a copy of `config/database.example.php` and name it `config/database.php`.
2. The default local XAMPP credentials (`root`, no password, database `campustask`) are automatically loaded.

---

### Step 3: Start XAMPP Services
Open the **XAMPP Control Panel** and start:
1. **Apache** (Web Server)
2. **MySQL** (Database Server)

---

### Step 4: Import Database Scripts in Sequence
Import the database scripts into MySQL via **phpMyAdmin** (`http://localhost/phpmyadmin/`) or the command line.

#### Command Line Import (from within project directory):
```bash
# 1. Core Schema (Drops & creates database `campustask`, creates users, profiles, categories, tasks)
mysql -u root < database/01_schema_core.sql

# 2. Workflow Schema (Creates skills, task_skills, applications, reviews)
mysql -u root < database/02_schema_workflow.sql

# 3. Core Seed Data (Admin, sample students, initial categories and foundation tasks)
mysql -u root < database/seed_a.sql

# 4. Workflow Seed Data (Sample skills, junction records, applications, reviews)
mysql -u root < database/seed_b.sql

# 5. Stored Procedures & Functions
mysql -u root < database/a_procedures.sql
mysql -u root < database/b_procedures.sql

# 6. Triggers (Business rule enforcement & audit trails)
mysql -u root < database/a_triggers.sql
mysql -u root < database/b_triggers.sql
```

---

### Step 5: Open in Your Web Browser
Navigate to your local browser and open:
```
http://localhost/CampusTaskmain/
```
*(Or `http://localhost/CampusTask/` if your folder is named `CampusTask`)*.

---

## 5. DBMS Academic Requirements Coverage

### 5.1 Eight Core Relational Tables
1. `users`: System authentication, student/admin roles, active/deactivated statuses.
2. `profiles`: One-to-One extension with `users` (academic ID, department, bio).
3. `categories`: Classification of campus tasks.
4. `tasks`: Core marketplace listings with budget and status workflow (`OPEN`, `IN_PROGRESS`, `COMPLETED`, `CANCELLED`).
5. `skills`: Reusable technical competencies.
6. `task_skills`: Many-to-Many junction table with composite primary key `(task_id, skill_id)` satisfying 1NF and 2NF.
7. `applications`: Student proposals with proposed budget; enforces unique applicant per task.
8. `reviews`: Bilateral peer ratings (1–5) and comments for completed tasks.

---

### 5.2 Stored Procedures & Functions
- **`GetStudentTasks(IN p_user_id INT)`:** Returns all tasks posted by a student with categories, budget, status, and application counts.
- **`GetAverageTaskBudget()`:** Stored function calculating average budget across non-cancelled tasks.
- **`GetTaskStatistics(IN p_task_id INT)`:** Returns breakdown of applications (Pending, Accepted, Rejected, Withdrawn) for a specific task.

---

### 5.3 Database Triggers
- **`before_task_update`:** Enforces non-negative budgets (`SIGNAL SQLSTATE '45000'`) and automatically refreshes `updated_at`.
- **`before_review_insert`:** Ensures ratings are strictly between 1 and 5 and rejects reviews for non-completed tasks.
- **`before_application_insert`:** Blocks self-applications and non-open tasks at the database level.

---

## 6. Security & Data Integrity Highlights

- **SQL Injection Prevention:** 100% prepared statements via PHP Data Objects (PDO) with strict associative fetching. Dynamic sorting columns strictly whitelisted.
- **Cross-Site Scripting (XSS):** Every printed value is escaped using `htmlspecialchars(..., ENT_QUOTES, 'UTF-8')` through the global helper `e()`.
- **Password Security:** Salted bcrypt hashing using PHP's native `password_hash()` and `password_verify()`.
- **Database Transactions:** Critical multi-step operations (e.g. accepting an applicant, posting tasks with skills, deleting tasks) are wrapped in atomic transactions (`$pdo->beginTransaction()` / `$pdo->commit()`).
- **Role-Based Access Control (RBAC):** Backend session and role verification (`require_login()`, `require_student()`, `require_admin()`) executed on every protected endpoint.
