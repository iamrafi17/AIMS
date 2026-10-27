import assert from 'node:assert/strict';
const { chromium } = await import(process.env.EVALUATION_PLAYWRIGHT_MODULE || 'playwright');
const browser = await chromium.launch({ channel: 'msedge', headless: true });
const period = { id: 1, academic_year: '2026-2027', semester: '2nd Semester' };
const program = { id: 1, code: 'BSIS' };
const base = { academic_period_id: 1, academic_period: period, program, version: 1, row_version: 0, criteria_count: 0, categories: [], rating_options: [], computation_method: 'ratings_only', allow_no_rating: false };
let forms = [{ ...base, id: 1, name: 'Temporary draft', status: 'draft' }, { ...base, id: 2, name: 'Published rubric', status: 'published' }, { ...base, id: 3, name: 'Builder draft', status: 'draft' }];
let deletes = 0;
let patches = 0;
let rejectStale = true;
const errors = [];
try {
  const context = await browser.newContext({ viewport: { width: 390, height: 844 } });
  await context.addInitScript(() => localStorage.setItem('token', 'isolated-ui-fixture'));
  const page = await context.newPage();
  page.on('pageerror', (e) => errors.push(e.message));
  await page.route('**/api/**', async (route) => {
    const request = route.request();
    const url = new URL(request.url());
    const match = url.pathname.match(/^\/api\/coordinator\/evaluation-forms\/(\d+)$/);
    let json = { notifications: [], unread_count: 0 };
    let status = 200;
    if (url.pathname === '/api/user') json = { user: { id: 1, name: 'QA Coordinator', role: 'coordinator', is_active: true, requires_profile_setup: false, requires_security_setup: false, requires_password_change: false } };
    else if (url.pathname === '/api/coordinator/evaluation-forms') json = { program, periods: [period], forms: { data: forms, current_page: 1, last_page: 1 } };
    else if (match && request.method() === 'GET') json = { form: forms.find((f) => f.id === Number(match[1])) };
    else if (match && request.method() === 'DELETE') {
      deletes++;
      assert.deepEqual(request.postDataJSON(), { row_version: 0 });
      if (rejectStale) { status = 409; json = { message: 'This form changed in another tab. Reload before editing.' }; }
      else { forms = forms.filter((f) => f.id !== Number(match[1])); json = { message: 'Evaluation draft deleted.' }; }
    } else if (request.method() === 'PATCH') { patches++; status = 500; json = { message: 'Delete must not save an incomplete draft first.' }; }
    await route.fulfill({ status, json });
  });
  await page.goto(process.env.EVALUATION_FRONTEND || 'http://127.0.0.1:5174/coordinator/evaluations');
  const draft = page.locator('article').filter({ has: page.getByRole('heading', { name: 'Temporary draft', exact: true }) });
  const published = page.locator('article').filter({ has: page.getByRole('heading', { name: 'Published rubric', exact: true }) });
  await draft.getByRole('button', { name: 'Delete draft', exact: true }).waitFor();
  assert.equal(await published.getByRole('button', { name: 'Delete draft', exact: true }).count(), 0);
  page.once('dialog', (d) => d.dismiss());
  await draft.getByRole('button', { name: 'Delete draft', exact: true }).click();
  assert.equal(deletes, 0);
  page.once('dialog', (d) => d.accept());
  await draft.getByRole('button', { name: 'Delete draft', exact: true }).click();
  await page.getByRole('alert').filter({ hasText: 'This form changed' }).waitFor();
  assert.equal(await draft.count(), 1);
  rejectStale = false;
  page.once('dialog', (d) => d.accept());
  await draft.getByRole('button', { name: 'Delete draft', exact: true }).click();
  await draft.waitFor({ state: 'detached' });
  const builder = page.locator('article').filter({ has: page.getByRole('heading', { name: 'Builder draft', exact: true }) });
  await builder.getByRole('button', { name: 'Edit draft / Preview / Publish', exact: true }).click();
  await page.getByLabel('Form name', { exact: true }).fill('Unsaved draft name');
  page.once('dialog', (d) => d.accept());
  await page.getByRole('button', { name: 'Delete draft', exact: true }).click();
  await page.getByRole('button', { name: 'Create draft', exact: true }).waitFor();
  assert.equal(await page.getByRole('heading', { name: 'Builder draft', exact: true }).count(), 0);
  assert.equal(patches, 0);
  assert.equal(deletes, 3);
  assert.deepEqual(errors, []);
  assert.equal(await page.evaluate(() => document.documentElement.scrollWidth > innerWidth + 1), false);
  console.log('PASS: list and builder delete, confirmation/cancel, stale conflict, no save before delete, published protection, mobile layout. All API requests mocked; no application records changed.');
} finally { await browser.close(); }
