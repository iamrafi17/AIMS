// Run against an isolated Laravel verification database, never a production account.
// EVALUATION_QA_DATA: JSON from the isolated fixture seeder; EVALUATION_PLAYWRIGHT_MODULE: optional module URL.
import assert from "node:assert/strict";
import { readFile, mkdir } from "node:fs/promises";
import path from "node:path";
const { chromium } = await import(
  process.env.EVALUATION_PLAYWRIGHT_MODULE || "playwright"
);
const fixture = JSON.parse(
  await readFile(process.env.EVALUATION_QA_DATA, "utf8"),
);
const base = process.env.EVALUATION_FRONTEND || "http://127.0.0.1:5174";
const backend = process.env.EVALUATION_BACKEND || "http://127.0.0.1:8001";
const output =
  process.env.EVALUATION_QA_OUTPUT ||
  path.resolve("../aims-backend/storage/app/private/evaluation-browser-qa");
await mkdir(output, { recursive: true });
const browser = await chromium.launch({ channel: "msedge", headless: true });
const pages = [];
const runtimeErrors = [];
async function pageFor(role, routePath, width = 1365) {
  const context = await browser.newContext({
    viewport: { width, height: 900 },
  });
  await context.addInitScript(
    (token) => localStorage.setItem("token", token),
    fixture.tokens[role],
  );
  const page = await context.newPage();
  page.setDefaultTimeout(15000);
  page.on("pageerror", (error) => runtimeErrors.push(error.message));
  await page.goto(base + routePath);
  pages.push(page);
  return page;
}
async function api(role, endpoint) {
  const response = await fetch(backend + "/api" + endpoint, {
    headers: {
      Authorization: `Bearer ${fixture.tokens[role]}`,
      Accept: "application/json",
    },
  });
  assert.equal(response.status, 200, `${endpoint}: HTTP ${response.status}`);
  return response.json();
}
async function noOverflow(page, name) {
  assert.equal(
    await page.evaluate(
      () => document.documentElement.scrollWidth > innerWidth + 1,
    ),
    false,
    `${name} has horizontal overflow`,
  );
}
try {
  const coordinator = await pageFor("coordinator", "/coordinator/evaluations");
  await coordinator
    .getByLabel("Form name", { exact: true })
    .fill("Browser QA BSIS rubric");
  await coordinator
    .getByLabel("Academic period", { exact: true })
    .selectOption(String(fixture.period_id));
  await coordinator
    .getByRole("button", { name: "Create draft", exact: true })
    .click();
  await coordinator
    .getByRole("button", { name: "Import CSV", exact: true })
    .click();
  const csv = path.resolve(
    "../aims-backend/tests/Fixtures/bsis-practicum-evaluation.csv",
  );
  await coordinator
    .getByLabel("CSV file (up to 2 MB)", { exact: true })
    .setInputFiles(csv);
  await coordinator
    .getByRole("button", { name: "Preview import", exact: true })
    .click();
  await coordinator
    .getByText("5 categories · 10 subgroups · 54 criteria", { exact: true })
    .waitFor();
  await coordinator.screenshot({ path: path.join(output, "csv-preview.png") });
  const listing = await api("coordinator", "/coordinator/evaluation-forms");
  const formId = listing.forms.data.find(
    (f) => f.name === "Browser QA BSIS rubric",
  ).id;
  assert.equal(
    (await api("coordinator", `/coordinator/evaluation-forms/${formId}`)).form
      .categories.length,
    0,
    "Preview must not mutate rubric",
  );
  await coordinator
    .getByRole("button", { name: "Cancel import", exact: true })
    .click();
  await coordinator
    .getByRole("button", { name: "Import CSV", exact: true })
    .click();
  await coordinator
    .getByLabel("CSV file (up to 2 MB)", { exact: true })
    .setInputFiles(csv);
  await coordinator
    .getByRole("button", { name: "Preview import", exact: true })
    .click();
  await coordinator
    .getByLabel("Apply to this draft", { exact: true })
    .selectOption("replace");
  await coordinator
    .getByRole("button", { name: "Confirm import", exact: true })
    .click();
  await coordinator
    .getByLabel("Better performance", { exact: true })
    .selectOption("lower");
  await coordinator
    .getByRole("button", { name: "Add rating", exact: true })
    .click();
  await coordinator
    .getByRole("button", { name: "Add rating", exact: true })
    .click();
  await coordinator.getByLabel("Number", { exact: true }).nth(0).fill("1");
  await coordinator
    .getByLabel("Meaning", { exact: true })
    .nth(0)
    .fill("QA excellent");
  await coordinator.getByLabel("Number", { exact: true }).nth(1).fill("5");
  await coordinator
    .getByLabel("Meaning", { exact: true })
    .nth(1)
    .fill("QA needs improvement");
  await coordinator
    .getByLabel("Allow explicit No Rating for unobserved criteria")
    .check();
  await coordinator
    .getByRole("button", { name: "Save and preview", exact: true })
    .click();
  await coordinator
    .getByRole("button", { name: "Publish version", exact: true })
    .waitFor();
  await coordinator.setViewportSize({ width: 390, height: 844 });
  await noOverflow(coordinator, "Mobile form preview");
  await coordinator.screenshot({
    path: path.join(output, "mobile-form-preview.png"),
  });
  coordinator.once("dialog", (dialog) => dialog.accept());
  await coordinator
    .getByRole("button", { name: "Publish version", exact: true })
    .click();
  await coordinator.getByText(/Version \d+ · published · read-only/).waitFor();
  console.log(
    "PASS Coordinator: actual CSV preview/cancel/confirm, ratings, preview, publish, mobile layout",
  );

  const supervisor = await pageFor(
    "supervisor",
    "/supervisor/evaluations",
    390,
  );
  await supervisor
    .getByRole("button", { name: "Evaluate whole semester", exact: true })
    .click();
  const firstRating = supervisor.locator('select[id^="rating-"]').first();
  await firstRating.waitFor();
  const numericOption = await firstRating
    .locator("option")
    .nth(1)
    .getAttribute("value");
  await firstRating.selectOption(numericOption);
  await supervisor
    .getByRole("button", { name: "Save draft", exact: true })
    .click();
  await supervisor.getByText("All changes saved", { exact: true }).waitFor();
  await supervisor
    .getByRole("button", { name: "Save and review", exact: true })
    .click();
  await supervisor
    .getByText("Resolve every criterion before submitting.", { exact: false })
    .waitFor();
  assert.equal(
    await supervisor
      .getByRole("button", { name: "Submit and lock", exact: true })
      .isDisabled(),
    true,
  );
  await supervisor
    .getByRole("button", { name: "Back to edit", exact: true })
    .click();
  const selects = await supervisor.locator('select[id^="rating-"]').all();
  assert.equal(selects.length, 54);
  for (let i = 1; i < selects.length; i++)
    await selects[i].selectOption("no_rating");
  await supervisor
    .getByLabel("Performance feedback", { exact: true })
    .fill("Browser QA submitted feedback");
  await noOverflow(supervisor, "Mobile Supervisor form");
  await supervisor.screenshot({
    path: path.join(output, "mobile-supervisor.png"),
  });
  await supervisor
    .getByRole("button", { name: "Save and review", exact: true })
    .click();
  await supervisor
    .getByRole("button", { name: "Submit and lock", exact: true })
    .waitFor();
  supervisor.once("dialog", (dialog) => dialog.accept());
  await supervisor
    .getByRole("button", { name: "Submit and lock", exact: true })
    .click();
  await supervisor.getByText(/Evaluator:.*Read-only/).waitFor();
  assert.equal(await supervisor.locator('select[id^="rating-"]').count(), 0);
  console.log(
    "PASS Supervisor: 54 criteria, partial draft, missing-item review, No Rating, submission lock, mobile layout",
  );

  for (const [role, route] of [
    ["coordinator", "/coordinator/evaluations"],
    ["program_head", "/program-head/evaluations"],
    ["dean", "/dean/evaluations"],
  ]) {
    const page = await pageFor(role, route);
    if (role === "coordinator")
      await page
        .getByRole("button", { name: "Evaluation Results", exact: true })
        .click();
    await page
      .getByRole("button", { name: "View submitted result", exact: true })
      .click();
    await page
      .getByText("Feedback: Browser QA submitted feedback", { exact: true })
      .waitFor();
    await noOverflow(page, `${role} results`);
    assert.equal(await page.locator('select[id^="rating-"]').count(), 0);
  }
  assert.deepEqual(runtimeErrors, []);
  console.log(
    "PASS Coordinator, Program Head, Dean: same submitted results, read-only, no browser runtime errors",
  );
} catch (error) {
  for (const [index, page] of pages.entries()) {
    await page.screenshot({ path: path.join(output, `failure-${index}.png`) });
    console.log(
      page.url(),
      (await page.locator("body").innerText()).slice(-6000),
    );
  }
  throw error;
} finally {
  await browser.close();
}
