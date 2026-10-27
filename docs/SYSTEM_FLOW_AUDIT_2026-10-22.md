# AIMS system flow audit

Date: October 22, 2026. Scope: working copy, including existing uncommitted changes.

## Assessment

The main approval chains are implemented and covered by automated tests, but the system is not yet ready to be treated as a fully consistent source for internship analysis. Resolve the evaluation-history, supervisor-access, and period-scoping findings first. Several dashboard metrics also rely on verification actions that the current application does not expose.

This was a source-code and automated API review, not a manual browser walkthrough. No application business logic or live database records were deliberately changed for this review. Backend tests use SQLite in memory. Actual MySQL behavior, mobile GPS/camera permissions, visual interactions, scheduler operation, and real file delivery still require environment-level acceptance testing.

## Observed role flows

| Role | Main flow and handoff | Review result |
| --- | --- | --- |
| Admin | Create academic periods, colleges/programs/sections and accounts; assign staff academic scope and supervisor login; deactivate accounts while preserving history | Existing CRUD, assignment, account-security and preservation tests pass. |
| Program Head, setup | Publish required hours/start date for the academic period; assign Coordinators to sections | Required setup precedes Coordinator deployment. Tests cover setup and section access. |
| Coordinator, enrollment | Add/import official enrollment into assigned sections; student then self-registers | Registration eligibility comes from official enrollment. Current implementation approves registration immediately; the older PRD description differs. |
| Student | Register, log in, upload requirements, inspect review feedback, record attendance/journals, participate in scheduled travel | Core API tests pass. Current dashboard mixes historical requirement folders; see F3. |
| Coordinator, documents | Review student uploads; manage HTEs, schedules, placements and MOAs; submit deployment and travel reports | Section/program access exists. Journal acknowledgment is separate from Supervisor attendance review. Verification metrics have an incomplete action path; see F4. |
| Program Head, review | Review Coordinator-approved requirements; endorse complete student folders and MOAs to Dean; forward deployment and travel reports to Dean | Folder endorsement checks completeness. Endorsed folders are locked against student replacement and staff review changes. |
| HTE Supervisor | Complete onboarding; view assigned interns; create tasks; review attendance; save evaluations | Assignment checks exist on management endpoints, but dashboard access and evaluation history have confirmed defects; see F1/F2. |
| Dean | College-scoped final decision for folders, MOAs, deployment and travel reports; monitor college live locations | Existing approval tests pass, including rejection of unauthorized roles and other colleges. Deployment notification has a wrong destination; see F5. |
| VPAA | University monitoring/reports; review acknowledgment of Dean-approved student folders and MOAs; announcements | VPAA does not own final Dean decisions. Final-decision handlers reject VPAA. Travel is view-only in the VPAA UI. |

## Main approval sequences

1. **Student documents:** Student PDF upload → Coordinator approval → Program Head approval of each requirement → complete folder endorsement → Dean approval/return → VPAA acknowledgment. A Dean return allows Program Head re-endorsement after corrections. Uploads/reviews remain locked while final approval or acknowledgment is in progress, and after completion.
2. **MOA:** Coordinator upload → Program Head endorsement/return → Dean final approval/return → VPAA acknowledgment. Returned MOAs can be edited by the Coordinator and resubmitted to Program Head.
3. **Deployment:** Program Head publishes period settings → Coordinator saves placement/schedule → Program Head forwards/returns → Dean approves/rejects → approved deployment becomes active once its start date is due and a current placement exists. Activation is also scheduled daily at 00:05; confirm that the deployment environment actually runs Laravel's scheduler.
4. **Travel:** Coordinator schedules → Student captures departure GPS → checkpoints → arrival → arrival photo → completed session → Coordinator generates/submits report → Program Head validates/forwards → Dean final validation/return. A return restores an editable draft and increments the version. VPAA views the report.
5. **Attendance:** Student records session slots/GPS and journal → Supervisor approves/flags attendance; Coordinator acknowledges journal review. These two actions do not set the separate attendance-verification field.

## Findings, in priority order

### F1 — High: new evaluation can overwrite an older internship's evaluation

Source: [SupervisorManagementController.php:145](../aims-backend/app/Http/Controllers/Api/SupervisorManagementController.php#L145), [Evaluation.php:46](../aims-backend/app/Models/Evaluation.php#L46).

`saveEvaluation()` matches an existing row using student, supervisor and evaluation type, without `student_internship_id`. If the same student has a subsequent internship under the same Supervisor, saving its final/midterm evaluation updates the previous row. The model retains an already populated internship reference.

**API reproduction confirmed:** submit final evaluation with work-quality 2; create a subsequent current internship and assign the same Supervisor; submit work-quality 5. There is still only one evaluation, the old row now contains 5, and the new internship has zero evaluation rows.

**Impact:** loss of historical ratings and incorrect per-period completion/evaluation reports.

**Recommended correction:** use the current internship ID in lookup, persistence, queries and database uniqueness; explicitly define when submitted/finalized evaluations may be revised. Preserve old evaluations and review attribution.

### F2 — High: former Supervisor receives current student information

Source: [SupervisorDashboardController.php:16](../aims-backend/app/Http/Controllers/Api/SupervisorDashboardController.php#L16), [SupervisorDashboardController.php:136](../aims-backend/app/Http/Controllers/Api/SupervisorDashboardController.php#L136).

The dashboard merges all students from the Supervisor's historical evaluations into the list of currently assigned students. It then loads each student's current HTE, program/status and attendance history. Retaining historical evaluations therefore also grants access to newer placement information.

**API reproduction confirmed:** a Supervisor with an evaluation but no current placement assignment receives `overview.assigned = 1` and the student's current HTE in `/api/supervisor/dashboard`. The current-assignment query independently returns no matching student.

**Impact:** access extends beyond the current assignment and the assigned-intern count is misleading.

**Recommended correction:** current dashboard/attendance data must use authorized internship or placement IDs. Historical evaluation access should return only the historical internship data relevant to that Supervisor.

### F3 — Medium: current dashboard mixes old and current requirement folders

Source: [StudentDashboardController.php:86](../aims-backend/app/Http/Controllers/Api/StudentDashboardController.php#L86), [StudentRequirementController.php:19](../aims-backend/app/Http/Controllers/Api/StudentRequirementController.php#L19).

The requirements screen filters to the current internship; the dashboard filters only by student and active definitions. Active definitions can be shared by requirements from multiple internships.

**API reproduction confirmed:** one student with an old and a new internship, each with ten requirements, receives ten requirements from `/api/student/requirements` but `requirements_progress.total = 20` from `/api/student/dashboard`.

**Impact:** misleading pending/completion percentages. Current dashboard attendance also uses the student-wide `Attendance::forStudent()` scope; audit all current-period metrics alongside this fix. Automatic OJT completion uses a narrower internship query, so different cards can use different populations.

**Recommended correction:** explicitly scope current dashboard data to the current internship. Offer history through an explicit period selector and keep report filters consistent.

### F4 — Medium: verification counters have no normal completion action

Sources: [routes/api.php](../aims-backend/routes/api.php), [CoordinatorAttendanceController.php:182](../aims-backend/app/Http/Controllers/Api/CoordinatorAttendanceController.php#L182), [SupervisorManagementController.php:103](../aims-backend/app/Http/Controllers/Api/SupervisorManagementController.php#L103), [UniversityDashboardController.php:197](../aims-backend/app/Http/Controllers/Api/UniversityDashboardController.php#L197), [CoordinatorReportController.php:293](../aims-backend/app/Http/Controllers/Api/CoordinatorReportController.php#L293).

**Confirmed by source inspection:** dashboards count unverified attendance and travel checkpoints as pending, and travel reports calculate compliance from `is_verified`. Current routes expose journal acknowledgment and Supervisor attendance review, but no attendance-verification or checkpoint-verification action. Student checkpoint creation does not mark checkpoints verified; report validation does not update checkpoint verification either.

**Impact:** newly recorded activity can remain pending/unverified in analytics even after the available review flow finishes. A completed travel with unverified checkpoints reports zero checkpoint compliance.

**Recommended correction:** decide whether separate verification is still required. Implement its authorized action if it is; otherwise derive metrics from the current review/validation workflow and revise labels and pending queues. Do not silently treat journal acknowledgment as attendance approval.

### F5 — Medium: Dean deployment notification sends Program Head to Coordinator page

Source: [VPAAApprovalController.php:157](../aims-backend/app/Http/Controllers/Api/VPAAApprovalController.php#L157), [ProgramHeadDeploymentController.php](../aims-backend/app/Http/Controllers/Api/ProgramHeadDeploymentController.php), [App.jsx](../aims-frontend/src/App.jsx).

**Confirmed by source inspection:** a deployment awaiting Dean decision was submitted by the Program Head. The decision notification goes to that submitter but uses `/coordinator/htes`. Frontend role guards prevent a Program Head from opening that destination. This handler does not additionally notify the original Coordinator or Student.

**Recommended correction:** link the Program Head to its deployment/reports workspace; notify the responsible Coordinator with its own destination. Define Student notification behavior for approval/return.

### F6 — Documentation/test drift must be resolved before formal analysis

Sources: [PRD.md:114](../PRD.md#L114), [PRD.md:163](../PRD.md#L163), [PRD.md:238](../PRD.md#L238), [AuthController.php:194](../aims-backend/app/Http/Controllers/Api/AuthController.php#L194), [CoordinatorHTEManagementTest.php:516](../aims-backend/tests/Feature/CoordinatorHTEManagementTest.php#L516).

The PRD says exactly six roles while the application implements seven, including Dean. Older sections still make VPAA the final approver, although the newer superseding Dean section correctly describes current ownership. AUTH-06 requires pending registration and Coordinator approval, but registration currently creates an approved record and permits immediate login after official-list validation.

Two backend tests fail on MOA response-message wording: `Reviewed or approved MOAs cannot be deleted.` versus `MOA under review cannot be deleted.`, and `Pending MOA deleted.` versus `MOA deleted.` Their HTTP-status assertions pass, but later assertions in those failing test methods are not reached.

**Recommended correction:** choose the authoritative registration policy, align the PRD/flowcharts and test expectations with it, and update superseded approval descriptions. Re-run both failing tests after aligning wording so their remaining assertions execute.

## Verification results

| Check | Result |
| --- | --- |
| `php artisan test --compact` in backend | 247 passed, 2 failed; 3,021 assertions reported; failures described in F6 |
| `npm run build` in frontend | Passed |
| `npm run lint` in frontend | No errors; 2 hook-dependency warnings in OjtSetup and ScopedLiveMap |
| Node tests for travelPoints and studentRequirementGroups | 4 passed |
| Temporary API probes for F1/F2/F3 | Each reproduced the documented defect using isolated test records |

Existing backend coverage includes Admin academic/user/settings operations, official-list registration, staff onboarding/recovery, program and Coordinator section access, document-folder approval/return/locks, MOA review, deployment, attendance, travel-report versioning, report exports/templates, notifications/activity and preservation tests. Passing coverage is evidence for those scenarios, not proof that every UI path or cross-period scenario is correct.

## Recommended next work

1. Fix F1 and F2 first; add regression cases for repeat internships and Supervisor reassignment.
2. Fix F3 and audit current-period versus historical data across all role dashboards/reports.
3. Resolve F4's verification policy, then F5's notification handoff.
4. Align documentation/test wording and obtain a clean backend run.
5. Perform a browser acceptance run with one student progressing through all seven roles, including return/resubmit paths; repeat with another college and a subsequent internship. Validate real GPS/camera behavior, private files, exports, and scheduled activation in the target environment.
