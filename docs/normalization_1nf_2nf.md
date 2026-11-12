# Database Normalization: First Normal Form (1NF) & Second Normal Form (2NF)

**Course:** CSE 2291 — Database Management System (DBMS)  
**Project:** CampusTask (A University-Based Student Freelance Task Marketplace)  
**Author:** Person A (Task Side)  
**Companion Document:** [3NF Explanation](file:///docs/normalization_3nf.md) by Person B

---

## 1. What is Normalization?

**Normalization** is a formal, step-by-step mathematical technique used in relational database design to:
1. Eliminate redundant data (data duplication across rows/tables).
2. Prevent database **anomalies**:
   - **Insertion Anomaly:** Inability to record certain information without recording unrelated information.
   - **Update Anomaly:** Multiple copies of the same data requiring multiple updates, risking inconsistent data.
   - **Deletion Anomaly:** Unintentional loss of secondary information when deleting a primary entity.
3. Enforce data integrity through foreign keys and proper functional dependencies.

---

## 2. First Normal Form (1NF)

### 2.1 Formal Definition of 1NF
A relational table is in **First Normal Form (1NF)** if and only if:
1. Every attribute (column) contains **only atomic (indivisible) values**.
2. There are **no repeating groups** or arrays of values stored in a single cell.
3. Every row is uniquely identifiable by a defined **Primary Key**.
4. The order in which data is stored does not alter its meaning.

---

### 2.2 1NF Violation & Solution in CampusTask: Task Skills

#### The Problem (0NF / Unnormalized Representation):
In an unnormalized student marketplace table, a task requiring multiple skills might store skills as a comma-separated text string:

| `task_id` | `title` | `budget` | `skills` (Non-Atomic!) |
|---|---|---|---|
| 1 | Presentation Slides | $30.00 | `"PowerPoint, Graphic Design"` |
| 2 | Python Cleaning | $45.00 | `"Python, Data Analysis, MySQL"` |

#### Why this violates 1NF:
1. **Non-Atomic Cell Values:** A single cell in the `skills` column holds multiple values (`PowerPoint` and `Graphic Design`).
2. **Querying Limitations:** Filtering for tasks requiring "Python" requires expensive and fragile `LIKE '%Python%'` string searches, making indexing ineffective.
3. **Data Integrity Hazards:** If a skill is renamed (e.g., "Python" to "Python 3"), string parsing across text columns can corrupt records.

---

#### The 1NF Solution in CampusTask:
To achieve 1NF, multi-valued skills are separated into independent rows and decomposed into dedicated entities:

1. **`skills` Table (Atomic definitions):**
   - Each row defines exactly one skill:
     - `(1, 'Python')`
     - `(6, 'PowerPoint')`
     - `(7, 'Graphic Design')`

2. **`task_skills` Junction Table (Atomic relationships):**
   - Instead of storing comma-separated values, each row stores a single atomic pair:

| `task_id` (PK, FK) | `skill_id` (PK, FK) |
|---|---|
| 1 | 6 (PowerPoint) |
| 1 | 7 (Graphic Design) |
| 2 | 1 (Python) |
| 2 | 9 (Data Analysis) |
| 2 | 3 (MySQL) |

**Result:** All values are completely atomic. Composite primary key `(task_id, skill_id)` uniquely identifies every pair. Full 1NF compliance is achieved!

---

## 3. Second Normal Form (2NF)

### 3.1 Formal Definition of 2NF
A relational table is in **Second Normal Form (2NF)** if and only if:
1. It is already in **First Normal Form (1NF)**.
2. **No non-prime attribute is partially dependent on any candidate key.**
   - In other words, every non-key column must depend on the **entire** primary key, not just a subset of a composite primary key.
   - *Note:* If a table has a single-column primary key, partial dependency is physically impossible, and it automatically satisfies 2NF once in 1NF.

---

### 3.2 2NF Violation & Solution in CampusTask: Composite Key Junction Table

#### The Problem (Hypothetical 2NF Violation):
Suppose we designed the `task_skills` junction table with a composite primary key `(task_id, skill_id)`, but also placed skill metadata inside it:

```
task_skills (task_id, skill_id, skill_name, skill_description, task_title)
Primary Key: (task_id, skill_id)
```

| `task_id` (PK) | `skill_id` (PK) | `skill_name` | `skill_description` | `task_title` |
|---|---|---|---|---|
| 1 | 6 | PowerPoint | Academic slide decks | Presentation Slides |
| 1 | 7 | Graphic Design | Vector logos & banners | Presentation Slides |
| 2 | 1 | Python | Python scripts & pandas | Python Cleaning |

#### Why this violates 2NF:
- **Partial Dependency 1:** `skill_name` and `skill_description` depend **only on `skill_id`**, which is only part of the composite primary key `(task_id, skill_id)`.
  $$\text{skill\_id} \rightarrow (\text{skill\_name}, \text{skill\_description})$$
- **Partial Dependency 2:** `task_title` depends **only on `task_id`**, which is also only part of the primary key.
  $$\text{task\_id} \rightarrow \text{task\_title}$$
- **Resulting Anomalies:**
  - **Redundancy:** If 50 tasks require Python, the string "Python scripts & pandas" is duplicated 50 times.
  - **Update Anomaly:** Updating the description of Python requires modifying 50 separate rows.
  - **Deletion Anomaly:** If Task 2 is deleted and it is the only task with MySQL, the description of MySQL is permanently lost from the database.

---

#### The 2NF Decomposition in CampusTask:
We split the relation into three focused tables where every non-key attribute depends on the full key:

1. **`tasks` Table:**
   - Single-column PK: `task_id`
   - All non-key attributes (`title`, `description`, `budget`, `deadline`, `status`) depend fully on `task_id`.
   - Single PK $\Rightarrow$ **No partial dependency possible $\Rightarrow$ 2NF Satisfied.**

2. **`skills` Table:**
   - Single-column PK: `skill_id`
   - Attributes `skill_name` and `description` depend fully on `skill_id`.
   - Single PK $\Rightarrow$ **No partial dependency possible $\Rightarrow$ 2NF Satisfied.**

3. **`task_skills` Junction Table:**
   - Composite PK: `(task_id, skill_id)`
   - Contains **no non-key attributes** (it only holds foreign key references).
   - Because there are zero non-key attributes, there are **no partial functional dependencies**.
   - **2NF is mathematically and structurally satisfied.**

---

## 4. Viva Defense Quick Reference (Cheat-Sheet)

| Question | Clear, Confident Answer |
|---|---|
| **What is 1NF in simple words?** | "A table is in 1NF if all column values are atomic (no commas, arrays, or repeating groups in a single cell) and every row has a unique primary key." |
| **How does CampusTask prove 1NF?** | "Instead of storing skills like `'HTML, CSS'` inside `tasks`, we use the `task_skills` table where each skill association occupies its own distinct row." |
| **What is a partial dependency in 2NF?** | "A partial dependency happens when a non-key column depends on only part of a composite primary key instead of the whole key." |
| **Can a table with a single primary key violate 2NF?** | "No. If a table has a single-column primary key, partial dependency cannot exist. Once in 1NF, it is automatically in 2NF." |
| **Why is `task_skills` in 2NF?** | "Because its primary key is composite `(task_id, skill_id)` and it has no non-key columns, so no partial dependencies can ever occur." |
