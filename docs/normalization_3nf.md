# Database Normalization: Third Normal Form (3NF) Explanation

**Course:** CSE 2291 — Database Management System (DBMS)  
**Project:** CampusTask (University Student Freelance Marketplace)  
**Author:** Person B (Workflow Side)  

---

## 1. What is Third Normal Form (3NF)?

A relational table is in **Third Normal Form (3NF)** if and only if:
1. It is already in **Second Normal Form (2NF)** (i.e. all non-key attributes are fully functionally dependent on the entire primary key, with no partial dependencies).
2. **No non-prime attribute is transitively dependent on the primary key.**

### Transitive Dependency Definition:
A transitive dependency occurs when non-key attribute $A$ determines another non-key attribute $B$ ($PK \rightarrow A \rightarrow B$). In 3NF, every non-key attribute must depend **only** on the primary key: *"The key, the whole key, and nothing but the key."*

---

## 2. 3NF Analysis of CampusTask Core & Workflow Tables

CampusTask's 8 relational tables are strictly normalized to 3NF to avoid update, insertion, and deletion anomalies:

```
users (user_id PK, full_name, email, password_hash, role, account_status, created_at)
profiles (profile_id PK, user_id FK UNIQUE, student_id, department, bio, profile_image, created_at)
categories (category_id PK, category_name UNIQUE, description, created_at)
tasks (task_id PK, poster_id FK, category_id FK, title, description, budget, deadline, status, created_at, updated_at)
skills (skill_id PK, skill_name UNIQUE, description, created_at)
task_skills (task_id PK/FK, skill_id PK/FK)
applications (application_id PK, task_id FK, applicant_id FK, proposal, proposed_budget, status, applied_at)
reviews (review_id PK, task_id FK, reviewer_id FK, reviewee_id FK, rating, comment, created_at)
```

### Table 1 & 2: `users` and `profiles` (One-to-One Separation)
* **The Problem if Combined:** If student academic details (`student_id`, `department`, `bio`) were placed inside `users`, an admin account would have null values, and any department change or student record update could create update anomalies.
* **The 3NF Solution:** `users` only holds authentication credentials and system role. Academic student profiles are placed in `profiles` with `user_id` as a unique foreign key. `department` depends directly on `student_id`/`profile_id`, not as a transitive property of login credentials.

### Table 3 & 4: `categories` and `tasks`
* **The Problem if Combined:** If category names (e.g. "Graphic Design", "Tutoring") were stored directly inside the `tasks` table, updating a category name would require updating every existing task row. If all tasks under "Tutoring" were deleted, the category itself would be lost (deletion anomaly).
* **The 3NF Solution:** `category_name` and its description reside in the independent `categories` table. `tasks` stores only `category_id` (FK). Thus, `task_id \rightarrow category_id`, and `category_id \rightarrow category_name`. Removing transitive dependency ensures 3NF.

### Table 5 & 6: `skills` and `task_skills` (Many-to-Many Relationship)
* **The Problem if Combined:** Storing skills as a comma-separated string inside `tasks` violates 1NF (atomic values). Storing skill descriptions inside a task junction table would violate 2NF (partial dependency on composite key).
* **The 3NF Solution:** Skills are stored in a dedicated `skills` table (`skill_id`, `skill_name`, `description`). The junction table `task_skills` contains only `(task_id, skill_id)`. There are no non-key attributes in `task_skills`, completely satisfying 2NF and 3NF.

### Table 7: `applications`
* **Transitive Dependency Avoided:** The `applications` table references `task_id` and `applicant_id`. It does **not** store the applicant's name, email, department, or the task poster's name.
* **Dependency:** 
  $$\text{application\_id} \rightarrow (\text{task\_id}, \text{applicant\_id}, \text{proposal}, \text{proposed\_budget}, \text{status}, \text{applied\_at})$$
  Any information about the applicant is queried via `JOIN users ON applications.applicant_id = users.user_id`.

### Table 8: `reviews`
* **Transitive Dependency Avoided:** The `reviews` table records bilateral feedback. It holds `task_id`, `reviewer_id`, and `reviewee_id` as foreign keys. It does not store user names, roles, or task titles.
* **Dependency:** Every non-key attribute (`rating`, `comment`, `created_at`) depends strictly and directly on `review_id`.

---

## 3. Comparison: Anomalies Prevented by 3NF

| Anomaly Type | Without 3NF (Denormalized) | With CampusTask 3NF Design |
|---|---|---|
| **Insertion Anomaly** | Cannot create a new category (e.g. "Mobile Dev") without creating a dummy task. | Categories can be created independently in `categories` before any tasks are posted. |
| **Deletion Anomaly** | Deleting the last task under a department or category deletes that category from the system. | Tasks can be deleted without deleting the master category or student account. |
| **Update Anomaly** | If a student updates their name, every task, application, and review row mentioning them would need updating. | Updating `users.full_name` instantly reflects across all tasks, applications, and reviews through foreign keys. |

---

## 4. Summary for Viva Defense
* Every non-key column in our database provides a fact about the entity identified by the primary key and nothing else.
* We eliminated all transitive dependencies through proper foreign key relationships (`categories`, `profiles`, `skills`).
* Data integrity is reinforced at the database level with foreign keys (`ON DELETE CASCADE`), unique constraints, and check constraints.
