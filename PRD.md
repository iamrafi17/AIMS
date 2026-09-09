# Product Requirements Document: Academic Internship Monitoring System (AIMS)

| Field | Value |
|---|---|
| Product | Academic Internship Monitoring System (AIMS) |
| Institution | Marinduque State University - Santa Cruz Campus |
| Document status | Draft for stakeholder review |
| Version | 3.1 |
| Date | September 1, 2026 |
| Basis | Direct inspection of the AIMS Laravel backend (routes, models, migrations, controllers) and React frontend (pages, routes, services, components) |
| Stack | Laravel 12 + Sanctum API backend; React (Vite) + React Router + Tailwind + Recharts + Leaflet frontend; MySQL/MariaDB-compatible schema |
| Product owner | To be assigned by the University |

### Revision history

| Version | Date | Summary |
|---|---|---|
| 2.0 | August 10, 2026 | Repository-derived baseline covering the six-role internship lifecycle. |
| 2.1 | August 15, 2026 | Documented the revised student-controlled travel and multi-stage report-validation workflow, workday-aware attendance behavior, student academic assignments in Admin User Management, configurable server-side page sizing, login readability/branding fixes, and related dashboard, notification, audit, schema, and automated-test updates. Corrected the backend framework version to Laravel 12. |
| 2.2 | August 15, 2026 | Added HTE batch schedule configuration by Academic Section, reusable common schedule tools for selected days, and a final editable Monday-to-Sunday schedule with program-scoped persistence and validation. |
| 2.3 | August 15, 2026 | Replaced Coordinator Daily Journal approval/rejection with a non-approval review acknowledgment (`pending`/`reviewed`) and optional review notes, while preserving separate attendance verification and HTE Supervisor review workflows. |
| 2.4 | August 16, 2026 | Updated the HTE list to display the latest saved batch/per-day schedule instead of stale HTE default hours, grouping days that share the same AM/PM pattern and refreshing the list immediately after a schedule save. |
| 2.5 | August 16, 2026 | Embedded Batch Schedule Configuration and the final editable Per-Day Configuration in Add HTE. A new HTE and its selected Academic Section schedule are validated and created atomically, while the separate schedule editor remains available for later batch-specific changes. |
| 2.6 | August 16, 2026 | Removed the redundant Default Working Hours controls from HTE forms and made each Per-Day Configuration checkbox the authoritative working-day state. Apply changes only checked days; unchecked days are disabled and persisted as non-working while legacy HTE defaults are maintained internally for compatibility. |
| 2.7 | August 26, 2026 | Implemented the role-based Report Management Module with dedicated Coordinator, Program Head, and VPAA report APIs; validated server-side date/organizational/status filters; program-scoped and university-scoped aggregation; detailed Coordinator attendance, requirements, progress, HTE deployment, journal, and travel data; Program Head period/section analytics; VPAA institutional filters and program comparison; browser CSV/Excel-compatible export and print-to-PDF; and automated report scope/filter coverage. |
| 2.8 | August 26, 2026 | Replaced browser/client export workarounds with authenticated backend-generated report downloads for all three reporting roles: UTF-8 CSV, true OpenXML `.xlsx`, and rendered PDF attachments. Added consistent report metadata, summaries, branded PDF layout, styled spreadsheet output, active-filter propagation, safe filenames, and automated signature/content/readability checks for generated files. |
| 2.9 | August 26, 2026 | Added a mandatory pre-download review step for Coordinator, Program Head, and VPAA PDF, XLSX, and CSV exports. The shared review dialog displays the selected format, report type, authorized scope, active period/filters, dynamic summary metrics, total row count, and a sample of the actual report rows before confirmation. |
| 3.0 | August 26, 2026 | Aligned Report Management with the attached consolidated-report PRD. Each administrative role now has exactly three reports: Student/Program/Overall Internship, HTE Deployment, and Travel Monitoring at the appropriate detail/review/validation level. Added authenticated backend preview endpoints that return the exact full dataset used by PDF/XLSX/CSV, complete preview actions (change filters, refresh, print, close, confirm download), prepared-by/page metadata, expanded academic/batch/student/HTE/status filters, and the missing Coordinator -> Program Head -> VPAA deployment review chain with required return remarks and notifications. |
| 3.1 | September 1, 2026 | Renamed the generated staff login identifier to User ID across the database contract, backend APIs, authentication and recovery flows, frontend labels, and automated tests while preserving its role-specific format and existing values. |

## 1. Executive Summary

AIMS is a role-based web application that manages the complete academic internship (On-the-Job Training / OJT) lifecycle at Marinduque State University - Santa Cruz Campus. It is implemented as a Laravel + Sanctum REST API (`aims-backend`) consumed by a React single-page application (`aims-frontend`), and already covers six distinct roles: **Student**, **Internship Coordinator**, **Program Head**, **HTE Supervisor**, **VPAA**, and **Administrator**.

The system is a single source of truth for: official OJT enrollment and student self-registration, host training establishment (HTE) records and geofencing, MOA agreements, student-to-HTE deployment, daily attendance via a "smart clock," documentary requirement submission and review, supervisor tasks and evaluations, student-controlled GPS travel sessions with checkpoints, Coordinator-generated travel reports with Program Head and VPAA validation, multi-stage approvals, announcements, in-app notifications, and per-user activity/audit history.

It replaces fragmented spreadsheets, paper forms, chat messages, and manually consolidated reports with a traceable workflow shared by all six roles.

## 2. Problem Statement

The internship process involves several parties and many records that change throughout a semester. When these records are managed through separate tools, the University faces the following problems:

- Student eligibility and registration are difficult to validate consistently.
- Attendance, daily journals, and rendered OJT hours are slow to verify and consolidate.
- Requirements, MOAs, deployment schedules, and approval decisions lack a single audit trail.
- Coordinators and program heads have limited real-time visibility into at-risk or incomplete internships.
- Travel and checkpoint evidence for off-site/field activities are difficult to monitor centrally.
- Students receive delayed or inconsistent feedback about requirements and approvals.
- University-wide reports require repetitive manual consolidation across programs and colleges.

## 3. Product Vision

Provide a secure, reliable, and easy-to-use internship operations portal that lets every authorized stakeholder see and act on the right information at the right time, while preserving an auditable history of the student's internship journey from enrollment to completion.

## 4. Product Goals

### 4.1 Primary goals

1. Digitize the internship lifecycle from official enrollment through completion, dropout, or deactivation.
2. Provide accurate, timely monitoring of attendance, daily activities/journals, and accumulated OJT hours against each student's required hours.
3. Standardize requirement, MOA, deployment, and multi-stage approval workflows with a clear decision owner at each stage.
4. Enforce role-based and college/program-scoped access to student, HTE, and reporting data.
5. Give students clear status, feedback, and in-app notifications for every decision that affects them.
6. Give decision-makers (Coordinator, Program Head, VPAA, Admin) consolidated dashboards and analytics.
7. Preserve per-user activity history for accountability.
8. Support GPS-verified, geofenced attendance and travel monitoring for on-site and off-campus activities.

### 4.2 Success metrics

Targets below must be confirmed by University stakeholders before production launch.

| Metric | Initial target |
|---|---:|
| Eligible students who complete registration without staff data correction | >= 95% |
| Active interns who record attendance through AIMS | >= 90% weekly |
| Submitted requirements with a decision within three working days | >= 90% |
| Deployment/MOA final decisions within five working days | >= 90% |
| Attendance records requiring manual correction | <= 5% |
| Critical actions with an attributable activity-log record | 100% |
| Monthly availability during active internship periods | >= 99.5% |
| P95 response time for normal read operations | <= 2 seconds |

## 5. Scope

### 5.1 In scope for the initial release (already substantially implemented)

- Public landing portal, authentication, self-registration against an official enrollment list, and password recovery via security questions.
- Student workspace: dashboard, attendance/smart clock, requirement uploads, travel participation, announcements, notifications, profile.
- Coordinator operations: enrollment import, student CRUD and registration approval, HTE/MOA/holiday management, requirement checklist definition, attendance and journal review, monitoring completed student travel evidence, preparation/submission/revision of official travel reports, dashboard.
- Program Head review: program-scoped dashboard, student monitoring, requirement/MOA document review and endorsement, travel-report validation/return, and forwarding to the VPAA.
- Hierarchical Report Management: detailed Coordinator operational reports, summarized Program Head reports for the assigned program, and consolidated VPAA reports across the University, all generated from existing AIMS records with role-authorized filters and exports.
- HTE Supervisor workspace: assigned-intern progress, task assignment, attendance review, midterm/final evaluations.
- VPAA approvals: final approval queues for documents, deployments, and MOAs; university-wide dashboard, travel oversight, and announcement authoring.
- Administrator console: user management, academic structure (colleges/programs/sections), system settings, system-wide reports.
- Shared cross-role features: announcements with read receipts, in-app notifications, personal activity log ("My Activity").

### 5.2 Non-goals for the initial release

- Payroll, allowance, billing, or accounting functionality.
- A learning management system for course content or grading beyond internship evaluations.
- Native Android or iOS applications; the web interface must instead be responsive.
- Biometric identity verification.
- Continuous background GPS tracking (location is only captured at explicit clock/checkpoint actions).
- Automatic government or third-party regulatory submission.
- Real-time chat or video conferencing.
- General HR management for HTE employees.
- Offline-first attendance synchronization.

## 6. Users and Access Model

AIMS defines exactly six roles via `users.role`: `student`, `coordinator`, `program_head`, `supervisor`, `vpaa`, `admin`. Every protected API route is gated by a `role:<name>` middleware, and the frontend mirrors this with per-role route guards (`RoleBasedRoute`) and a role-to-dashboard redirect map.

| Role | Primary need | Authorized scope |
|---|---|---|
| Student Intern | Complete internship obligations and track personal progress | Own profile, attendance, requirements, assigned travel, announcements, and notifications |
| Internship Coordinator | Operate internships for an assigned college/program | Students, attendance, requirements, HTEs, holidays, MOAs, deployments, travel, and dashboards within the assigned scope |
| Program Head | Review and monitor program-level internship compliance | Requirement/MOA review and endorsement, student monitoring, travel checkpoint verification, reports within the assigned program |
| HTE Supervisor | Monitor assigned interns and submit workplace reviews | Assigned students only: tasks, attendance reviews, progress, and evaluations |
| VPAA | Make final institutional decisions and publish announcements | University-level approval queues (documents, deployments, MOAs), travel oversight, announcements, dashboards, and reports |
| Administrator | Maintain platform access and reference data | Users, roles, account states, colleges, programs, sections, system settings, and system-wide reports |

### 6.1 Access principles

- A user must authenticate (Sanctum token) before accessing any non-public route; every authenticated route also passes through an activity-logging middleware.
- Backend `role:` middleware is the enforcement point; a hidden frontend menu item is not sufficient authorization.
- Coordinator, Program Head, and Supervisor accounts carry a `college_id`/`program_id` (staff) or explicit assignment (`supervisor_id` on `students`) that scopes which student records they may read or modify.
- A student may only access records linked to their own `student_id`.
- A student travel session shall resolve its responsible Coordinator from the Student's linked official OJT enrollment when valid, with a fallback to the first active Coordinator assigned to the same college/program; absence of an eligible Coordinator blocks session creation.
- `users.is_active` gates authentication; deactivating a user must not delete their historical attendance, requirements, approvals, evaluations, or activity records.
- Staff accounts (coordinator, program_head, supervisor, admin, vpaa) receive an auto-generated, unique `user_id`; students authenticate with their `student_id`/School ID.

## 7. Core Internship Lifecycle

1. A Coordinator adds the official OJT enrollment list (`ojt_enrollments`), individually or via CSV import with preview/confirmation.
2. An eligible student verifies their School ID against the enrollment list and self-registers; academic details are copied from the enrollment record, not typed freely.
3. The Coordinator approves or rejects the registration (`students.registration_status`); only `approved` students can sign in and use the student workspace.
4. The Coordinator deploys the student to an HTE and supervisor, and sets the internship schedule, required hours, workdays, and past-entry permission.
5. The Coordinator uploads/maintains the HTE's MOA; the Program Head endorses it and the VPAA gives final approval before it is considered valid.
6. The student records daily attendance through the smart clock (AM/PM, full-day/half-day, WFO/WFH/field) and logs AM/PM journal activities, and uploads the program's required documents.
7. Coordinators review attendance/journals; the assigned HTE Supervisor separately reviews attendance and records evaluations and tasks; Program Head and VPAA review/approve documents and deployments within their authority.
8. When applicable, the eligible student initiates a GPS-tracked travel session, records starting location, departure, optional route checkpoints, and final arrival, uploads required arrival-photo evidence, and ends the session to lock the evidence.
9. The assigned Coordinator reviews the completed evidence, generates and submits a versioned travel report; the Program Head validates or returns it and explicitly forwards validated reports to the VPAA; the VPAA issues final validation or returns the report through the revision chain.
10. Dashboards and reports (per role) consolidate progress until the internship becomes `completed`, `dropped`, or otherwise closed.

## 8. Functional Requirements

Priority definitions: **P0** is required for the initial production release (already implemented in the codebase and must be verified/hardened), **P1** is important but may follow after the core launch, and **P2** is an enhancement.

### 8.1 Public portal, authentication, and accounts

Backend: `AuthController`, `CollegeController`, `PublicPortalController`. Frontend: `pages/public/LandingPage`, `pages/auth/{LoginPage, RegisterPage, ForgotPasswordPage}`.

| ID | Priority | Requirement |
|---|---|---|
| AUTH-01 | P0 | The system shall provide a public landing page (`GET /portal`) with institutional information and role-based portal entry points, and public read-only access to colleges, programs, and HTEs. |
| AUTH-02 | P0 | The system shall authenticate via `POST /login` using email, user ID, or student School ID plus password, and issue a revocable Sanctum token. |
| AUTH-03 | P0 | The system shall support explicit logout (`POST /logout`) that revokes the current token. |
| AUTH-04 | P0 | Registration (`POST /register`) shall only proceed when the School ID exists in `ojt_enrollments` (`GET /registration/enrollment/{schoolId}`) and is not already linked to a registered student. |
| AUTH-05 | P0 | Registration shall copy the student's name, college, program, and section from the enrollment record rather than trusting freely-typed form input. |
| AUTH-06 | P0 | A newly registered student's `registration_status` shall default to `pending` and shall remain unable to sign in until a Coordinator sets it to `approved`. |
| AUTH-07 | P0 | The registration wizard shall capture consent (`consent_status`) to terms and data-privacy notices as a distinct step. |
| AUTH-08 | P0 | The system shall provision 12 seeded security questions (`security_questions`); each user selects and answers 3 (`user_security_questions`, hashed answers) for password recovery. |
| AUTH-09 | P0 | Password recovery shall verify the user's identifier, verify 3 security-question answers (`POST /forgot-password`, `/forgot-password/verify`), and allow reset (`POST /reset-password`), all rate-limited (10/min). |
| AUTH-10 | P0 | Users shall maintain their own account, profile, contact, and password fields (`PUT /profile*`) and upload/remove an avatar (`POST`/`DELETE /profile/avatar`). |
| AUTH-11 | P1 | The system shall support an institution-controlled password recovery path as an alternative to security questions (e.g., admin-initiated reset is already available; a self-service email-based reset is a future enhancement). |

### 8.2 Student internship workspace

Backend: `StudentDashboardController`, `StudentAttendanceController`, `StudentRequirementController`, `StudentTravelController`. Frontend: `pages/student/{Dashboard, Attendance, Requirements, Travel, Announcements, Profile}`.

| ID | Priority | Requirement |
|---|---|---|
| STU-01 | P0 | `GET /student/dashboard` shall return internship status, HTE assignment, rendered vs. required OJT hours, attendance summary, requirement completion, and recent announcements/notifications for the KPI cards and progress ring shown on `Dashboard.jsx`. |
| STU-02 | P0 | `GET /student/attendance` and `/student/attendance/workspace` shall provide a monthly calendar and per-date detail used by the attendance calendar UI. The workspace shall expose the student's effective workdays, calculate remaining workdays for the current week within the active OJT period, and identify non-working dates as unavailable unless an attendance record already exists. |
| STU-03 | P0 | Students shall create `present`, `absent`, or `holiday` entries (`POST /student/attendance/entry`) subject to `allow_past_attendance` and configured date windows. |
| STU-04 | P0 | Present entries shall support `work_mode` of `wfo`, `wfh`, or `field`, and `session_type` of `full_day`, `am_half`, or `pm_half`. |
| STU-05 | P0 | Smart clock (`GET/POST /student/attendance/quick-clock`, `POST /student/attendance/clock-in`, `/clock-out`) shall use server timestamps and enforce a valid AM/PM time-in/time-out sequence per session. |
| STU-06 | P0 | WFO smart clock actions shall capture latitude/longitude and validate against the assigned HTE's geofence (`geofence_enabled`, `geofence_radius`) when configured. |
| STU-07 | P0 | Students shall record AM/PM journal activities (`am_activity`, `pm_activity`) alongside attendance for later Coordinator journal review. |
| STU-08 | P0 | `GET /student/requirements` shall list the student's program requirement checklist with status (`pending`/`approved`/`rejected`) and reviewer feedback. |
| STU-09 | P0 | Students shall upload (`POST /student/requirements/{id}/upload`) and download (`GET /student/requirements/{id}/download`) requirement files; re-uploading shall return the item to `pending`. |
| STU-10 | P0 | `GET /student/travel` shall return the student's eligibility, assigned Coordinator, current session, and travel history. An eligible student shall start a session (`POST /student/travel/start`), record departure (`POST /student/travel/{travel}/departure`), add zero or more route checkpoints (`POST /student/travel/{travel}/checkpoints`), mark final arrival (`POST /student/travel/{travel}/arrival`), upload required arrival evidence (`POST /student/travel/{travel}/arrival-photo`), and end (`POST /student/travel/{travel}/end`) the session. Every route action shall capture server time, latitude, longitude, GPS accuracy when available, and a readable location. |
| STU-11 | P0 | Students shall view announcements targeted to `all` or `students`, and receive/mark-read in-app notifications that deep-link to the relevant screen. |
| STU-12 | P0 | Students shall maintain personal, guardian, and contact profile fields and change their password from `Profile.jsx`. |

### 8.3 Coordinator operations

Backend: `CoordinatorDashboardController`, `CoordinatorStudentController`, `CoordinatorAttendanceController`, `CoordinatorHTEController`, `CoordinatorRequirementController`, `CoordinatorTravelController`. Frontend: `pages/coordinator/{Dashboard, StudentManagement, AttendanceManagement, HTEManagement, TravelMonitoring, Reports, Announcements, Profile}`.

| ID | Priority | Requirement |
|---|---|---|
| COR-01 | P0 | Coordinators shall manage `ojt_enrollments` individually and via CSV (`POST /coordinator/students/import` preview, `/import/confirm` commit). The OJT Enrollment List shall use server-side pagination with selectable page sizes of 10, 25, 50, or 100 entries. |
| COR-02 | P0 | Coordinators shall list/search/filter (`GET /coordinator/students`), view, create, update, and delete student records (`CoordinatorStudentController` apiResource) within their college/program. The student list shall use server-side pagination with selectable page sizes of 10, 25, 50, or 100 entries. |
| COR-03 | P0 | Coordinators shall approve (`POST /coordinator/students/{student}/approve`) or reject (`.../reject`, reason required) registrations, and deactivate (`PUT .../deactivate`) student accounts. |
| COR-04 | P0 | Coordinators shall define, edit, reorder (`PUT /coordinator/requirements/order`), and deactivate the program's official requirement checklist (`program_requirements`) without deleting historical `internship_requirements` submissions. |
| COR-05 | P0 | Coordinators shall review submitted requirements (`POST /coordinator/students/{student}/requirements/{requirement}/review`), download evidence, and approve/reject with feedback. |
| COR-06 | P0 | Coordinators shall list/inspect (`GET /coordinator/attendance`, `/{attendance}`), edit (`PUT`), verify (`PUT .../verify`), and acknowledge Daily Journals as reviewed (`POST .../journal-review`) with optional review notes for attendance within their program. Daily Journals shall not use approve/reject decisions. Attendance Logs and Daily Journals shall share server-side pagination with selectable page sizes of 10, 25, 50, or 100 entries. |
| COR-07 | P0 | Coordinators shall create/update/delete HTEs (`CoordinatorHTEController`), including geofence center/radius/enabled flag and default AM/PM schedule and workdays. |
| COR-08 | P0 | Coordinators shall deploy a student to an HTE and supervisor (`PUT /coordinator/htes/deployments/{student}`) with internship dates, required hours, schedule, and workdays. A saved deployment request shall enter Program Head review before it can appear in the VPAA final-validation queue. |
| COR-09 | P0 | Coordinators shall manage holidays (`POST/PUT/DELETE /coordinator/htes/holidays*`) scoped to college/program, including recurring holidays. |
| COR-10 | P0 | Coordinators shall upload and maintain MOAs (`POST/PUT/DELETE /coordinator/htes/moas*`) with an HTE, effective/expiration dates, and a PDF, and download the MOA file. The upload form shall not ask for a college; the API shall always assign the logged-in Coordinator's authoritative college and program scope. |
| COR-11 | P0 | Coordinators shall monitor travel sessions assigned to them, review completed Student-submitted GPS/timestamp/checkpoint/arrival-photo evidence, generate one official travel report per completed session (`POST /coordinator/travel/{travel}/reports`), edit report remarks while the report is editable, and submit or resubmit the report to the Program Head. Coordinators shall not alter the Student's preserved route evidence. |
| COR-12 | P0 | The Coordinator dashboard (`GET /coordinator/dashboard`) shall summarize total/active/completed/pending/for-approval student counts, attendance and requirement summaries, and recent activity. |
| COR-13 | P1 | The Coordinator Reporting Center shall expose exactly three consolidated reports: Student Internship Report, HTE Deployment Report, and Travel Monitoring Report. `GET /coordinator/reports` shall return program-scoped, validated date, Academic Section/batch, academic-year, semester, student, HTE, internship-status, attendance-status, and work-mode data. `GET /coordinator/reports/preview` shall return the complete template dataset used unchanged by `GET /coordinator/reports/export/{format}`. The Student report shall combine identity, assignment, attendance summary, requirements summary, OJT hours/progress, and status; deployment and travel shall remain separate because they have approval workflows. |
| COR-14 | P0 | Coordinator travel monitoring shall categorize sessions as active (`active`, `traveling`, or `arrived`), completed without a submitted report, pending validation, returned for revision, or final validated, and shall display the complete report-validation history and version. |
| COR-15 | P0 | Add HTE shall preserve HTE identity/contact/geofence fields and embed responsive Batch Schedule Configuration plus the final editable Per-Day Configuration, without a duplicate Default Working Hours section. The Coordinator shall select an active Academic Section, set common AM/PM tools, check the actual working days within the Monday-to-Sunday cards, apply common hours only to checked days, and edit each checked day's values independently. Unchecked days shall be disabled and persisted as non-working. The HTE and initial seven-day batch schedule shall be created atomically, with legacy HTE default schedule fields populated internally for compatibility. The separate batch-schedule editor (`GET/PUT /coordinator/htes/{hte}/batch-schedules*`) shall remain available for later batch-specific changes. After saving, the HTE list shall refresh and display only working days from the latest saved batch schedule, grouping days with identical AM/PM hours. |

### 8.4 Program Head review and monitoring

Backend: `ProgramHeadDashboardController`, `ProgramHeadDeploymentController`, `ProgramHeadDocumentController`, `ProgramHeadTravelController`, `ProgramHeadStudentController`. Frontend: `pages/program-head/{Dashboard, DocumentReview, StudentMonitoring, TravelMonitoringManagement, Reports, Announcements}`.

| ID | Priority | Requirement |
|---|---|---|
| PH-01 | P0 | `GET /program-head/dashboard` shall return a program-scoped overview (student counts, average progress/rating, attendance and requirement compliance, MOA and travel status, pending-approval counts). |
| PH-02 | P0 | `GET /program-head/students` shall list the program's students with progress, attendance %, and requirement completion for `StudentMonitoring.jsx`. |
| PH-03 | P0 | Program Heads shall review requirements (`POST /program-head/documents/requirements/{requirement}/review`) and download evidence (`GET .../download`). |
| PH-04 | P0 | Program Heads shall review/endorse MOAs (`POST /program-head/documents/moas/{moa}/review`, `moas.program_status`/`program_reviewed_by`) before VPAA final approval. |
| PH-05 | P0 | Program Heads shall monitor program-scoped travel (`GET /program-head/travel`), review a Coordinator-submitted travel report against the immutable Student GPS route and arrival evidence, validate or return it with a required reason for returns (`PUT /program-head/travel/reports/{report}/review`), and explicitly forward a validated report to the VPAA (`POST /program-head/travel/reports/{report}/forward`). |
| PH-06 | P1 | The Program Head Reporting Center shall expose exactly three program-scoped reports: Program Internship Summary Report, HTE Deployment Review Report, and Travel Monitoring Review Report. It shall validate date, Academic Section/batch, academic-year, and semester filters. `GET /program-head/reports/preview` shall return the complete dataset used by every export. Program Heads may forward a pending Coordinator deployment through `PUT /program-head/deployments/{approval}/review` or return it with required remarks; only forwarded deployment requests enter the VPAA queue. Existing travel review/return/forward behavior remains authoritative. |

### 8.5 HTE Supervisor workspace

Backend: `SupervisorDashboardController`, `SupervisorManagementController`. Frontend: `pages/supervisor/{Dashboard, StudentProgress, Evaluations, AttendanceReview, Announcements}`.

| ID | Priority | Requirement |
|---|---|---|
| SUP-01 | P0 | `GET /supervisor/progress` shall list only students where `students.supervisor_id` matches the logged-in supervisor, with rendered/required hours and progress %. |
| SUP-02 | P0 | Supervisors shall create (`POST /supervisor/tasks`) and update (`PUT /supervisor/tasks/{task}`) tasks (`internship_tasks`: title, description, due date, priority, status) for assigned interns. |
| SUP-03 | P0 | `GET /supervisor/attendance` shall list assigned interns' attendance; `PUT /supervisor/attendance/{attendance}/review` shall set `supervisor_review_status` to `approved` or `flagged` with feedback required when flagged. |
| SUP-04 | P0 | Supervisors shall create/update evaluations (`POST /supervisor/evaluations`, `evaluations` table) of type `midterm` or `final`, rating 6 criteria (work quality, communication, professionalism, attendance, technical skills, teamwork) on a 1-5 scale, plus recommendations/feedback, saved as `draft`, `submitted`, or `finalized`. |
| SUP-05 | P0 | `GET /supervisor/dashboard` shall summarize assigned interns, tasks, pending attendance reviews, and evaluation counts (midterm/final remaining). |

### 8.6 VPAA approvals and communications

Backend: `VPAADashboardController`, `VPAAApprovalController`, `VPAAMOAController`, `AnnouncementController`, `ProgramHeadTravelController` (shared). Frontend: `pages/vpaa/{Dashboard, Approvals, MOAApproval, Reports, Announcements}`.

| ID | Priority | Requirement |
|---|---|---|
| VPAA-01 | P0 | `GET /vpaa/approvals` shall present separate pending queues and decision history for `approval_records` where `subject_type` is `document` or `deployment`. |
| VPAA-02 | P0 | `GET /vpaa/moas` shall present MOAs endorsed by a Program Head (`program_status = approved`) awaiting VPAA final decision, plus history. |
| VPAA-03 | P0 | The VPAA shall inspect metadata and download supporting files (`GET /vpaa/approvals/{approval}/download`, `/vpaa/moas/{approval}/download`) before deciding. |
| VPAA-04 | P0 | `PUT /vpaa/approvals/{approval}` and `PUT /vpaa/moas/{approval}` shall accept exactly one final decision (`approved`/`rejected`); a second decision on an already-decided record shall be rejected by the API. |
| VPAA-05 | P0 | Rejection shall require `remarks`; approval remarks shall be optional. A decision shall stamp `decided_by`/`decided_at` and notify the submitter. |
| VPAA-06 | P0 | The VPAA shall create, update, publish, archive, restore, and delete announcements (`AnnouncementController@manage/store/update/archive/restore/destroy`) with optional file attachments and a `target_audience`. |
| VPAA-07 | P0 | The VPAA shall have a university-wide dashboard and exactly three reports: Overall Internship Summary Report, HTE Deployment Validation Report, and Travel Monitoring Validation Report. `GET /vpaa/reports` shall validate date, college, program, HTE, academic year, semester, and internship-status filters; `GET /vpaa/reports/preview` shall return the complete export dataset. Deployment validation shall list only requests forwarded by a Program Head, while travel validation shall use the existing final-review workflow and preserved validation history. |
| VPAA-08 | P0 | The VPAA travel view shall list only reports that reached the final-validation stage, allow final validation or return with a required reason (`PUT /vpaa/travel/reports/{report}/review`), preserve the validation decision/history, notify affected users, and make final-validated reports read-only. |

### 8.7 Administration

Backend: `AdminDashboardController`, `AdminUserController`, `AdminSystemController`. Frontend: `pages/admin/{Dashboard, UserManagement, AcademicManagement, SystemSettings, Reports}`.

| ID | Priority | Requirement |
|---|---|---|
| ADM-01 | P0 | Administrators shall search/filter/create/update/delete users (`AdminUserController` apiResource) across all six roles. The user list shall expose selectable server-side page sizes of 10, 15, 25, or 50 entries. |
| ADM-02 | P0 | Administrators shall change a user's role (`PUT /admin/users/{user}/role`) and toggle active status (`PUT .../status`); role changes to staff roles must carry a valid college/program assignment. |
| ADM-03 | P0 | Administrators shall reset a user's password directly (`POST /admin/users/{user}/reset-password`). |
| ADM-04 | P0 | Administrators shall manage colleges, programs, and academic sections (`AdminSystemController@academic` + `store/update/destroy*`) with referential-integrity protection (e.g., cannot delete a college with active programs/students). |
| ADM-05 | P0 | Administrators shall manage typed `system_settings` (group/key/value/type) via `GET/PUT /admin/settings`. |
| ADM-06 | P0 | `GET /admin/dashboard` shall summarize total users by role, active interns, pending registrations, completed internships, monthly attendance, and recent login activity. |
| ADM-07 | P1 | `GET /admin/reports` shall provide system-wide analytics/exports by academic year, semester, college, program, and status. |
| ADM-08 | P0 | Admin User Management shall display each student's college and program from the linked `students` profile when those assignments are not stored directly on the `users` row. College filtering shall match both staff assignments on `users.college_id` and student assignments on `students.college_id`. |

### 8.8 Shared communication and audit features

Backend: `AnnouncementController`, `NotificationController`, `ActivityLogController`, `RecordActivityLog` middleware. Frontend: `components/announcements/AnnouncementCenter`, `pages/shared/{Notifications, MyActivity}`.

| ID | Priority | Requirement |
|---|---|---|
| COM-01 | P0 | `GET /announcements` shall return published announcements targeted to `all` or the caller's role (`students`, `coordinators`, `program_heads`, `vpaa`, `admin`, `supervisor`). |
| COM-02 | P0 | `POST /announcements/{announcement}/read` shall record a per-user read receipt (`announcement_reads`); `GET .../download` shall serve the attachment to authorized readers only. |
| COM-03 | P0 | `GET /notifications` shall list `system_notifications` for the caller; `PUT /notifications/{notification}/read` and `/notifications/read-all` shall mark items read. |
| COM-04 | P0 | Actionable notifications shall carry an `action_url` that deep-links to the relevant authorized screen. |
| AUD-01 | P0 | `RecordActivityLog` middleware shall persist an `activity_logs` entry (actor, `activity_type`, description) for significant authenticated actions returning HTTP 200-399. |
| AUD-02 | P0 | `GET /activity-logs` shall return only the caller's own activity; there is currently no privileged institution-wide audit search screen (see Section 17, Open Decision 12). |

## 9. Business Rules and Status Models

### 9.1 Registration and internship (`students` table)

- `registration_status`: `pending`, `approved`, `rejected`.
- `internship_status`: `pending`, `active`, `completed`, `dropped`.
- `consent_status` and `schedule_status` track registration sub-steps.
- Only students with `registration_status = approved` (and `users.is_active = true`) may authenticate.
- Deactivating a student/user must preserve historical attendance, requirements, approvals, evaluations, and activity records.
- `ojt_enrollments` (unique `school_id`, one-to-one with `students.student_record_id`) is the authority for identity and academic assignment during self-registration; a School ID can register only once.

### 9.2 Attendance (`attendance` table)

- `status`: `present`, `absent`, `holiday`, `late`.
- `work_mode` (present only): `wfo`, `wfh`, `field`.
- `session_type`: `full_day`, `am_half`, `pm_half`.
- Unique constraint on (`student_id`, `date`) — only one attendance record per student per day.
- Smart clock timestamps (`am_time_in`, `am_time_out`, `pm_time_in`, `pm_time_out`, `ot_start`, `ot_end`) are server-generated; client-submitted times are informational only.
- Past-date entry is blocked unless `students.allow_past_attendance` is enabled by a Coordinator.
- WFO geofence enforcement (using `latitude_in`/`longitude_in` vs. HTE `latitude`/`longitude`/`geofence_radius`) applies only when the assigned HTE has `geofence_enabled = true` and valid coordinates/radius.
- Three independently tracked review dimensions exist on the same record: `is_verified`/`verified_by` (Coordinator attendance verification), `journal_status` (`pending`/`reviewed`)/`journal_feedback`/`journal_reviewed_by` (Coordinator Daily Journal review acknowledgment, not approval), and `supervisor_review_status`/`supervisor_feedback`/`supervisor_reviewed_by` (Supervisor attendance review). These must remain distinguishable in UI and reports.
- `overtime_hours` and location snapshots (`am_time_in_location`, etc.) are stored per session for audit purposes.

### 9.3 Requirements and approvals

- `internship_requirements.status`: `pending`, `approved`, `rejected`; `program_requirements` defines the ordered, per-program checklist (default seed includes Birth Certificate, Evaluation Checklist, Medical Certificate, Notarized Parent's/Guardian's Consent, Parent's/Guardian's ID, Notarized Training Contract, Sample Accomplished Training Contract, Medical Insurance ID, Official Registration Form/COR, Curriculum Vitae).
- Rejection requires `feedback`; re-uploading evidence must reset the item to `pending` and clear the prior reviewer/feedback for the new cycle.
- `approval_records.status`: `pending`, `approved`, `rejected`, keyed by `subject_type` (`document`, `deployment`, `moa`) + `subject_id`; a `decided_at`/`decided_by` decision is final for that record — duplicate pending records for the same subject/stage are not allowed.

### 9.4 MOA and deployment (`moas` table)

- `status` (VPAA-facing): `pending`, `approved`, `rejected`, `expired`.
- `program_status` (Program Head endorsement) must be `approved` before the VPAA can act; Program Head rejection stops the MOA from reaching the VPAA queue.
- `expiration_date` must be after `effective_date`.
- A student deployment (`students.hte_id`, `supervisor_id`, schedule, `required_ojt_hours`, `work_days`) must reference an HTE within the same college/program as the Coordinator and student.
- The system must be able to identify expired and soon-to-expire (e.g., within 60 days) approved MOAs without altering their historical decision.

### 9.5 Travel (`travel_logs`, `travel_checkpoints`)

- New sessions are student-controlled and solo. The initiating Student user is stored in `travel_logs.created_by`, the responsible Internship Coordinator in `coordinator_id`, and the Student record is linked through `travel_participants`. Companion records are not collected or stored.
- A Student may start only when the account is active, registration is approved, internship is active and within its configured period, an active HTE is assigned, an active matching Coordinator can be resolved, and the Student has no session in `active`, `traveling`, or `arrived` state.
- The session state sequence is `active` (starting location captured) -> `traveling` (departure captured) -> `arrived` (final arrival captured) -> `completed` (arrival photo present and session ended). Invalid, repeated, out-of-order, and cross-account transitions must return an authorization or validation error.
- `travel_checkpoints.point_type` supports `start`, `departure`, `checkpoint`, and `final_arrival`. Points are append-only and ordered by `sequence`; each captures `captured_at` (server time), latitude, longitude, optional `gps_accuracy`, readable location, and optional remarks.
- Final arrival requires a protected `arrival_photo_path` before completion. Student, assigned Coordinator, authorized Program Head, and eligible VPAA reviewers may retrieve it only through authorization-checked endpoints.
- A completed session may have exactly one `travel_reports` record. Reports begin as `draft`; Coordinator remarks are editable only in `draft`, `for_revision`, or `returned_by_vpaa`.
- The report sequence is `draft` -> `pending_program_head` -> `program_head_validated` -> `pending_vpaa` -> `final_validated`. A Program Head return sets `for_revision`; a VPAA return sets `returned_by_vpaa`. Resubmission increments `version` and repeats the complete Program Head-to-VPAA chain.
- Every validate, return, forward, and resubmit action shall create a `travel_report_validations` history item with reviewer, role, action, reason/comments, timestamp, and report version. A return requires a reason; final-validated reports and completed route evidence are read-only.
- Travel status notifications shall be sent to the next responsible reviewer and affected Student/Coordinator, and significant travel/report actions shall be recorded in the personal activity log.

## 10. Key Data Entities

Derived directly from `app/Models` and `database/migrations`.

| Entity (table) | Purpose | Key relationships |
|---|---|---|
| User (`users`) | Authentication, `role` enum, `is_active`, `user_id`, contact info, avatar | hasOne Student; belongsTo College |
| Student (`students`) | Academic, personal, guardian, HTE/supervisor assignment, schedule, registration/internship status, OJT progress | belongsTo User/College/Program/HTE/Supervisor(User); hasMany Attendance/InternshipRequirement/TravelLog/Evaluation/InternshipTask |
| OjtEnrollment (`ojt_enrollments`) | Official pre-registration eligibility list, unique `school_id` | belongsTo College/Program; belongsTo User (`added_by`); belongsTo Student (`student_record_id`) |
| College / Program / AcademicSection | Academic hierarchy and scoping, per-college `required_ojt_hours` | College hasMany Program; Program hasMany AcademicSection |
| HTE (`htes`) | Host organization contact data, address, geofence center/radius/enabled flag, default schedule | belongsTo College/Program; hasMany Student, MOA, HteBatchSchedule |
| HteBatchSchedule (`hte_batch_schedules`) | Final AM/PM schedule and `is_working` state for one HTE, Academic Section batch, and weekday; unique per HTE/batch/day | belongsTo HTE and AcademicSection |
| MOA (`moas`) | HTE agreement file, validity window, Program Head endorsement, VPAA final approval | belongsTo HTE/College/Program; belongsTo User (`program_reviewed_by`, `approved_by`) |
| Attendance (`attendance`) | Daily time records, work mode/session, journal, three independent review states | belongsTo Student; belongsTo User (verifier/journal reviewer/supervisor reviewer) |
| ProgramRequirement (`program_requirements`) | Ordered, versionable definition of a program's required document | belongsTo Program; hasMany InternshipRequirement |
| InternshipRequirement (`internship_requirements`) | Student submission: file, status, feedback, reviewer | belongsTo Student, ProgramRequirement; belongsTo User (`reviewed_by`) |
| TravelLog / TravelCheckpoint | Solo student-controlled travel session, responsible Coordinator, ordered GPS route evidence, and final arrival photo | TravelLog belongsTo Creator(User), Coordinator(User), and HTE; hasMany TravelCheckpoint; belongsToMany Student via `travel_participants`; hasOne TravelReport |
| TravelReport (`travel_reports`) | Versioned Coordinator report generated from one completed travel session and routed through Program Head and VPAA validation | belongsTo TravelLog and Coordinator(User); hasMany TravelReportValidation |
| TravelReportValidation (`travel_report_validations`) | Append-only validation/return/forward/resubmission history tied to a specific report version | belongsTo TravelReport and Reviewer(User) |
| InternshipTask (`internship_tasks`) | Supervisor-assigned work item and status | belongsTo Student, User (supervisor) |
| Evaluation (`evaluations`) | Supervisor assessment (6 rated criteria), recommendations, feedback, draft/submitted/finalized | belongsTo Student, User (supervisor) |
| ApprovalRecord (`approval_records`) | Generic, polymorphic-by-string decision request for document/deployment/moa | belongsTo User (`submitted_by`, `decided_by`) |
| Announcement / AnnouncementRead | Targeted communication, attachment, publish/archive lifecycle, read receipts | belongsTo User (`author_id`); belongsToMany User via `announcement_reads` |
| SystemNotification (`system_notifications`) | User-specific actionable notice with `action_url` and `read_at` | belongsTo User |
| ActivityLog (`activity_logs`) | Attributable record of significant user actions, indexed by (`user_id`, `created_at`) | belongsTo User |
| Holiday (`holidays`) | College/program-scoped holidays, optional recurrence | belongsTo College/Program |
| SecurityQuestion / UserSecurityQuestion | Seeded question bank; 3 hashed answers per user for password recovery | UserSecurityQuestion belongsTo User, SecurityQuestion |
| SystemSetting (`system_settings`) | Typed, grouped configuration key/value store | none (standalone) |

## 11. User Experience Requirements

Observed and required patterns from the current React implementation (Tailwind, Recharts, Leaflet, react-hot-toast):

- The interface must be responsive for current desktop and mobile browsers, with a collapsible sidebar and dark-mode support (`ThemeContext`).
- The application shall use the MarSU logo for browser branding, and login identifier/password fields—including browser-autofilled values—must retain readable foreground/background contrast independently of the selected application theme.
- Navigation must be role-specific (`Sidebar` filters by `user.role`) and consistent across screens (`Header` + `Sidebar` layout, notification bell with unread badge).
- Every asynchronous action must show loading, success (toast), validation, and failure feedback.
- Destructive or irreversible actions (delete, deactivate, final approval/rejection) must require confirmation and clearly name the affected record.
- Statuses must use a consistent color palette across the app (green = approved/active/present, red = rejected/absent/cancelled, amber = pending/late, blue = completed/info, violet = holiday, slate = inactive).
- Empty states must explain why no data is present and, when applicable, provide the next action.
- Forms must preserve valid user input after validation errors; multi-step forms (registration, evaluations) must retain progress across steps.
- Dates and times must be displayed in the configured campus timezone (Asia/Manila).
- Tables with operational data must provide search, filters, server-side pagination, accessible labels, current-page/total feedback, and an explicit "Entries per page" selector where multiple page sizes are supported.
- Changing a table's page size or any filter that changes its result set must reset that table to page 1. Page-size selectors must use the options defined by the corresponding API contract and remain usable in light mode, dark mode, and responsive layouts.
- Upload screens (requirements, MOAs, avatars, checkpoint/arrival photos) must show allowed formats and size limits before upload.
- Map-based features (`GeofenceMap`, travel route maps) must clearly render geofence circles, checkpoint markers (departure/intermediate/arrival), and route polylines centered on Santa Cruz, Marinduque.
- Location-permission errors must explain how the user can retry without weakening geofence/authorization enforcement.
- The minimum accessibility target is WCAG 2.1 Level AA for core workflows.

## 12. Non-Functional Requirements

### 12.1 Security and privacy

- Enforce TLS/HTTPS in production.
- Hash passwords and security-question answers using an approved adaptive password hashing algorithm (Laravel's default bcrypt/argon hashing).
- Store secrets and production credentials outside source control (`.env`, not committed).
- Every protected controller action must re-verify role and record ownership/scope server-side, not rely solely on `role:` middleware for record-level checks (e.g., a Coordinator must be blocked from another program's student even though both pass `role:coordinator`).
- Rate-limit sign-in, password-recovery, and upload endpoints (recovery routes already throttled to 10/min; extend to login and uploads).
- Validate file extension, MIME type, size, and authorization before storing or serving requirement files, MOAs, avatars, and travel photos.
- Files containing personal or approval evidence should use private storage with authorization-checked download endpoints, not public URLs.
- Apply CSRF protections appropriate to the selected Sanctum authentication mode (SPA cookie vs. bearer token).
- Protect `activity_logs` from being altered by non-privileged users; logs must not contain passwords, tokens, or security-question answers.
- Establish retention, deletion, consent, and data-subject procedures consistent with the Philippine Data Privacy Act and University policy before launch.
- Perform a privacy impact assessment for precise location data (attendance/travel GPS), guardian information, and uploaded documents.

### 12.2 Performance and scale

- Production shall use MySQL or MariaDB rather than SQLite.
- The system shall support at least 1,000 registered accounts and the stakeholder-approved concurrent-user target.
- List and dashboard queries must be indexed (see the `add_high_volume_query_indexes` migration) and use validated server-side pagination to avoid unbounded dataset loading. Supported `per_page` values must be allow-listed by each endpoint rather than accepted as arbitrary client input.
- P95 normal API response time should be no more than two seconds under the agreed load profile, excluding large file transfers.
- Notifications, exports, and heavy file processing should use background queues (`jobs` table already present) rather than blocking requests.
- Caching and horizontal application scaling must be possible without storing essential state on one application server.

### 12.3 Reliability and recovery

- Production availability target: at least 99.5% per month during active internship periods.
- Database and uploaded files (`storage/app`) must have automated backups with tested restoration procedures.
- Initial recovery targets: RPO <= 24 hours and RTO <= 4 hours; stakeholders must confirm these values.
- Multi-record operations (registration approval + notification, MOA approval + status update, travel checkpoint sequencing) must be transactional.
- Failed background jobs must be retryable and observable.
- Application errors must be logged without exposing passwords, tokens, security answers, or unnecessary personal data.

### 12.4 Compatibility and maintainability

- Support the latest two stable versions of Chrome, Edge, Firefox, and Safari at production release.
- Maintain automated feature tests (`tests/Feature`) for authentication, authorization, registration, program scoping, attendance, requirements, approvals, and travel.
- Database changes must use reversible migrations (already the pattern used across 40+ migrations) and documented deployment steps.
- Production configuration must disable debug output (`APP_DEBUG=false`) and use optimized Laravel caches (config/route/view cache).

## 13. Reporting and Analytics

The role-based reporting hierarchy is implemented as follows:

- **Coordinator = detailed/operational.** Exactly three categories are available: Student Internship Report (consolidated student, HTE, attendance, requirements, hours, progress, and status), HTE Deployment Report (batch/schedule/review state), and Travel Monitoring Report (participants, route/checkpoints, evidence, remarks, and validation state).
- **Program Head = program summary/review.** Exactly three categories are available: Program Internship Summary Report, HTE Deployment Review Report, and Travel Monitoring Review Report. `ProgramAccess` always constrains the assigned program, and applicable date, Academic Section/batch, academic-year, and semester filters are enforced server-side.
- **VPAA = university summary/final validation.** Exactly three categories are available: Overall Internship Summary Report, HTE Deployment Validation Report, and Travel Monitoring Validation Report. Date, college, program, HTE, academic-year, semester, and internship-status filters are enforced server-side.

The implemented dynamic report sources and indicators include:

- Registered, pending, approved, active, completed, dropped, and deactivated student counts (Admin, Coordinator, Program Head, VPAA dashboards).
- Attendance counts/rates by date, status, program, HTE, and verification state.
- Rendered hours vs. required hours and students at risk of falling behind.
- Requirement completion and decision turnaround by status and program.
- HTE deployment counts and students without assignments.
- MOA pending/approved/rejected/expired/expiring-soon counts.
- Pending and historical approval-record decisions and turnaround time.
- Active (`active`/`traveling`/`arrived`) and completed travel sessions, route evidence completeness, Coordinator reports by validation status/version, returned reports requiring revision, validation history, and final-validated reports.
- Supervisor task completion, attendance-review, and evaluation completion (midterm/final remaining).
- Announcement reach and read rates where institutionally permitted.

All report APIs read existing AIMS tables and apply authorization before aggregation; users do not re-enter stored data. Each role has an authenticated `/reports/preview` endpoint that invokes the same dataset builder as `/reports/export/{format}`. The shared dialog therefore shows every report row and full cell value—not a separate sample—plus report title, scope, period, generation time, preparer, and summary. It supports Change Filters, Refresh Preview, Print, Close, and confirmed download; automatic download before preview is prevented. The backend returns UTF-8 CSV, true OpenXML XLSX, and rendered A4 PDF with MarSU/AIMS branding, prepared-by metadata, repeated table headings, adaptive orientation, and page numbering. Personally identifiable detail is limited to the Coordinator's assigned program; Program Head and VPAA summary output follows their authorized scope.

## 14. Acceptance Criteria for Initial Production Release

The release is acceptable when all of the following are true:

1. Every P0 requirement is implemented, accepted by its business owner, or formally waived with documented risk.
2. A complete student journey succeeds in staging: enrollment import, registration, approval, deployment, MOA approval, attendance, requirement upload/review, supervisor evaluation, and completion reporting.
3. Document, MOA, and deployment approval workflows have one unambiguous owner at every stage (Coordinator -> Program Head -> VPAA where applicable) and preserve their decision history via `approval_records`.
4. Cross-program and cross-student authorization tests confirm that unauthorized records cannot be read or changed (e.g., Coordinator A cannot see Coordinator B's program's students).
5. Supported upload types, download authorization, rejected files, and storage cleanup are verified for requirements, MOAs, avatars, and travel photos.
6. Attendance time, half-day, geofence, holiday, past-entry, correction, and OJT-hour rules are approved by the internship office and covered by tests.
7. The production-like deployment passes the agreed load test with at least 1,000 accounts and the expected concurrent attendance traffic.
8. Backup restoration, rollback, error logging, monitoring, and incident contacts are tested and documented.
9. Core mobile and desktop workflows pass usability and WCAG 2.1 AA checks.
10. Privacy notices, consent language, data retention, account deactivation, and location-data policies receive institutional approval.
11. No open critical or high-severity security defects remain.
12. User guides and administrator runbooks are available to the intended users.
13. Admin User Management displays the linked college/program for student accounts, and its college filter includes students whose assignment is stored on the `students` profile.
14. The Admin user list and Coordinator OJT enrollment, student, attendance, and journal lists honor their documented `per_page` options, return correct pagination metadata, reset to page 1 after page-size/filter changes, and retain search/filter query parameters while paging.
15. A complete travel journey succeeds in staging: eligible Student start, departure, optional checkpoints, final arrival, required private arrival-photo upload, Student completion, Coordinator report generation/submission, Program Head validation/forwarding, and VPAA final validation.
16. Travel workflow tests reject duplicate active sessions, invalid or repeated state transitions, cross-student/cross-Coordinator access, premature report actions, and attempts to edit a report while under review or after final validation; returned reports increment version and repeat the full validation chain.
17. The Student attendance calendar exposes configured workdays, marks non-working dates unavailable when no record exists, and calculates “Workdays Left” from the remaining configured days in the current week and active OJT period.

18. Coordinator, Program Head, and VPAA each expose exactly three documented report categories. Their preview and export routes return database-derived values only, reject invalid/out-of-scope filters, enforce role/program scope, show the complete shared export dataset before download, support preview refresh/print/filter changes, and download only after explicit confirmation as valid UTF-8 `.csv`, readable OpenXML `.xlsx`, or rendered `%PDF` `.pdf` attachments.

## 15. Delivery Plan

### Phase 1: Policy and workflow confirmation

- Confirm review authority, approval order, official attendance/OJT-hour computation, data retention, success targets, and production ownership.
- Resolve all open questions in Section 17.

### Phase 2: Core launch readiness

- Complete and stabilize all P0 workflows across all six roles.
- Align frontend and backend status transitions (registration, requirement, MOA, approval, travel, attendance review states).
- Confirm whether official signed reports require signature blocks or University-specific templates beyond the implemented backend-rendered operational PDF and spreadsheet exports.
- Conduct security, privacy, accessibility, and load testing.

### Phase 3: Controlled pilot

- Pilot with one academic program and a limited set of HTEs.
- Collect support tickets, workflow completion times, and data-correction rates.
- Fix blocking issues before campus-wide rollout.

### Phase 4: Campus rollout and optimization

- Onboard all approved colleges, programs, staff, supervisors, and active students.
- Finalize any University-mandated signed report templates and institutional recovery options.
- Review success metrics after the first full internship cycle.

## 16. Risks and Mitigations

| Risk | Impact | Mitigation |
|---|---|---|
| Approval roles or sequence are ambiguous (Coordinator vs. Program Head vs. VPAA) | Conflicting decisions and invalid records | Approve a single state machine and responsible owner per transition before launch; enforce it in `ApprovalRecord`/`moas.program_status` logic |
| GPS is unavailable, inaccurate, or spoofed | Incorrect WFO attendance or travel checkpoint evidence | Treat geofence/GPS as one signal, preserve accuracy/context, define an exception review path, and avoid continuous tracking |
| Public/unauthenticated storage exposes sensitive files | Privacy breach | Move sensitive evidence (requirements, MOAs, checkpoint photos) to private storage and authorize each download endpoint |
| Student data is imported incorrectly via CSV | Failed or duplicate registration | Use preview, row-level validation, confirmation, idempotency, and an import error report (`CoordinatorStudentController@importCsv`/`confirmCsvImport`) |
| Concurrent attendance writes create conflicts | Duplicate or lost time records | Enforce the existing DB uniqueness on (`student_id`, `date`), use transactions, server timestamps, and production-grade database infrastructure |
| Inconsistent statuses across modules | Misleading dashboards and approvals | Centralize state/enum definitions and add transition/contract tests |
| Stakeholders rely on unverified reports | Operational or compliance error | Define report formulas, data owners, timestamps, and reconciliation checks before formal export endpoints ship |
| Low adoption by HTE supervisors | Missing evaluations and reviews | Use focused workflows, reminders, onboarding, and escalation dashboards |
| Uploaded files consume excessive storage | Cost and availability issues | Enforce limits, lifecycle policies, monitoring, and backup capacity planning |

## 17. Open Product Decisions

These decisions are required before the PRD can move from Draft to Approved:

1. **Requirement approval chain:** Does the Coordinator perform an initial review before Program Head review, or may either role issue the authoritative requirement decision? Which requirement types require VPAA final review via `approval_records`?
2. **Deployment approval chain:** Is VPAA approval sufficient after Coordinator submission, or is Program Head endorsement also required for every deployment?
3. **Completion authority:** Who changes `students.internship_status` to `completed`, and what exact conditions (hours, requirements, evaluations) must be satisfied?
4. **Attendance formula:** How are lunch breaks, grace periods, late arrivals, overtime, holidays, half-days, WFH, and field work credited toward `required_ojt_hours`?
5. **Geofence exception:** What evidence and approver are required when location permission or GPS accuracy fails during a WFO clock action?
6. **Supervisor identity:** Are supervisors University-managed `users` accounts, HTE-managed accounts, or invited external users? Can one supervisor serve multiple HTEs/programs (schema currently allows one `supervisor_id` per student, no HTE scoping on the user)?
7. **Report format:** Which official reports, signatures, templates, date ranges, and export formats (CSV/PDF/Excel) are mandatory for Section 13?
8. **Notification channels:** Are in-app notifications (`system_notifications`) sufficient for launch, or are verified email/SMS notices required?
9. **Retention:** How long must profiles, attendance, location evidence, uploaded documents, evaluations, activity logs, and backups be retained?
10. **Production scale:** What is the peak concurrent-user target during attendance windows, and what maximum upload volume is expected?
11. **Academic periods:** Must all operational records be explicitly separated by academic year and semester for repeat internships (`students.internship_semester`/`internship_year` exist but cross-period reporting rules are undefined)?
12. **Audit access:** Which role, if any, may search institution-wide `activity_logs` beyond a user's own history, and which actions require an immutable audit record?

## 18. Current Implementation Alignment

This PRD was derived from direct inspection of the repository on the document date, including all API routes, Eloquent models, migrations, controllers, and React pages/components. The codebase already contains substantial, working implementation for all six roles: authentication, official-enrollment registration, program scoping, attendance and smart clock, requirement uploads, HTE/MOA operations, travel with checkpoints, supervisor tasks/evaluations, multi-stage approvals, announcements, notifications, dashboards, settings, and per-user activity history.

As of version 2.1, travel has been revised from a Coordinator-operated checkpoint flow to a Student-controlled evidence flow with explicit session states, GPS accuracy, protected final-arrival evidence, Coordinator report generation, versioned Program Head/VPAA validation, notifications, and activity-log coverage. Dashboard active-travel counts treat `active`, `traveling`, and `arrived` sessions as in progress. The Student attendance workspace is workday-aware and reports only the configured remaining workdays for the current week.

The Admin user-list response resolves student college/program details through the linked student profile while preserving direct staff assignments. Administrator user records and Coordinator OJT enrollment, student, attendance, and journal operational lists expose validated server-side page sizing with UI selectors and pagination metadata. The sign-in screen also preserves credential-field contrast under dark theme and browser autofill, and browser branding uses the MarSU logo.

Areas that require explicit validation or product decisions before describing the product as production-ready:

- The Coordinator and Program Head can both act on the same `internship_requirements` record, while Program Head approval is also expected to create the VPAA endorsement via `approval_records`. The intended sequence and state ownership must be resolved (Open Decision 1).
- Role-specific report screens now implement the attached three-by-three hierarchy, authenticated full-dataset preview routes, validated role-appropriate filters, dynamic aggregation, preview refresh/print/change-filter controls, and confirmed UTF-8 CSV/OpenXML XLSX/rendered PDF downloads. Coordinator deployment submissions now require Program Head forward/return review before VPAA final validation, with remarks and notification/history preservation. Digitally signed institutional forms remain an open template/signature decision rather than a data/reporting gap.
- Several `system_settings` describe desired behavior (e.g., default OJT hours, attendance windows) but each setting must be verified as enforced by the operational code, not merely editable in the Admin UI.
- Student travel arrival photos are stored on the private local disk and served through authorization-checked endpoints. Requirements, MOAs, avatars, any legacy checkpoint photos, and remaining uploaded evidence still require a storage and privacy review before production.
- Existing automated tests (`tests/Feature`) cover authentication, enrollment, program scope, attendance, configured-workday calculations, Coordinator operations and detailed report payloads, Program Head report date/program scoping, VPAA report filters, table page sizing, the full travel-report validation chain and rejected transitions, core travel-report schema, profiles, activity logs, and VPAA approval cases, but the complete release criteria in Section 14 still require end-to-end validation.
- The supervisor-to-HTE relationship is implicit (via `students.supervisor_id` and `students.hte_id` independently); there is no explicit constraint that a supervisor's assigned students all belong to one HTE (Open Decision 6).

## 19. Approval Record

| Stakeholder | Name | Decision | Date |
|---|---|---|---|
| Product owner / Internship Office |  |  |  |
| VPAA |  |  |  |
| MIS / IT Administrator |  |  |  |

| Program Head representative |  |  |  |
| VPAA representative |  |  |  |
| Data Protection Officer |  |  |  |
| IT / System Administrator |  |  |  |
