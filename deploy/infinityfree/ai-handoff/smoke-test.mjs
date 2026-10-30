// Smoke test: opens every page logged out and logged in, reports HTTP/JS/Alpine errors.
// Usage: BASE=https://lab.freehosting.dev SLUG=unique-healthcare-solutions USER_EMAIL=... USER_PASSWORD=... node smoke-test.mjs
// Needs: npm i playwright && npx playwright install chromium
import { chromium } from 'playwright';
const B = process.env.BASE || 'http://127.0.0.1:8100';
const S = process.env.SLUG || 'unique-healthcare-solutions';
const routes = [
  '/', '/login', `/${S}`, `/${S}/login`, `/${S}/register`, `/${S}/forgot-password`, `/${S}/reset-password?token=x`,
  `/${S}/marketplace`, `/${S}/marketplace/post-ad`, `/${S}/cart`, `/${S}/checkout/address`, `/${S}/checkout/summary/1`,
  `/${S}/posts`, `/${S}/profile/my-ads`, `/${S}/profile/settings`,
  `/${S}/admin`, `/${S}/admin/dashboard`, `/${S}/admin/dashboard-lab`, `/${S}/admin/customers`, `/${S}/admin/categories`, `/${S}/admin/categories/1`,
  `/${S}/admin/customers-lab`, `/${S}/admin/customers-lab/new`, `/${S}/admin/customers-lab/1/edit`, `/${S}/admin/invoices-lab`,
  `/${S}/admin/lab-expenses`, `/${S}/admin/cash-recovery`, `/${S}/admin/orders`, `/${S}/admin/orders/new`, `/${S}/admin/pos`,
  `/${S}/admin/products`, `/${S}/admin/reagents`, `/${S}/admin/reagents/new`, `/${S}/admin/reagents/1/edit`, `/${S}/admin/reports-lab`,
  `/${S}/admin/sales-lab`, `/${S}/admin/settings`, `/${S}/admin/stock`, `/${S}/admin/stock/low`, `/${S}/admin/stock/new`, `/${S}/admin/stock/1/in`,
  `/${S}/admin/stock/vendors/new`, `/${S}/admin/users`, `/${S}/admin/vendors`, `/${S}/admin/vendors/new`,
  '/super-admin', '/super-admin/companies', '/super-admin/banner',
];
const b = await chromium.launch();
async function run(ctx, label) {
  let bad = 0;
  for (const path of routes) {
    const p = await ctx.newPage();
    const errs = [];
    p.on('pageerror', e => errs.push('JS: ' + e.message.slice(0, 160)));
    p.on('console', m => {
      const t = m.text();
      if (/Alpine|Uncaught|is not defined|Cannot read/.test(t) && (m.type() === 'error' || m.type() === 'warning')) errs.push(`${m.type()}: ${t.slice(0, 200)}`);
    });
    p.on('response', r => { if (r.status() >= 500) errs.push(`HTTP ${r.status()} ${r.url().replace(B, '')}`); });
    let status = 'ERR';
    try { const resp = await p.goto(B + path, { waitUntil: 'networkidle', timeout: 30000 }); status = resp.status(); } catch (e) { errs.push('NAV: ' + e.message.slice(0, 100)); }
    await p.waitForTimeout(400);
    const h1 = (await p.locator('h1').first().innerText({ timeout: 1000 }).catch(() => '')).replace(/\s+/g, ' ').slice(0, 45);
    if (errs.length || status >= 400) bad++;
    console.log(`[${label}] ${status} ${path} -> ${p.url().replace(B, '')} | ${h1}${errs.length ? '\n    ' + [...new Set(errs)].join('\n    ') : ''}`);
    await p.close();
  }
  console.log(`[${label}] routes with problems: ${bad}/${routes.length}`);
}
if (!process.env.AUTH_ONLY) await run(await b.newContext(), 'anon');
if (process.env.ANON_ONLY) { await b.close(); process.exit(0); }
const ctx = await b.newContext();
const lp = await ctx.newPage();
await lp.goto(`${B}/${S}/login`); await lp.waitForLoadState('networkidle');
await lp.fill('input[type=email]', process.env.USER_EMAIL || 'admin@gmail.com'); await lp.fill('input[placeholder=Password]', process.env.USER_PASSWORD || 'password123');
await lp.click('button[type=submit]'); await lp.waitForURL(/admin|super/, { timeout: 30000 }); await lp.close();
await run(ctx, 'auth');
await b.close();
