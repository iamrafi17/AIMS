# Student deployment flow review

Reviewed October 22, 2026, against the current working copy. Source/API review only; no business logic changes or manual browser walkthrough.

## Assessment

The main approval sequence works, but the deployment lifecycle is not fully enforced. Most urgently, a student can begin Smart Clock attendance while the deployment is still pending and before the OJT start date.

## Current sequence

1. Admin configures academic structure/accounts. Program Head assigns the Coordinator to the student's section.
2. Student registers from the official enrollment list. The registration HTE choice is a preference, separate from the final placement.
3. Program Head publishes the academic period's OJT hours and start date.
4. Coordinator selects the final HTE, optional department/Supervisor, and batch or custom schedule. Published hours/start date override client input. Saving sets internship and deployment status to pending.
5. Program Head forwards the deployment to Dean or returns it with remarks.
6. The college Dean approves or rejects it. VPAA cannot make the final decision.
7. An approved deployment remains pending if its start date is in the future. It becomes active when the date is due and a current placement exists. Activation runs on several reads and is also scheduled daily at 00:05.
8. Returned/rejected placements can be edited and submitted again through Coordinator Student Deployments.

## Findings

### D1 — High: attendance is possible before deployment approval/start

Sources: [SmartClockAccess.php:12](../aims-backend/app/Support/SmartClockAccess.php#L12), [StudentAttendanceController.php:362](../aims-backend/app/Http/Controllers/Api/StudentAttendanceController.php#L362), [StudentAttendanceController.php:794](../aims-backend/app/Http/Controllers/Api/StudentAttendanceController.php#L794).

Smart Clock access checks the program switch and an approved consent upload. Its workday check validates the daily schedule, not the deployment approval, internship status or OJT date window.

**API reproduction confirmed:** at October 22, a student with `deployment_status=pending`, `status=pending` and November 1 OJT start date successfully records a WFH time-in through `/api/student/attendance/quick-clock`. An attendance row is created while both statuses remain pending.

**Correction needed:** enforce deployment eligibility on the server before new attendance sessions, including approval, date, current placement and permissible internship status. Define an explicit policy for finishing an already open session after a placement change. Apply equivalent authorization to other attendance write paths; UI disabling is insufficient.

### D2 — Medium: inactive HTE can be assigned through the API

Source: [CoordinatorHTEController.php:510](../aims-backend/app/Http/Controllers/Api/CoordinatorHTEController.php#L510).

The UI hides inactive HTEs, but deployment validation accepts any existing HTE whose program link is active. It does not require the HTE itself to be active.

**API reproduction confirmed:** set `htes.is_active=false`, retain its active program link, submit a deployment; the endpoint returns success and assigns that HTE.

**Correction needed:** enforce HTE activity in backend validation and recheck eligibility at approval/activation if policy requires it.

### D3 — Medium: saving unchanged placement resets an active approved internship

Source: [CoordinatorHTEController.php:625](../aims-backend/app/Http/Controllers/Api/CoordinatorHTEController.php#L625).

Every save with an HTE unconditionally writes both statuses to pending and submits a new approval request. There is no comparison with the saved deployment.

**API reproduction confirmed:** submit a placement, mark it approved/active, then save the identical placement/schedule payload. The endpoint returns `schedule_status=pending` and `internship_status=pending`.

**Correction needed:** unchanged saves should preserve status. Material changes should use an explicit revision/resubmission flow with review history and clear handling of an active attendance session.

### D4 — Medium: handoff wording and decision notification disagree with ownership

Sources: [Reports.jsx:131](../aims-frontend/src/pages/program-head/Reports.jsx#L131), [VPAAApprovalController.php:157](../aims-backend/app/Http/Controllers/Api/VPAAApprovalController.php#L157).

Program Head's confirmation says the deployment is forwarded to VPAA, although the backend forwards it to Dean. After Dean's decision, the notification goes to the Program Head but links to `/coordinator/htes`, which that role cannot open. The decision handler does not additionally notify the responsible Coordinator/Student.

**Correction needed:** display Dean consistently and use recipient-specific notification destinations.

## Eligibility policy that needs to be explicit

Deployment currently does not require a valid approved MOA, complete/Dean-approved student folder, department selection, or a Supervisor with a login account. Department and Supervisor fields are nullable. Existing approval-chain tests complete successfully without a MOA or approved folder.

These are policy gaps if the University's deployment prerequisites require them; do not label those prerequisites as enforced in a flowchart. A two-stage model may be appropriate: allow a draft/proposed placement, but enforce the agreed readiness checklist before final approval/activation.

## Verification

- Existing deployment-focused tests: **8 passed, 104 assertions** (`php artisan test --compact --filter 'deployment|deployments'`).
- Three temporary isolated API probes: **3 passed, 9 assertions**. Passing here means each probe successfully reproduced D1, D2 or D3; it does not mean the behavior is correct.
- D4 verified from frontend and backend source.
- No production workflow fixes applied. Real-browser operation and target-database/scheduler behavior remain unverified.
