# Student deployment workflow

Implemented October 22, 2026. This supersedes the deployment approval description in the earlier audit reports and PRD. Student document folders, MOAs and travel reports retain their own workflows.

1. A student registers from the official enrollment list and may select a preferred HTE and department. Preferences do not create a placement.
2. The assigned Coordinator opens **HTE Management → Student Deployments**. The student's preference appears in the list and is preselected in the deployment editor when it is available. The Coordinator may confirm it or choose a different HTE/department, supervisor and working schedule.
3. Published OJT settings for that student's program and academic period supply the start date and required hours. The Coordinator selects **Approve & Send to Program Head**.
4. The Program Head opens **Document Review → Deployment Approval**, views the complete assignment and selects **Review, Approve & Forward to Dean**. This records Program Head approval.
5. The Dean opens **Deployment Review**, views the approved assignment and selects **Forward to VPAA**. The Dean has no deployment approve/reject decision.
6. VPAA opens **Student Deployments** to view Dean-forwarded assignments and the handoff history. VPAA has no deployment approval action.

**Student Monitoring remains view-only** in the Program Head workspace. Reports link to the deployment approval tab instead of containing deployment decision buttons.

## Status and OJT activation

- `for_program_head_review`: Coordinator confirmed the assignment; Program Head review is pending.
- `for_dean_forwarding`: Program Head approved the assignment; Dean forwarding is pending.
- `forwarded_to_vpaa`: Dean forwarded the assignment for VPAA viewing.
- `superseded`: an unforwarded submission was replaced or withdrawn by the Coordinator.

Internship status is separate from forwarding status. OJT becomes active after Program Head approval and when the published start date is due. The Dean's forwarding action does not change the approval decision. New/adopted workflow records enforce this gate for Smart Clock attendance. An already open time-in session may still finish its time-out; new time-ins remain blocked until access is restored.

## History and reassignment

Each submission saves an internship reference and a copy of the assignment, preferences and working schedule. Program Head approval and Dean forwarding have separate actors/timestamps. A reassignment creates a new submission for review; previous forwarded assignments remain visible as historical records with their original details. Saving an unchanged submission preserves its review status.

The additive migration `2026_10_22_000001_add_deployment_handoff_to_approval_records` adopts existing pending deployment requests into the appropriate review/forwarding stage. It does not fabricate a Dean forwarding event. Earlier completed approvals stay in the database and remain available in legacy report history.

## Verification

`StudentDeploymentHandoffTest` exercises real registration through all handoffs, published-setting overrides, reassignment, saved history, unchanged saves, cross-role/college/program rejection, duplicate/out-of-order actions, legacy pending adoption, inactive HTE rejection and attendance eligibility.
