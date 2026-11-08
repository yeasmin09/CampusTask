# CampusTask: Comprehensive Test Cases & Verification Checklist

**Course:** CSE 2291 — Database Management System (DBMS)  
**Project:** CampusTask  
**Module:** Person B (Workflow, Authentication, Applications, Reviews, Dashboards)  

---

## 1. Authentication & Session Management (Feature B2)

| Test ID | Test Scenario | Steps to Execute | Expected Result | Pass / Fail |
|---|---|---|---|---|
| **TC-AUTH-01** | Valid Student Login | 1. Navigate to `/login.php`<br>2. Enter `rafi@student.test` and `Student@123`<br>3. Submit | Successfully logs in, regenerates session, displays welcome flash message, and redirects to `/student/dashboard.php`. | Pass |
| **TC-AUTH-02** | Valid Admin Login | 1. Navigate to `/login.php`<br>2. Enter `admin@campustask.test` and `Admin@123`<br>3. Submit | Successfully logs in, redirects to `/admin/dashboard.php`. | Pass |
| **TC-AUTH-03** | Invalid Password | 1. Enter valid email with incorrect password<br>2. Submit | Login rejected with message *"Invalid email or password"*. | Pass |
| **TC-AUTH-04** | Non-existent Email | 1. Enter `ghost@university.test`<br>2. Submit | Login rejected with generic message *"Invalid email or password"*. | Pass |
| **TC-AUTH-05** | Deactivated Account Login | 1. Admin deactivates account<br>2. Student attempts login with correct credentials | Login blocked with error: *"Your account has been deactivated. Please contact an administrator."* | Pass |
| **TC-AUTH-06** | Logout Process | 1. Click "Logout" from header navigation | Session variables unset, cookie cleared, session destroyed, redirects to `/login.php` with info flash. | Pass |

---

## 2. Role-Based Access Control (Feature B3)

| Test ID | Test Scenario | Steps to Execute | Expected Result | Pass / Fail |
|---|---|---|---|---|
| **TC-RBAC-01** | Guest Accessing Student Dashboard | 1. Open new private browser window<br>2. Go to `/student/dashboard.php` | Blocked by `require_login()`, redirects to `/login.php` with error flash. | Pass |
| **TC-RBAC-02** | Student Accessing Admin Panel | 1. Log in as student (`rafi@student.test`)<br>2. Attempt opening `/admin/dashboard.php` | Blocked by `require_admin()`, redirects to `/student/dashboard.php` with access denied error. | Pass |
| **TC-RBAC-03** | Admin Accessing Student Actions | 1. Log in as admin (`admin@campustask.test`)<br>2. Attempt opening `/student/my-applications.php` | Blocked by `require_student()`, redirects to `/admin/dashboard.php`. | Pass |

---

## 3. User Management — Admin (Feature B4)

| Test ID | Test Scenario | Steps to Execute | Expected Result | Pass / Fail |
|---|---|---|---|---|
| **TC-USER-01** | Search Users | 1. On `/admin/users.php`, type "Nusrat"<br>2. Click Filter | Table filters to show only matching user accounts. | Pass |
| **TC-USER-02** | Filter by Role & Status | 1. Select Role: `STUDENT` and Status: `ACTIVE`<br>2. Click Filter | Only active student accounts are shown. | Pass |
| **TC-USER-03** | Deactivate Student Account | 1. Click "Deactivate" next to a student account | Account status updates to `DEACTIVATED`, success flash shown, user cannot log in. | Pass |
| **TC-USER-04** | Prevent Self-Deactivation | 1. Attempt deactivating own admin account | System blocks action with error: *"You cannot deactivate your own administrative account."* | Pass |
| **TC-USER-05** | Prevent Deactivating Other Admins | 1. Attempt sending deactivation request for another admin user ID | System blocks action with error: *"Administrators cannot deactivate other administrator accounts."* | Pass |

---

## 4. Task Application Process (Feature B5)

| Test ID | Test Scenario | Steps to Execute | Expected Result | Pass / Fail |
|---|---|---|---|---|
| **TC-APP-01** | Valid Task Application | 1. Student views an OPEN task posted by another user<br>2. Enters proposal and proposed budget<br>3. Submits form | Application recorded with status `PENDING`, success flash shown. | Pass |
| **TC-APP-02** | Apply to Own Task | 1. Student attempts to apply to a task they posted | Blocked: *"You cannot apply to a task that you posted yourself."* | Pass |
| **TC-APP-03** | Duplicate Application Prevention | 1. Student attempts applying twice to the same task | Blocked by server check and unique constraint: *"You have already submitted an application for this task."* | Pass |
| **TC-APP-04** | Apply to Non-OPEN Task | 1. Attempt submitting application for `IN_PROGRESS` or `COMPLETED` task | Blocked: *"This task is not open for applications."* | Pass |
| **TC-APP-05** | Negative Budget Validation | 1. Submit proposal with proposed budget `-15` | Blocked: *"Proposed budget cannot be negative."* | Pass |

---

## 5. My Applications (Feature B6)

| Test ID | Test Scenario | Steps to Execute | Expected Result | Pass / Fail |
|---|---|---|---|---|
| **TC-MYAPP-01** | View Submitted Proposals | 1. Student navigates to `/student/my-applications.php` | Displays all proposals submitted by the logged-in student with correct status badges. | Pass |
| **TC-MYAPP-02** | Withdraw PENDING Application | 1. Click "Withdraw" on a PENDING application | Status updates to `WITHDRAWN`, applicant removed from pending considerations. | Pass |
| **TC-MYAPP-03** | Disallow Withdrawing ACCEPTED Application | 1. Attempt withdrawing an application that has already been `ACCEPTED` | System blocks action: *"Only PENDING applications can be withdrawn."* | Pass |

---

## 6. Application Management & Database Transaction (Feature B7)

| Test ID | Test Scenario | Steps to Execute | Expected Result | Pass / Fail |
|---|---|---|---|---|
| **TC-MGR-01** | Non-Owner Access Prevention | 1. Student attempts to manage applications of another student's task (`/student/applications.php?task_id=X`) | Access denied: *"You can only manage applications for tasks you posted."* | Pass |
| **TC-MGR-02** | Accept Applicant (PDO Transaction) | 1. Task owner clicks "Accept" on applicant A | 1. Selected application becomes `ACCEPTED`<br>2. Other pending applications become `REJECTED`<br>3. Task status changes to `IN_PROGRESS`<br>(All committed atomically in one transaction). | Pass |
| **TC-MGR-03** | Reject Single Application | 1. Task owner clicks "Reject" on applicant B | Application status updates to `REJECTED`. | Pass |
| **TC-MGR-04** | Mark Task Completed | 1. On `IN_PROGRESS` task, owner clicks "Mark as Completed" | Task status updates to `COMPLETED`, review button unlocks. | Pass |
| **TC-MGR-05** | Cancel Task | 1. On `OPEN` task, owner clicks "Cancel Task" | Task status becomes `CANCELLED`, pending applications become `REJECTED`. | Pass |

---

## 7. Reviews and Ratings (Feature B8)

| Test ID | Test Scenario | Steps to Execute | Expected Result | Pass / Fail |
|---|---|---|---|---|
| **TC-REV-01** | Submit Review on COMPLETED Task | 1. Task poster rates freelancer 5 stars with comment<br>2. Submits review | Review inserted into database, average rating recalculated. | Pass |
| **TC-REV-02** | Review on Non-Completed Task | 1. Attempt writing a review on an `IN_PROGRESS` or `OPEN` task | Blocked: *"Reviews can only be submitted for COMPLETED tasks."* | Pass |
| **TC-REV-03** | Unauthorized Reviewer | 1. A third student (not poster, not accepted freelancer) attempts to review | Blocked: *"Only the task poster and the accepted freelancer can submit a review."* | Pass |
| **TC-REV-04** | Prevent Duplicate Review | 1. User attempts to submit a second review on the same task | Blocked by server check and database UNIQUE constraint. | Pass |
| **TC-REV-05** | Rating Range Validation (1–5) | 1. Attempt submitting rating 0 or 6 | Blocked by validation and SQL Trigger `before_review_insert`. | Pass |

---

## 8. Dashboards & Moderation (Feature B9 & B10)

| Test ID | Test Scenario | Steps to Execute | Expected Result | Pass / Fail |
|---|---|---|---|---|
| **TC-DASH-01** | Student Dashboard Live Counts | 1. View `/student/dashboard.php` | Correctly counts posted tasks, sent applications, accepted jobs, and completed collaborations from live SQL. | Pass |
| **TC-DASH-02** | Admin Dashboard Analytics | 1. View `/admin/dashboard.php` | Displays system-wide counts and category budget breakdown using `GROUP BY`. | Pass |
| **TC-DASH-03** | Stored Procedure Execution | 1. Enter Task ID 1 in Task Stats Lookup on Admin Dashboard | Successfully executes `CALL GetTaskStatistics(1)` and displays total, accepted, and pending applications. | Pass |
| **TC-DASH-04** | Review Moderation & Deletion | 1. On `/admin/reviews.php`, admin deletes a flagged review | Review permanently removed from database, success flash displayed. | Pass |

---

## 9. Triggers & Constraints (Feature B12)

| Test ID | Test Scenario | Steps to Execute | Expected Result | Pass / Fail |
|---|---|---|---|---|
| **TC-TRIG-01** | `before_review_insert` | Direct SQL insert of a review with task status `OPEN` | Trigger raises SQLSTATE '45000': *"Reviews can only be submitted for COMPLETED tasks."* | Pass |
| **TC-TRIG-02** | `before_application_insert` | Direct SQL insert of application on task by task owner | Trigger raises SQLSTATE '45000': *"A task poster cannot apply to their own task."* | Pass |
| **TC-TRIG-03** | Unique Constraints | Direct insert of duplicate `(task_id, applicant_id)` | Blocked by MySQL UNIQUE constraint violation error. | Pass |
