/**
 * Authenticated check that the three AbstractFrontController adopters actually
 * RENDER DISTINCT CONTENT per tab.
 *
 * A bare HTTP 200 is not sufficient evidence: FA returns 200 with the login form
 * for anonymous requests, so every page scores "healthy" even when it renders
 * nothing. This logs in and compares per-tab content.
 */
import { chromium } from 'playwright';

const BASE = process.env.FA_BASE_URL || 'http://localhost:8090';
const ADOPTERS = [
  { module: 'ksf_FA_ProjectManagement', entry: 'index.php', param: 'view',
    tabs: ['dashboard','projects','tasks','team','reports','project_types'] },
  { module: 'ksf_FA_HRM', entry: 'hrm.php', param: 'tab',
    tabs: ['leave','payroll','employees','teams'] },
  { module: 'ksf_FA_CRM', entry: 'index.php', param: 'view',
    tabs: ['','dashboard','leads','opportunities'] },
];

const browser = await chromium.launch({ executablePath: '/usr/bin/chromium-browser', args: ['--no-sandbox'] });
const page = await browser.newPage();
await page.goto(`${BASE}/`, { waitUntil: 'domcontentloaded' });
await page.fill('input[name=user_name_entry_field]', 'opencode');
await page.fill('input[name=password]', 'opencode');
await Promise.all([page.waitForLoadState('domcontentloaded'), page.click('input[type=submit]')]);

const seen = new Map();
for (const a of ADOPTERS) {
  for (const tab of a.tabs) {
    const q = tab ? `?${a.param}=${tab}` : '';
    const r = await page.goto(`${BASE}/modules/${a.module}/${a.entry}${q}`, { waitUntil: 'networkidle' });
    const text = (await page.textContent('body')).replace(/\s+/g, ' ').trim();
    // Strip the login marker so a failed session is obvious rather than "healthy".
    const loggedIn = text.includes('Logout');
    const key = `${a.module}/${q || '(default)'}`;
    const dup = seen.get(text.slice(0, 4000));
    console.log(`  ${r.status()}  ${key.padEnd(48)} len=${String(text.length).padEnd(6)} ` +
      `session=${loggedIn ? 'ok' : 'MISSING'}${dup ? '  ** IDENTICAL to ' + dup + ' **' : ''}`);
    seen.set(text.slice(0, 4000), key);
  }
}
await browser.close();
