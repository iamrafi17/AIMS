# Whole-semester student evaluations

This module adds program/academic-period rubrics to the existing React/Laravel application. No official scale or academic grade conversion is seeded. Coordinators select the official rubric and calculation before publishing. The default calculation is ratings only.

## Actors and navigation

- Coordinator: `/coordinator/evaluations`, with Evaluation Forms and Evaluation Results tabs. Form authorship uses the account's existing program assignment. Forms are shared across that program; result visibility follows assigned academic sections, including assigned historical sections.
- HTE Supervisor: `/supervisor/evaluations`. The list is internship-based and has one whole-semester evaluation per internship. Student program changes and the currently active period never choose a historical rubric.
- Program Head: `/program-head/evaluations`, read-only results in the assigned program.
- Dean: `/dean/evaluations`, read-only results in the assigned college.
- Student, Admin, and VPAA receive no new result-view or grade-edit permission.

## Form and evaluation flow

1. Create a named program/period draft. Add categories, optional subgroups, criteria and optional text-field labels manually, or import a CSV.
2. Configure distinct numeric choices (up to three decimal places), their official descriptions, better-is-lower/higher, and explicit No Rating policy.
3. Save and preview the complete rubric, then publish. Publication locks the version and archives any previous published version in the same program/period.
4. The Supervisor starts an assigned internship evaluation. The server pins the published form. Repeat starts return that same evaluation. A new form version never switches an existing draft/result or creates an additional cycle.
5. Save partial answers, feedback and recommendations. Review saves the current changes and retrieves authoritative missing-item validation. Submit resolves every criterion and requires at least one numeric answer.
6. Submission records the evaluator and timestamp once. Grades become read-only. Authorized viewers see submitted answers and comments, never unsent draft answers.

**Delete draft** is available in the form list and builder, with confirmation. It removes only an unreferenced draft, including its categories, subgroups, criteria and rating options in one transaction. It requires the current draft revision and authorized program. Published, archived, previously published and referenced forms are protected. The builder can delete an incomplete draft without first saving unsaved edits. No existing form is automatically deleted by adding this action.

Supervisor dashboard pending counts also use one whole-semester action. Legacy midterm/final records remain in read-only history. Their original scores are not converted into the new rubric. The legacy POST entry point now returns HTTP 409. The old GET remains available for compatibility.

## CSV mapping

CSV creates rubric questions, not student scores. Upload is a preview only. Confirmation explicitly selects Append or Replace; Cancel changes nothing. File limit is 2 MB, 10,000 records, 200 columns and 5,000 imported criteria. Use UTF-8 CSV.

- Report layout: choose category and item columns using letters such as A/B/AA, and optionally a start/end row. A numbered heading followed by lettered items becomes a subgroup. Unrecognized item rows produce preview errors instead of being silently dropped.
- Normalized layout: map category, optional subgroup, and criterion columns. Category context may carry down. An empty subgroup cell means a direct category criterion. Column headers are excluded.
- Other college layouts must be mapped and checked in preview. The importer is not fixed to BSIS coordinates and does not assume all programs share categories or ratings. Ambiguous layouts can be corrected to normalized CSV.
- Averages, overall average, final grade, student-name headers, student columns, document checklists and blank cells are not questions.
- Repeated criterion text in different subgroups is preserved. Programming-language/software blanks in the supplied report create optional text fields.

The supplied BSIS fixture imports **5 categories, 10 subgroups and 54 rated criteria** from columns A/B. Its report area is A5:B72 with average rows skipped. Numbers 1–50 in C:AZ are student columns, not rating values.

Preview tokens expire after 15 minutes and are bound to the author, form and draft revision. Stale, expired, reused and other-author confirmations are rejected. Imported questions remain editable until publication.

## Calculation and consistency

- `ratings_only`: criterion ratings and coverage, without an invented overall number.
- `rated_item_mean`: sum of numeric values divided by numeric response count.
- `category_mean`: unweighted mean of eligible category means. Select whether a category without numeric ratings is excluded or must receive a numeric answer.

No Rating has no numeric value and is excluded from both numerator and denominator. Blank means unanswered. Calculations use Brick Math exact rational/decimal arithmetic, then explicit half-up display rounding (0–3 decimal places). Category means are not rounded before calculating the overall mean. Direction changes interpretation, not arithmetic. New scales are excluded from the existing legacy 1–5 dashboard average.

Draft saving locks the evaluation and checks `row_version`. HTTP 409 identifies stale revisions or edits after submission; 422 identifies invalid ratings/incomplete submissions. Submit retries return the original submitted result. A previous supervisor's evaluation cannot be automatically taken over after an assignment change.

API merge semantics: omitted criteria and omitted comment fields retain their saved values. An explicit response with `rating_option_id: null` and `is_no_rating: false` clears that answer. Extra text accompanies a resolved criterion; the UI enables the text field after selecting a numeric rating or No Rating.

## Database and rollout

Migration: `2026_10_24_000001_add_whole_semester_evaluations.php`.

Six new tables: `evaluation_forms`, `evaluation_categories`, `evaluation_subgroups`, `evaluation_criteria`, `evaluation_rating_options`, `evaluation_responses`. The existing `evaluations` table gains form identity and draft revision, whole-semester type and nullable legacy score fields. Student/type uniqueness is removed; internship/type uniqueness remains. Composite FKs reject cross-form criteria/options; checks reject invalid answer/binding states. Student deletion is restricted to preserve evaluation history.

Before a targeted deployment migration:

```powershell
php artisan evaluations:preflight --backup
php artisan migrate --path=database/migrations/2026_10_24_000001_add_whole_semester_evaluations.php --force
```

Preflight reports version, existing type/status counts, unresolved internship references, duplicate/mismatched records, indexes and FKs. Backups are private under `storage/app/private/evaluation-backups`. Do not use `migrate:fresh` on application data. Legacy unresolved records are not assigned guessed internships. Their evaluator can read them through legacy history; scoped academic result access requires a resolved internship context.

Rollback refuses to drop rubric data once any form/evaluation exists. Preserve records and use a forward migration after use. Empty-module rollback retains safer nullable legacy columns, expanded type and retention indexes/FKs.

The local configured MariaDB schema was compared with a separately migrated backup copy. During initial verification the cached schema connection caused the feature migration to run on the configured local database; its schema was subsequently verified and migration registration reconciled. Existing application records were preserved. Browser fixtures, sample ratings/publication and test submissions were created only in the isolated verification database, never the application database.

## Verification

```powershell
# In aims-backend; phpunit.xml uses an in-memory SQLite database.
php artisan test --compact --filter=WholeSemesterEvaluationTest
php artisan test --compact

# In aims-frontend
npm.cmd run lint
npm.cmd run build
```

The workflow tests cover exact BSIS counts, manual hierarchy, configurable scale/precision, duplicate numeric values, draft privacy, missing answers, No Rating rules, merge/clear semantics, stale revisions, version pinning, cross-form rejection, program/section/college scopes, idempotent start/submit, historical internship separation, legacy reading, student deletion restrictions and exact rounding. The same feature suite is also run against a dedicated MariaDB 10.4.32 test database.

`aims-frontend/scripts/test-evaluations.mjs` runs headless browser checks against disposable role accounts on an isolated backend at port 8001. In a separate PowerShell session, select a newly created and migrated test database whose name matches `aims_evaluation_verify_YYYYMMDDHHMMSS` through `DB_DATABASE` and `DB_CONNECTION=mysql`. Run `php tests/Support/seed-evaluation-browser.php` from the backend to create private fixture JSON and disposable role tokens, then serve that same test database on port 8001. The seeder refuses other database names.

Start the frontend with `npm.cmd run dev -- --config scripts/evaluation-qa.vite.config.mjs` (port 5174), set `EVALUATION_QA_DATA` to `aims-backend/storage/app/private/evaluation-browser-fixtures.json`, and set `EVALUATION_PLAYWRIGHT_MODULE` if Playwright is supplied by an external runtime. Run `node scripts/test-evaluations.mjs` from the frontend. It verifies actual file upload/preview/cancel/confirm, publication, all 54 Supervisor inputs, review and submission, three viewer roles, runtime errors and mobile horizontal overflow. Do not point destructive test fixtures at the application database.

Verified locally: 299 backend tests (3,802 assertions), the isolated MariaDB feature suite, the headless role/mobile flow, and the production frontend build. Frontend lint has no new-module warnings; the existing `OjtSetup.jsx` dependency warning is outside this change.
