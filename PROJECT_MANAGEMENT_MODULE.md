#Instructions
- this is a 10-30mins project done work
# Task: Add a Comprehensive Borehole Project Management Module to the Existing Water Billing System

## Context

You are already familiar with our existing water billing system codebase. We now need to extend it with a comprehensive **Borehole Project Management Module** for vendors and contractors building boreholes.

**Important scope decisions:**
- This module has **no client/customer management** — clients are not part of project management. Remove any client, customer, contract-revenue, client-invoice, or client-payment concepts from the project module.
- Projects are tracked **purely as internal vendor cost/progress tracking** from Day One through handover. Budget, actual cost, and forecast cost are the financial focus — not revenue.
- The system must have a **module switcher / portal** so users can move between the Water Billing module and the Project Management module, both from a top-level portal and from within either module's workspace.

**Work efficiently.** Prioritize a working end-to-end vertical slice, then layer depth. Do not stop at UI mockups or use fake data.

---

## Part A: Module Portal and Switcher

1. **Top-level portal** — after login, users land on a portal page listing the modules they have access to (at minimum: *Water Billing* and *Project Management*). Show module name, short description, and a card/tile to enter it.
2. **In-workspace switcher** — while inside either module, provide a persistent switcher (top nav dropdown, sidebar item, or header button) to jump directly to the other module without returning to the portal.
3. Module access is role/permission-aware — only show modules the user can access.
4. Preserve each module's existing layout/navigation; the switcher should be additive, not a rewrite.

---

## Part B: Project Management Module

### 1. Project Registration and Lifecycle

Project record fields:
- Unique project reference number (auto-generated).
- Project name and description.
- Vendor/contractor responsible (FK to existing vendor/tenant record).
- Borehole location: county, sub-county, GPS coordinates, site details.
- Project start date, expected completion date, actual completion date.
- Project manager and assigned team members.
- Approved project budget (internal cost budget).
- Borehole specifications: planned depth, diameter, casing requirements, pump capacity.
- Scope, deliverables, special requirements.
- Permit/drawing/contract attachments.

Lifecycle statuses (configurable order): `Draft`, `Planning`, `Site Preparation`, `Drilling`, `Casing & Installation`, `Pump Installation`, `Testing`, `Commissioning`, `Completed`, `On Hold`, `Cancelled`.

Stage transitions must be logged and full history preserved. Transitions should be configurable to accommodate different project types.

### 2. Progress Tracking

Daily progress reports including:
- Reporting date, reporting officer, current stage.
- Activities completed, work planned for next day.
- Planned vs actual drilling depth.
- Materials received/consumed/returned/damaged (link to materials module).
- Labour and equipment deployed.
- Expenses incurred (link to expenses module).
- Weather, site challenges, delays, incidents, risks, corrective actions.
- Progress percentage.
- Photos, videos, documents, evidence.
- PM remarks and approvals.

Provide:
- Project timeline + progress dashboard (completed / ongoing / pending / milestones / deadlines).
- Progress calculated from configurable task/milestone weights (not only manual %).
- Planned vs actual progress; flag delayed activities.
- Timestamped activity log (who changed what, when).

### 3. Materials and Inventory

**Do not create a duplicate inventory system.** Inspect the existing codebase first — if an inventory module exists, integrate with it. Otherwise create a minimal project-scoped materials ledger that can later be promoted.

Material transaction fields:
- Material name, SKU/item code, unit of measure.
- Quantity requested / approved / received / issued / consumed / returned / wasted.
- Unit cost, total cost.
- Supplier, purchase reference, delivery date, receiving officer.
- Storage location, project allocation.
- Invoice/delivery note/receipt attachment.
- Approval status and transaction history.

Costing: `Material Cost = Quantity Consumed × Unit Cost`. Support weighted-average or FIFO (configurable).

**Critical distinction:** materials *purchased* ≠ materials *consumed*. Stock remaining is not a project cost. Returns, transfers, and wastage must be recorded correctly.

Include low-stock notifications and usage reports.

### 4. Expense Tracking

Categories: drilling services, labour & wages, materials & supplies, fuel & transport, equipment hire, equipment repairs, accommodation & meals, site prep & excavation, permits & fees, water quality testing, pump installation & electrical, security & logistics, subcontractors, communication & admin, contingency, other (configurable).

Expense record fields:
- Project reference, category, description, justification.
- Transaction date, amount, currency.
- Payee (supplier/employee).
- Payment method, payment status.
- Receipt/invoice attachment.
- Submitted by, approved by.
- Notes and audit history.

Workflow: submit → approve/reject → record partial/full payments. Prevent duplicates; flag over approval limits.

Auto-calculate: total incurred, total approved, total paid, outstanding, by-category, by-stage, daily/weekly/monthly, budget utilization %, remaining budget.

**Do not double-count** when an expense is also an inventory purchase, supplier invoice, payroll entry, or subcontractor payment.

### 5. Labour, Equipment, and Resources

Track: worker/contractor name & role, assignment dates, daily attendance & hours, wage/rate, overtime & allowances; equipment type & ID, hire rate & duration, fuel & maintenance, downtime; resource allocation across projects.

**Reuse existing HR/payroll/asset/fleet modules if present — do not duplicate employee or equipment records.**

### 6. Budgeting and Financial Analysis

Internal cost-focused (no client revenue). Display:
- Original approved budget, revised budget & approved variations.
- Actual cost to date, paid costs, unpaid approved costs.
- Committed costs (approved POs not yet invoiced).
- Estimated cost to complete, forecast final cost.
- Remaining budget, budget variance, contingency remaining.

Formulas:
- **Actual Project Cost** = sum of recognized costs to date, no duplication.
- **Forecast Final Cost** = Actual Project Cost + Estimated Cost to Complete.
- **Budget Variance** = Approved Current Budget − Forecast Final Cost.
- **Budget Utilization** = Actual Project Cost ÷ Approved Current Budget × 100.

Keep **cash payments separate from costs incurred** (unpaid invoice = incurred cost; advance payment ≠ consumed material).

Support approved budget revisions, retain original for audit. Alerts at 75% / 90% / 100% thresholds.

### 7. Tasks, Milestones, Scheduling

Tasks/subtasks with: assignee, priority, start/due dates, dependencies, estimated cost, required materials, attachments, progress updates, completion evidence, approval, overdue/blocker flags.

Configurable milestones (example default sequence):
1. Approval & mobilization
2. Site survey & preparation
3. Equipment mobilization
4. Drilling commenced
5. Target depth reached
6. Casing completed
7. Gravel packing & development
8. Pump installation
9. Pumping & yield tests
10. Water quality testing
11. Electrical & plumbing
12. Commissioning
13. Final handover & closure

Provide calendar and (where feasible) Gantt-style views. Show critical delays and impact on expected completion.

### 8. Procurement and Supplier Management

Requisition → approval → quotation comparison → PO → invoice/delivery note → partial delivery tracking → goods verification → supplier payments & balances.

Link each procurement transaction to project, budget category, and inventory transaction. Flag over-estimate procurement for re-authorization.

**If a procurement module exists, extend it — do not build a parallel one.**

### 9. Borehole Technical Records

Per-borehole technical record: site survey & geology, planned vs actual depth, drilling logs & formation descriptions, casing/screen specs, gravel pack, development records, static/dynamic water levels, pumping test duration & results, yield & recovery, pump specs & install depth, water quality lab results, electrical/plumbing test results, inspection reports, defects & corrective actions, completion certificates & handover docs.

Mandatory fields may vary by stage. **Technical completion ≠ financial closure** — a borehole is not "handed over" just because drilling ended.

### 10. Dashboards and Reports

Dashboard: active projects, completed, delayed/on-hold, approaching deadlines; total approved budgets, total actual expenditure, total forecast cost, total outstanding supplier obligations; projects over budget; average completion %; estimated variance vs budget.

Reports: daily progress, expenditure ledger, materials usage & wastage, labour & equipment costs, budget vs actual, procurement & supplier balances, milestone completion, delays & risks, final cost summary, closure & handover summary.

Filter by vendor, project, location, date range, status, category, PM. Export to PDF/Excel where the app already supports it.

### 11. Notifications and Alerts

In-app (and email/SMS where available): new assignments, upcoming milestones, overdue tasks, missed progress reports, budget threshold breaches, cost spikes, low stock, pending approvals, delayed deliveries, overdue supplier payments, failed inspections, closure requirements. Configurable by role/event; no duplicates.

### 12. Roles, Permissions, Vendor Isolation

Roles: System Administrator, Vendor/Contractor Owner, Project Manager, Site Engineer/Supervisor, Accountant/Finance Officer, Storekeeper, Procurement Officer, Technician/Field Worker.

Enforce strict tenant/vendor isolation **at the backend**, not just UI. Reuse the existing auth/permission system. All financial changes, approvals, and closures must be auditable.

### 13. Project Closure and Final Cost Reconciliation

Closure checklist (verify before allowing close):
- All mandatory milestones complete or formally waived.
- Final drilling/technical reports recorded.
- Materials reconciled; unused returned/transferred; wastage documented.
- Labour & equipment timesheets finalized.
- Expenses & supplier invoices reconciled.
- Outstanding POs resolved.
- Budget variations approved.
- Outstanding defects addressed/documented.
- Final inspection recorded.
- Handover docs & completion certificates attached.

Generate a final project statement: original budget, approved variations, actual material/labour/equipment/other costs, total final cost, variance vs budget. Preserve full history after closure; reopening requires authorization and creates an audit entry.

---

## Part C: Technical Implementation Rules

Before writing code:
1. Inspect the existing app: architecture, DB schema, auth, roles, inventory, procurement, accounting, reporting.
2. **Explain which existing modules will be reused** and how the project module integrates.

Then follow:
- Reuse existing components/services; **do not duplicate** inventory, supplier, employee, payment, or expense systems.
- Follow existing naming conventions, patterns, and UI style.
- Preserve existing functionality; no unnecessary refactoring.
- Use DB transactions for multi-record financial/inventory operations.
- Add indexes, FKs, validation, constraints.
- Decimal-safe types for money; appropriate precision for quantities.
- Tenant scoping & authorization enforced server-side.
- Validate all input server-side.
- Maintain audit trails for financial/operational changes.
- Prevent duplicate financial postings & repeated inventory deductions.
- Use background jobs for heavy reports/notifications where appropriate.
- Automated tests for calculations, permissions, inventory movements, approvals, closure.
- Safe migrations with rollback.
- Responsive UI (desktop/tablet/mobile) — field teams use mobile.

If Laravel: follow the existing Laravel version and conventions (models, migrations, policies, services, controllers/Livewire, jobs, events, notifications, tests). **Do not assume the framework or UI library** until the codebase is inspected.

---

## Part D: Deliverables (in priority order)

1. **Codebase review + integration plan** (short, at the top of your response).
2. **Module portal + in-workspace switcher** (Part A).
3. **Database migrations/models**: `projects`, `project_milestones`, `project_progress_reports`, `project_expenses`, `project_materials`, `project_tasks`, `project_team_assignments`, `project_activity_log`. Link to existing vendor/tenant/user records — do not recreate them.
4. **API endpoints (CRUD)** for each entity with server-side validation and tenant scoping.
5. **UI**: portal page, project list, project detail with tabs (Overview / Milestones / Progress / Expenses / Materials / Tasks / Financials / Technical / Closure), and create/edit forms.
6. **Auto-calculated totals** server-side: total material cost, total expenses, total project cost, budget variance, budget utilization.
7. **Seed or manual test flow** verifying totals calculate correctly and no double-counting occurs.
8. **Automated tests** for cost calculations, tenant isolation, and closure rules.

---

## Acceptance Criteria

- User can switch between Water Billing and Project Management from the portal **and** from inside either module.
- A vendor can create a borehole project and track it Day One → handover.
- PM can submit daily progress reports with photos/documents.
- Materials issued/consumed/returned/wasted reconcile correctly.
- Expenses, labour, equipment, and procurement contribute to the project financial summary **without double-counting**.
- System compares approved budget vs actual and forecast final cost.
- Delays, outstanding tasks, and budget overruns are visible.
- Every closed project has an auditable final technical & financial statement.
- Vendors cannot access another vendor's project data.
- Existing modules keep working; automated tests verify key workflows and calculations.
- **No client/customer, client-invoice, or client-revenue concepts** exist in the project module.

---

## Implementation Order

1. Inspect codebase → write integration plan.
2. Migrations + models + lifecycle.
3. Portal + in-workspace switcher.
4. Project CRUD + milestones.
5. Progress reports + activity log.
6. Expenses + materials + labour/equipment.
7. Financial rollups (cost, budget, forecast).
8. Tasks + scheduling.
9. Procurement integration.
10. Technical records.
11. Dashboards + reports.
12. Notifications.
13. Roles/permissions/vendor isolation.
14. Closure + final reconciliation.
15. Tests + docs.

Tests to be run after finishing. Do not replace existing functionality with mock data, and do not stop at a UI prototype. When a decision is ambiguous, choose the simplest reasonable option and note it in your summary.