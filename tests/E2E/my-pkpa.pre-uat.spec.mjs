import { expect, test } from '@playwright/test';

const credentials = {
  admin: [process.env.E2E_ADMIN_EMAIL || 'admin@sikp.test', process.env.E2E_ADMIN_PASSWORD || 'password'],
  koordinator: [process.env.E2E_COORDINATOR_EMAIL || 'koordinator@sikp.test', process.env.E2E_COORDINATOR_PASSWORD || 'password'],
  mahasiswa: [process.env.E2E_STUDENT_EMAIL || 'mahasiswa@sikp.test', process.env.E2E_STUDENT_PASSWORD || 'password'],
  pd: [process.env.E2E_INTERNAL_SUPERVISOR_EMAIL || 'dosen@sikp.test', process.env.E2E_INTERNAL_SUPERVISOR_PASSWORD || 'password'],
  pl: [process.env.E2E_FIELD_SUPERVISOR_EMAIL || 'lapangan@sikp.test', process.env.E2E_FIELD_SUPERVISOR_PASSWORD || 'password'],
};

async function assertHealthyPage(page) {
  await page.waitForLoadState('domcontentloaded').catch(() => {});
  await expect(page.locator('body')).toBeVisible();
  await expect(page.locator('body')).not.toContainText(/Exception|Server Error|Undefined variable|SQLSTATE|Stack trace|Whoops/i);
  const overflow = await page.evaluate(() => document.documentElement.scrollWidth > document.documentElement.clientWidth);
  expect(overflow).toBeFalsy();
}

async function visit(page, path) {
  await page.goto(path, { waitUntil: 'domcontentloaded', timeout: 20_000 });
  await assertHealthyPage(page);
}

async function expectForbiddenRequest(page, path) {
  const response = await page.request.get(path);
  expect([403, 404]).toContain(response.status());
  expect(await response.text()).not.toMatch(/storage\/app|DB_PASSWORD|Stack trace|SQLSTATE/i);
}

async function login(page, [email, password]) {
  await page.context().clearCookies();
  await page.goto('/login');
  if (!await page.getByLabel(/Email akun Core Farmasi/i).count()) {
    if (await page.getByRole('button', { name: /Keluar|Logout/i }).count()) return;
    await page.goto('/logout').catch(() => {});
    await page.goto('/login');
  }
  await page.getByLabel(/Email akun Core Farmasi/i).fill(email);
  await page.getByLabel(/^Password$/i).fill(password);
  await page.getByRole('button', { name: /Masuk ke Dashboard/i }).click();
  await page.waitForLoadState('networkidle').catch(() => {});
}

async function logout(page) {
  const keluar = page.getByRole('button', { name: /Keluar|Logout/i });
  if (await keluar.count()) {
    await keluar.first().click();
    await page.waitForLoadState('networkidle').catch(() => {});
  }
}

async function selectRole(page, roleName) {
  const form = page.locator('form').filter({ has: page.getByRole('heading', { name: roleName }) }).first();
  await form.getByRole('button', { name: /Masuk/i }).click();
  await page.waitForLoadState('networkidle').catch(() => {});
}

test.beforeEach(async ({ page }) => {
  const consoleErrors = [];
  const failedRequests = [];
  page.on('console', (msg) => {
    if (msg.type() === 'error') consoleErrors.push(msg.text());
  });
  page.on('requestfailed', (request) => failedRequests.push(request.url()));
  page.e2eErrors = consoleErrors;
  page.e2eFailedRequests = failedRequests;
});

test.afterEach(async ({ page }) => {
  expect(page.e2eErrors, 'console errors').toEqual([]);
  expect(page.e2eFailedRequests, 'failed requests').toEqual([]);
});

test('guest landing, login, health, and protected redirect are clean', async ({ page }) => {
  await page.goto('/');
  await expect(page).toHaveTitle(/MY PKPA/);
  await expect(page.getByRole('heading', { name: /MY PKPA Portal PKPA UBP/i })).toBeVisible();
  await expect(page.getByText(/Apotek/i).first()).toBeVisible();
  await assertHealthyPage(page);

  await page.goto('/login');
  await expect(page.getByRole('button', { name: /Masuk ke Dashboard/i })).toBeVisible();
  await assertHealthyPage(page);

  const health = await page.request.get('/health');
  expect(health.ok()).toBeTruthy();
  expect(await health.text()).not.toMatch(/sikp_farmasi_ubp|DB_PASSWORD|stack/i);

  await page.goto('/management/pkpa-documents');
  await expect(page).toHaveURL(/\/login/);
  await assertHealthyPage(page);
});

test('admin can open PKPA management surfaces and student cannot escalate', async ({ page }) => {
  await login(page, credentials.admin);
  if (await page.getByText(/Pilih Role/i).count()) {
    await selectRole(page, /Admin/i);
  }

  for (const path of [
    '/admin/dashboard',
    '/management/pkpa-programs',
    '/management/pkpa-practice-domains',
    '/management/pkpa-practice-sites',
    '/management/pkpa-enrollments',
    '/management/pkpa-student-groups',
    '/management/pkpa-placement-planner',
    '/management/pkpa-publications',
    '/management/pkpa-operations',
    '/management/pkpa-academics',
    '/management/pkpa-assessments',
    '/management/pkpa-final-program',
    '/management/pkpa-documents',
    '/management/pkpa-analytics',
    '/admin/health',
  ]) {
    await visit(page, path);
  }

  await logout(page);
  await login(page, credentials.mahasiswa);
  await expectForbiddenRequest(page, '/management/pkpa-documents');
});

test('coordinator multi-role, internal supervisor, field supervisor, and student surfaces load', async ({ page }) => {
  await login(page, credentials.koordinator);
  await expect(page.getByText(/Pilih akses untuk melanjutkan/i)).toBeVisible();
  await selectRole(page, /Koordinator KP/i);
  await visit(page, '/management/pkpa-placement-readiness');
  await visit(page, '/management/pkpa-placement-planner');
  await logout(page);

  await login(page, credentials.pd);
  if (await page.getByText(/Pembimbing Dalam/i).count()) {
    await selectRole(page, /Pembimbing Dalam/i);
  }
  for (const path of ['/pembimbing-dalam/monitoring-pkpa', '/pembimbing-dalam/akademik-pkpa', '/pembimbing-dalam/penilaian-pkpa']) {
    await visit(page, path);
  }
  await expectForbiddenRequest(page, '/pembimbing-lapangan/operasional-pkpa');
  await logout(page);

  await login(page, credentials.pl);
  for (const path of ['/pembimbing-lapangan/operasional-pkpa', '/pembimbing-lapangan/akademik-pkpa', '/pembimbing-lapangan/penilaian-pkpa']) {
    await visit(page, path);
  }
  await logout(page);

  await login(page, credentials.mahasiswa);
  for (const path of ['/mahasiswa/dashboard', '/mahasiswa/rotasi-pkpa', '/mahasiswa/akademik-rotasi', '/mahasiswa/nilai-pkpa', '/mahasiswa/hasil-akhir-pkpa', '/mahasiswa/dokumen-pkpa']) {
    await visit(page, path);
  }
});

test('download endpoints keep private access controls', async ({ page }) => {
  await login(page, credentials.admin);
  const exportResponse = await page.request.get('/management/pkpa-documents/export');
  expect([200, 204, 302]).toContain(exportResponse.status());
  expect(exportResponse.headers()['x-content-type-options']).toBe('nosniff');
  await logout(page);

  await login(page, credentials.mahasiswa);
  const forbidden = await page.request.get('/management/pkpa-documents/export');
  expect([403, 404]).toContain(forbidden.status());
  expect(await forbidden.text()).not.toMatch(/storage\/app|DB_PASSWORD|Stack trace/i);
});
