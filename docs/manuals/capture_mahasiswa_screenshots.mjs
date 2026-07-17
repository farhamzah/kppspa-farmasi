import { createRequire } from 'node:module';
import path from 'node:path';

const require = createRequire(import.meta.url);
const { chromium } = require('playwright');

const baseUrl = process.env.KP_MANUAL_BASE_URL || 'http://127.0.0.1:8023';
const outDir = path.resolve('docs/manuals/assets/mahasiswa');

const pages = [
  ['02-dashboard-mahasiswa.png', '/mahasiswa/dashboard'],
  ['03-profil-saya.png', '/profil-saya'],
  ['04-pendaftaran-kp.png', '/mahasiswa/pendaftaran-kp'],
  ['05-berkas-kp.png', '/mahasiswa/berkas-kp'],
  ['06-pemilihan-tempat-kp.png', '/mahasiswa/pemilihan-tempat'],
  ['07-penempatan-kp.png', '/mahasiswa/penempatan-kp'],
  ['08-logbook-kp.png', '/mahasiswa/logbook'],
  ['09-laporan-akhir.png', '/mahasiswa/laporan-akhir'],
  ['10-sidang-kp.png', '/mahasiswa/sidang'],
  ['11-nilai-kp.png', '/mahasiswa/nilai'],
];

async function safeGoto(page, url) {
  await page.goto(url, { waitUntil: 'domcontentloaded', timeout: 45000 });
  await page.waitForTimeout(1200);
}

async function shot(page, filename) {
  await page.evaluate(() => window.scrollTo(0, 0)).catch(() => {});
  await page.waitForTimeout(350);
  await page.screenshot({
    path: path.join(outDir, filename),
    fullPage: true,
  });
}

const browser = await chromium.launch({
  headless: true,
  executablePath: 'C:/Program Files/Google/Chrome/Application/chrome.exe',
});

const context = await browser.newContext({
  viewport: { width: 1440, height: 1000 },
  deviceScaleFactor: 1,
});

const page = await context.newPage();
const captured = [];

try {
  await safeGoto(page, `${baseUrl}/login`);
  await shot(page, '01-login-desktop.png');
  captured.push('01-login-desktop.png');

  await page.getByLabel('Email Akun SI-KP').fill('mahasiswa@sikp.test');
  await page.getByLabel('Kata Sandi').fill('password');
  await Promise.all([
    page.waitForURL(/mahasiswa|pilih-role/, { timeout: 45000 }).catch(() => {}),
    page.getByRole('button', { name: 'Buka Dashboard KP' }).click(),
  ]);
  await page.waitForTimeout(1500);

  if (page.url().includes('/pilih-role')) {
    const mahasiswaButton = page.getByText('Mahasiswa').first();
    await mahasiswaButton.click({ timeout: 15000 }).catch(() => {});
    await page.waitForTimeout(1500);
  }

  for (const [filename, route] of pages) {
    await safeGoto(page, `${baseUrl}${route}`);
    await shot(page, filename);
    captured.push(filename);
  }

  console.log(JSON.stringify({ captured }, null, 2));
} finally {
  await browser.close();
}
