# AJM Bengkel Laravel Implementation Plan

> **For Hermes:** Use subagent-driven-development skill to implement this plan task-by-task.

**Goal:** Migrate AJM Bengkel from NextJS to Laravel with a focus on a robust Admin Panel.

**Architecture:** 
- Monolithic Laravel 11 with Inertia.js for a SPA-like experience using TypeScript.
- Filament PHP for the Admin Panel to accelerate CRUD development.
- MySQL for data persistence, utilizing stored procedures/triggers for business logic.

**Tech Stack:** 
- Laravel 11, Inertia.js, Vue 3/React (TypeScript), MySQL 8.0, Filament PHP, Tailwind CSS.

---

## Phase 1: Design & Documentation
**Objective:** Establish the blueprint and required UML/ERD diagrams.

### Task 1: ERD & Database Schema Design
**Objective:** Design the relational schema for inventory, work orders, and financials.
**Files:** `docs/design/erd.md` (or image link)
**Steps:**
1. Define tables: `users`, `vehicles`, `customers`, `spareparts`, `work_orders`, `work_order_items`, `service_photos`, `transactions`.
2. Define relationships (One-to-Many, Many-to-Many).
3. Document data types and constraints.

### Task 2: Use Case & Activity Diagrams
**Objective:** Map out admin interactions and the service lifecycle.
**Files:** `docs/design/usecase.md`, `docs/design/activity.md`
**Steps:**
1. Create Use Case for Admin: (Manage Stock, Create Work Order, Upload Proof, View Reports).
2. Create Activity Diagram: Customer Arrival $\rightarrow$ Inspection $\rightarrow$ Work Order $\rightarrow$ Execution (with photo) $\rightarrow$ Payment $\rightarrow$ Completion.

---

## Phase 2: Project Initialization
**Objective:** Setup the environment and base framework.

### Task 3: Laravel & Filament Installation
**Objective:** Bootstrap the project in `~/Documents/ajm-bengkel-laravel/`.
**Steps:**
1. Run `composer create-project laravel/laravel .`
2. Install Filament PHP: `composer require filament/filament`.
3. Setup Inertia.js with TypeScript.
4. Configure `.env` for MySQL.

---

## Phase 3: Core Admin Features (The Priority)
**Objective:** Build the requested admin modules.

### Task 4: Inventory Management Module
**Objective:** CRUD for spare parts and stock tracking.
**Files:** `app/Filament/Resources/SparepartResource.php`
**Steps:**
1. Migration for `spareparts` table.
2. Filament Resource for stock management.
3. Add "Low Stock" alert logic.

### Task 5: Work Order & Service Logging
**Objective:** Track what is being done at the workshop.
**Files:** `app/Filament/Resources/WorkOrderResource.php`
**Steps:**
1. Migration for `work_orders` and `work_order_items`.
2. Implementation of status tracking (Pending, In Progress, Finished).
3. Integration of `spareparts` into the order.

### Task 6: Proof of Work (Photo Documentation)
**Objective:** Allow admins to upload photos of specific tasks (e.g., oil change).
**Files:** `app/Filament/Resources/WorkOrderResource.php`
**Steps:**
1. Migration for `service_photos` table linked to `work_order_items`.
2. Add FileUpload field in Filament to the work order items.
3. Ensure photos are stored securely.

### Task 7: Financial Reporting (Optional)
**Objective:** Simple income/expense tracking.
**Files:** `app/Filament/Resources/TransactionResource.php`
**Steps:**
1. Migration for `transactions`.
2. Basic reporting dashboard in Filament.

---

## Phase 4: Database Logic & Advanced Features
**Objective:** Implement DB-level routines and audit trails.

### Task 8: Procedures, Triggers, and Functions
**Objective:** Move business logic to the DB layer as requested.
**Files:** `database/migrations/xxxx_create_db_routines.php`
**Steps:**
1. Create trigger for automatic stock deduction upon work order completion.
2. Create procedure for generating monthly financial summaries.
3. Use `DB::unprepared` in migrations for implementation.

### Task 9: Commit & Rollback (Audit Trail)
**Objective:** Track changes to critical data for rollback capability.
**Files:** `app/Models/AuditLog.php`
**Steps:**
1. Create `audit_logs` table (model, row_id, old_values, new_values, user_id).
2. Implement a Trait or Observer to log every mutation.
3. Build a Filament page to view and "rollback" (revert) to a previous state.

---

## Phase 5: Reporting & Handoff
**Objective:** Finalize documentation and progress logs.

### Task 10: Progress Report Setup
**Objective:** Create a system for daily/weekly reporting.
**Files:** `docs/progress_logs/`
**Steps:**
1. Initialize `weekly_report_template.md`.
2. Log all completed tasks from Phase 1-4.

**Verification:**
- All admin features working in Filament.
- DB Triggers reducing stock correctly.
- Photos uploading and linking to orders.
- Audit log capturing changes.
