# Product Requirements Document: Academic Internship Monitoring System (AIMS)

| Field | Value |
|---|---|
| Product | Academic Internship Monitoring System (AIMS) |
| Institution | Marinduque State University - Santa Cruz Campus |
| Document status | Draft for stakeholder review |
| Version | 2.0 |
| Date | August 10, 2026 |
| Basis | Direct inspection of the AIMS Laravel backend (routes, models, migrations, controllers) and React frontend (pages, routes, services, components) |
| Stack | Laravel 11 + Sanctum API backend; React (Vite) + React Router + Tailwind + Recharts + Leaflet frontend; MySQL/MariaDB-compatible schema |
| Product owner | To be assigned by the University |

## 1. Executive Summary

AIMS is a role-based web application that manages the complete academic internship (On-the-Job Training / OJT) lifecycle at Marinduque State University - Santa Cruz Campus. It is implemented as a Laravel + Sanctum REST API (`aims-backend`) consumed by a React single-page application (`aims-frontend`), and already covers six distinct roles: **Student**, **Internship Coordinator**, **Program Head**, **HTE Supervisor**, **VPAA**, and **Administrator**.

The system is a single source of truth for: official OJT enrollment and student self-registration, host training establishment (HTE) records and geofencing, MOA agreements, student-to-HTE deployment, daily attendance via a "smart clock," documentary requirement submission and review, supervisor tasks and evaluations, GPS-tracked travel sessions with checkpoints, multi-stage approvals, announcements, in-app notifications, and per-user activity/audit history.

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
- Coordinator operations: enrollment import, student CRUD and registration approval, HTE/MOA/holiday management, requirement checklist definition, attendance and journal review, coordinator-led travel sessions, dashboard.
- Program Head review: program-scoped dashboard, student monitoring, requirement/MOA document review and endorsement, travel checkpoint verification.
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
- `users.is_active` gates authentication; deactivating a user must not delete their historical attendance, requirements, approvals, evaluations, or activity records.
- Staff accounts (coordinator, program_head, supervisor, admin, vpaa) receive an auto-generated, unique `staff_id`; students authenticate with their `student_id`/School ID.

## 7. Core Internship Lifecycle

1. A Coordinator adds the official OJT enrollment list (`ojt_enrollments`), individually or via CSV import with preview/confirmation.
2. An eligible student verifies their School ID against the enrollment list and self-registers; academic details are copied from the enrollment record, not typed freely.
3. The Coordinator approves or rejects the registration (`students.registration_status`); only `approved` students can sign in and use the student workspace.
4. The Coordinator deploys the student to an HTE and supervisor, and sets the internship schedule, required hours, workdays, and past-entry permission.
5. The Coordinator uploads/maintains the HTE's MOA; the Program Head endorses it and the VPAA gives final approval before it is considered valid.
6. The student records daily attendance through the smart clock (AM/PM, full-day/half-day, WFO/WFH/field) and logs AM/PM journal activities, and uploads the program's required documents.
7. Coordinators review attendance/journals; the assigned HTE Supervisor separately reviews attendance and records evaluations and tasks; Program Head and VPAA review/approve documents and deployments within their authority.
8. When applicable, the Coordinator or student initiates GPS-tracked travel sessions with ordered checkpoints (departure/intermediate/arrival), and Program Head/VPAA verify checkpoint evidence.
9. Dashboards and reports (per role) consolidate progress until the internship becomes `completed`, `dropped`, or otherwise closed.

## 8. Functional Requirements

Priority definitions: **P0** is required for the initial production release (already implemented in the codebase and must be verified/hardened), **P1** is important but may follow after the core launch, and **P2** is an enhancement.

### 8.1 Public portal, authentication, and accounts

Backend: `AuthController`, `CollegeController`, `PublicPortalController`. Frontend: `pages/public/LandingPage`, `pages/auth/{LoginPage, RegisterPage, ForgotPasswordPage}`.

| ID | Priority | Requirement |
|---|---|---|
| AUTH-01 | P0 | The system shall provide a public landing page (`GET /portal`) with institutional information and role-based portal entry points, and public read-only access to colleges, programs, and HTEs. |
| AUTH-02 | P0 | The system shall authenticate via `POST /login` using email, staff ID, or student School ID plus password, and issue a revocable Sanctum token. |
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
| STU-02 | P0 | `GET /student/attendance` and `/student/attendance/workspace` shall provide a monthly calendar and per-date detail used by the attendance calendar UI. |
| STU-03 | P0 | Students shall create `present`, `absent`, or `holiday` entries (`POST /student/attendance/entry`) subject to `allow_past_attendance` and configured date windows. |
| STU-04 | P0 | Present entries shall support `work_mode` of `wfo`, `wfh`, or `field`, and `session_type` of `full_day`, `am_half`, or `pm_half`. |
| STU-05 | P0 | Smart clock (`GET/POST /student/attendance/quick-clock`, `POST /student/attendance/clock-in`, `/clock-out`) shall use server timestamps and enforce a valid AM/PM time-in/time-out sequence per session. |
| STU-06 | P0 | WFO smart clock actions shall capture latitude/longitude and validate against the assigned HTE's geofence (`geofence_enabled`, `geofence_radius`) when configured. |
| STU-07 | P0 | Students shall record AM/PM journal activities (`am_activity`, `pm_activity`) alongside attendance for later Coordinator journal review. |
| STU-08 | P0 | `GET /student/requirements` shall list the student's program requirement checklist with status (`pending`/`approved`/`rejected`) and reviewer feedback. |
| STU-09 | P0 | Students shall upload (`POST /student/requirements/{id}/upload`) and download (`GET /student/requirements/{id}/download`) requirement files; re-uploading shall return the item to `pending`. |
| STU-10 | P0 | `GET /student/travel` shall list assigned travel sessions; students shall start (`POST /student/travel/{travel}/start`), submit checkpoint evidence (`POST /student/travel/checkpoints/{checkpoint}`), and end (`POST /student/travel/{travel}/end`) sessions with location and photo evidence where required. |
| STU-11 | P0 | Students shall view announcements targeted to `all` or `students`, and receive/mark-read in-app notifications that deep-link to the relevant screen. |
| STU-12 | P0 | Students shall maintain personal, guardian, and contact profile fields and change their password from `Profile.jsx`. |

### 8.3 Coordinator operations

Backend: `CoordinatorDashboardController`, `CoordinatorStudentController`, `CoordinatorAttendanceController`, `CoordinatorHTEController`, `CoordinatorRequirementController`, `CoordinatorTravelController`. Frontend: `pages/coordinator/{Dashboard, StudentManagement, AttendanceManagement, HTEManagement, TravelMonitoring, Reports, Announcements, Profile}`.

| ID | Priority | Requirement |
|---|---|---|
| COR-01 | P0 | Coordinators shall manage `ojt_enrollments` individually and via CSV (`POST /coordinator/students/import` preview, `/import/confirm` commit). |
| COR-02 | P0 | Coordinators shall list/search/filter (`GET /coordinator/students`), view, create, update, and delete student records (`CoordinatorStudentController` apiResource) within their college/program. |
| COR-03 | P0 | Coordinators shall approve (`POST /coordinator/students/{student}/approve`) or reject (`.../reject`, reason required) registrations, and deactivate (`PUT .../deactivate`) student accounts. |
| COR-04 | P0 | Coordinators shall define, edit, reorder (`PUT /coordinator/requirements/order`), and deactivate the program's official requirement checklist (`program_requirements`) without deleting historical `internship_requirements` submissions. |
| COR-05 | P0 | Coordinators shall review submitted requirements (`POST /coordinator/students/{student}/requirements/{requirement}/review`), download evidence, and approve/reject with feedback. |
| COR-06 | P0 | Coordinators shall list/inspect (`GET /coordinator/attendance`, `/{attendance}`), edit (`PUT`), delete (`DELETE`), verify (`PUT .../verify`), and review journals (`POST .../journal-review`) for attendance within their program. |
| COR-07 | P0 | Coordinators shall create/update/delete HTEs (`CoordinatorHTEController`), including geofence center/radius/enabled flag and default AM/PM schedule and workdays. |
| COR-08 | P0 | Coordinators shall deploy a student to an HTE and supervisor (`PUT /coordinator/htes/deployments/{student}`) with internship dates, required hours, schedule, and workdays. |
| COR-09 | P0 | Coordinators shall manage holidays (`POST/PUT/DELETE /coordinator/htes/holidays*`) scoped to college/program, including recurring holidays. |
| COR-10 | P0 | Coordinators shall upload and maintain MOAs (`POST/PUT/DELETE /coordinator/htes/moas*`) with effective/expiration dates, and download the MOA file. |
| COR-11 | P0 | Coordinators shall create and run travel sessions (`CoordinatorTravelController`: `store`, `startTravel`, `departure`, `checkpoint`, `arrival`, `uploadArrivalPhoto`, `endTravel`, `cancel`) with travelers, companions, route notes, and at least two ordered checkpoints. |
| COR-12 | P0 | The Coordinator dashboard (`GET /coordinator/dashboard`) shall summarize total/active/completed/pending/for-approval student counts, attendance and requirement summaries, and recent activity. |
| COR-13 | P1 | Coordinators shall export filtered attendance, journal, student, HTE-deployment, and travel reports (`Reports.jsx` modules); backend export/print endpoints remain to be finalized. |

### 8.4 Program Head review and monitoring

Backend: `ProgramHeadDashboardController`, `ProgramHeadDocumentController`, `ProgramHeadTravelController`, `ProgramHeadStudentController`. Frontend: `pages/program-head/{Dashboard, DocumentReview, StudentMonitoring, TravelMonitoringManagement, Reports, Announcements}`.

| ID | Priority | Requirement |
|---|---|---|
| PH-01 | P0 | `GET /program-head/dashboard` shall return a program-scoped overview (student counts, average progress/rating, attendance and requirement compliance, MOA and travel status, pending-approval counts). |
| PH-02 | P0 | `GET /program-head/students` shall list the program's students with progress, attendance %, and requirement completion for `StudentMonitoring.jsx`. |
| PH-03 | P0 | Program Heads shall review requirements (`POST /program-head/documents/requirements/{requirement}/review`) and download evidence (`GET .../download`). |
| PH-04 | P0 | Program Heads shall review/endorse MOAs (`POST /program-head/documents/moas/{moa}/review`, `moas.program_status`/`program_reviewed_by`) before VPAA final approval. |
| PH-05 | P0 | Program Heads shall monitor travel (`GET /program-head/travel`) and verify/unverify checkpoint evidence (`PUT /program-head/travel/checkpoints/{checkpoint}/verify`) and view checkpoint photos. |
| PH-06 | P1 | Program Heads shall export program-level compliance and progress reports. |

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
| VPAA-07 | P0 | The VPAA shall have a university-wide dashboard (`GET /vpaa/dashboard`, `UniversityInternshipDashboard` component), reports, and travel-monitoring/checkpoint-verification views (shared with Program Head). |

### 8.7 Administration

Backend: `AdminDashboardController`, `AdminUserController`, `AdminSystemController`. Frontend: `pages/admin/{Dashboard, UserManagement, AcademicManagement, SystemSettings, Reports}`.

| ID | Priority | Requirement |
|---|---|---|
| ADM-01 | P0 | Administrators shall search/filter/create/update/delete users (`AdminUserController` apiResource) across all six roles. |
| ADM-02 | P0 | Administrators shall change a user's role (`PUT /admin/users/{user}/role`) and toggle active status (`PUT .../status`); role changes to staff roles must carry a valid college/program assignment. |
| ADM-03 | P0 | Administrators shall reset a user's password directly (`POST /admin/users/{user}/reset-password`). |
| ADM-04 | P0 | Administrators shall manage colleges, programs, and academic sections (`AdminSystemController@academic` + `store/update/destroy*`) with referential-integrity protection (e.g., cannot delete a college with active programs/students). |
| ADM-05 | P0 | Administrators shall manage typed `system_settings` (group/key/value/type) via `GET/PUT /admin/settings`. |
| ADM-06 | P0 | `GET /admin/dashboard` shall summarize total users by role, active interns, pending registrations, completed internships, monthly attendance, and recent login activity. |
| ADM-07 | P1 | `GET /admin/reports` shall provide system-wide analytics/exports by academic year, semester, college, program, and status. |

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
- Three independently tracked review dimensions exist on the same record: `is_verified`/`verified_by` (Coordinator verification), `journal_status`/`journal_feedback`/`journal_reviewed_by` (Coordinator journal review), and `supervisor_review_status`/`supervisor_feedback`/`supervisor_reviewed_by` (Supervisor review). These must remain distinguishable in UI and reports.
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

### 9.5 Travel (`travel_logs`, `travel_checkpoints`, `travel_companions`)

- `travel_logs.status`: `active`, `completed`, `cancelled`.
- A travel session may be coordinator-led (`created_by`, nullable `student_id`, `travel_participants` pivot for multiple travelers) or student/self-initiated.
- `travel_checkpoints.point_type`: `departure`, `intermediate`, `arrival`; each session requires at least a departure and arrival checkpoint (at least two ordered checkpoints).
- Each checkpoint captures `captured_at` (server time), `latitude`/`longitude`, `photo_path`, and `notes`; verification (`is_verified`, `verified_by`, `verified_at`) is restricted to Program Head and VPAA.
- Arrival requires an `arrival_photo_path` and `arrived_at` timestamp before the session can be marked `completed`.

## 10. Key Data Entities

Derived directly from `app/Models` and `database/migrations`.

| Entity (table) | Purpose | Key relationships |
|---|---|---|
| User (`users`) | Authentication, `role` enum, `is_active`, `staff_id`, contact info, avatar | hasOne Student; belongsTo College |
| Student (`students`) | Academic, personal, guardian, HTE/supervisor assignment, schedule, registration/internship status, OJT progress | belongsTo User/College/Program/HTE/Supervisor(User); hasMany Attendance/InternshipRequirement/TravelLog/Evaluation/InternshipTask |
| OjtEnrollment (`ojt_enrollments`) | Official pre-registration eligibility list, unique `school_id` | belongsTo College/Program; belongsTo User (`added_by`); belongsTo Student (`student_record_id`) |
| College / Program / AcademicSection | Academic hierarchy and scoping, per-college `required_ojt_hours` | College hasMany Program; Program hasMany AcademicSection |
| HTE (`htes`) | Host organization contact data, address, geofence center/radius/enabled flag, default schedule | belongsTo College/Program; hasMany Student, MOA |
| MOA (`moas`) | HTE agreement file, validity window, Program Head endorsement, VPAA final approval | belongsTo HTE/College/Program; belongsTo User (`program_reviewed_by`, `approved_by`) |
| Attendance (`attendance`) | Daily time records, work mode/session, journal, three independent review states | belongsTo Student; belongsTo User (verifier/journal reviewer/supervisor reviewer) |
| ProgramRequirement (`program_requirements`) | Ordered, versionable definition of a program's required document | belongsTo Program; hasMany InternshipRequirement |
| InternshipRequirement (`internship_requirements`) | Student submission: file, status, feedback, reviewer | belongsTo Student, ProgramRequirement; belongsTo User (`reviewed_by`) |
| TravelLog / TravelCheckpoint / TravelCompanion | Planned/active travel session, ordered checkpoints, evidence, non-student companions | TravelLog hasMany TravelCheckpoint; belongsToMany Student via `travel_participants` |
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
- Navigation must be role-specific (`Sidebar` filters by `user.role`) and consistent across screens (`Header` + `Sidebar` layout, notification bell with unread badge).
- Every asynchronous action must show loading, success (toast), validation, and failure feedback.
- Destructive or irreversible actions (delete, deactivate, final approval/rejection) must require confirmation and clearly name the affected record.
- Statuses must use a consistent color palette across the app (green = approved/active/present, red = rejected/absent/cancelled, amber = pending/late, blue = completed/info, violet = holiday, slate = inactive).
- Empty states must explain why no data is present and, when applicable, provide the next action.
- Forms must preserve valid user input after validation errors; multi-step forms (registration, evaluations) must retain progress across steps.
- Dates and times must be displayed in the configured campus timezone (Asia/Manila).
- Tables with operational data must provide search, filters, pagination, and accessible labels.
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
- List and dashboard queries must be indexed (see the `add_high_volume_query_indexes` migration) and paginated to avoid unbounded dataset loading.
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

Dashboards already implemented (per role) and reports that must be finalized as formal exports:

- Registered, pending, approved, active, completed, dropped, and deactivated student counts (Admin, Coordinator, Program Head, VPAA dashboards).
- Attendance counts/rates by date, status, program, HTE, and verification state.
- Rendered hours vs. required hours and students at risk of falling behind.
- Requirement completion and decision turnaround by status and program.
- HTE deployment counts and students without assignments.
- MOA pending/approved/rejected/expired/expiring-soon counts.
- Pending and historical approval-record decisions and turnaround time.
- Active, completed, and cancelled travel sessions and unverified checkpoints.
- Supervisor task completion, attendance-review, and evaluation completion (midterm/final remaining).
- Announcement reach and read rates where institutionally permitted.

Personally identifiable detail in exports must be limited to roles with a documented operational need. The Coordinator, Program Head, and VPAA `Reports.jsx` pages currently render report modules from dashboard-style aggregates; dedicated CSV/PDF export endpoints are a P1 item to formalize (see COR-13, PH-06, ADM-07).

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

## 15. Delivery Plan

### Phase 1: Policy and workflow confirmation

- Confirm review authority, approval order, official attendance/OJT-hour computation, data retention, success targets, and production ownership.
- Resolve all open questions in Section 17.

### Phase 2: Core launch readiness

- Complete and stabilize all P0 workflows across all six roles.
- Align frontend and backend status transitions (registration, requirement, MOA, approval, travel, attendance review states).
- Add dedicated CSV/PDF export endpoints for Coordinator/Program Head/VPAA/Admin reports.
- Conduct security, privacy, accessibility, and load testing.

### Phase 3: Controlled pilot

- Pilot with one academic program and a limited set of HTEs.
- Collect support tickets, workflow completion times, and data-correction rates.
- Fix blocking issues before campus-wide rollout.

### Phase 4: Campus rollout and optimization

- Onboard all approved colleges, programs, staff, supervisors, and active students.
- Add P1 exports, reporting filters, and institutional recovery options.
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

Areas that require explicit validation or product decisions before describing the product as production-ready:

- The Coordinator and Program Head can both act on the same `internship_requirements` record, while Program Head approval is also expected to create the VPAA endorsement via `approval_records`. The intended sequence and state ownership must be resolved (Open Decision 1).
- Role-specific report screens (`Reports.jsx` per role) currently render from dashboard-style aggregates rather than dedicated export endpoints; formal CSV/PDF export endpoints remain a P1 item.
- Several `system_settings` describe desired behavior (e.g., default OJT hours, attendance windows) but each setting must be verified as enforced by the operational code, not merely editable in the Admin UI.
- Sensitive uploaded files (requirements, MOAs, checkpoint/arrival photos) require a storage and privacy review (public disk vs. private/authorized-download) before production.
- Existing automated tests (`tests/Feature`) cover authentication, enrollment, program scope, attendance, Coordinator operations, travel, profiles, activity logs, and VPAA approval cases, but the complete release criteria in Section 14 still require end-to-end validation.
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

