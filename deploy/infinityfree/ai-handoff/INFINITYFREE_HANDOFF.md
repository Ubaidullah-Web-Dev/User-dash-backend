# Lab App: Complete Handoff for an AI Assistant

> **Read all of this before doing anything.** It is everything needed to work on this project and its live
> deployment on InfinityFree: where the code is, how it's built, how to reach the server, the credentials,
> and the traps already found the hard way.
>
> ⚠️ **This file contains real passwords.** Never commit it to git, paste it into public places, or put it on the server.

---

## 1. What this project is

A multi-tenant laboratory / pharmacy management web app (reagent inventory, POS, lab sales, invoices,
customers, cash recovery, expenses, a marketplace storefront, and a super-admin area for managing companies).

- **Originally:** Symfony 6.4 JSON API (`backend/`) plus a separate Next.js 16 / React 19 frontend (`frontend/`).
- **Now:** a **single Symfony 6.4 app with Twig templates** that serves all the pages itself. **Next.js is no
  longer needed** (`frontend/` is only kept as a reference of the old UI). The port was checked
  pixel-by-pixel against the old Next.js app.
- **Live at:** **https://apps.alphasoft360.com** (custom domain) and **https://lab.freehosting.dev** (InfinityFree free hosting).

### Owner rules (follow these)
- Commit **only** on git branch **`twig-monolith`** in `backend/`. Never touch `main`.
- **Never use or kill anything on port 3000.** That's the owner's other project (`/home/ubaid/Public/Propfirmcheckup`).
  Avoid port 8000 too. For local test servers use **8100** (Symfony) and **3100** (old Next app, only if needed).
- **Stop every server you start** (Symfony, Next, Docker containers) when you're done.
- Don't put long timeouts on quick shell commands (grep, ls, rm, ...).
- When killing processes by pattern use a bracket trick (`pkill -f "[n]ext dev -p 3100"`) so you don't kill your own shell.

---

## 2. Paths on the owner's machine (Linux, zsh)

| What | Path |
|---|---|
| Project root | `/home/ubaid/Public/Lab/` |
| **The app (git repo)** | `/home/ubaid/Public/Lab/backend/` (branch `twig-monolith`) |
| Old Next.js frontend (reference only) | `/home/ubaid/Public/Lab/frontend/` |
| Deployment tooling | `/home/ubaid/Public/Lab/backend/deploy/infinityfree/` |
| Production settings (secrets, git-ignored) | `/home/ubaid/Public/Lab/backend/deploy/infinityfree/prod.env` |
| Build output (wiped on every build) | `/home/ubaid/Public/Lab/backend/var/deploy/infinityfree/` (`htdocs/` + `database.sql`) |
| Python venv the build uses (pypdf, Pillow) | `/home/ubaid/Public/Lab/backend/var/deploy/.venv/` (auto-created) |
| Real data from the old Next.js app | `/home/ubaid/Downloads/db_backup_2026-10-01.sql` (original MySQL 8.4 dump) |
| Same data, converted for InfinityFree | `/home/ubaid/Downloads/db_backup_2026-10-01-infinityfree.sql` |
| Smoke test script | `/home/ubaid/Public/Lab/backend/deploy/infinityfree/ai-handoff/smoke-test.mjs` |
| List of React bugs fixed in the port | `/home/ubaid/Public/Lab/backend/var/react-bug-fixes.md` |

Local tools: PHP 8.5 CLI, Composer 2, Symfony CLI (`symfony`), MariaDB client (`mariadb`, `mariadb-dump`),
Python 3, Node 20, Docker. Docker can **pull** images, but containers **can't reach the internet**
(apt/apk installs hang), so use images that already contain what you need.

---

## 3. InfinityFree account and server access

### 3.1 Hosting account
| Item | Value |
|---|---|
| Website | **https://lab.freehosting.dev** (HTTPS already works) |
| Account / username | `if0_43054013` |
| Account password (used for FTP **and** MySQL) | `minato344` |
| Control panel | InfinityFree client area → this account → **Control Panel** (owner logs in via browser) |

> The owner pasted this password into a chat, and was told to change it. If it has been changed, ask them for the
> new one and update `FTP_PASSWORD` and `DB_PASSWORD` in `prod.env`, then rebuild and re-upload. The site's database
> connection uses the password baked into `.env.local.php` on the server.

### 3.2 FTP (the only way to put files on the server)
| Item | Value |
|---|---|
| Host | `ftpupload.net` |
| Port | `21` |
| Username | `if0_43054013` |
| Password | `minato344` |
| Encryption | **Explicit FTPS works** (`AUTH TLS`, then `PROT P`). Python: `ftplib.FTP_TLS` → `connect()` → `auth()` → `login()` → `prot_p()` |
| Web root | **`/apps.alphasoft360.com/htdocs/`** for `apps.alphasoft360.com` (or `/htdocs/` for root domain). Everything for the site goes **inside** it; never upload to the domain root folder directly. |
| FTP root contents | `/htdocs`, `/.htaccess` (InfinityFree's, "DO NOT EDIT"), `/.override`, `/DO NOT UPLOAD FILES HERE` |

Quick connection test:
```bash
python3 - <<'EOF'
import ftplib
f = ftplib.FTP_TLS(timeout=30); f.connect('ftpupload.net', 21); f.auth()
f.login('if0_43054013', 'minato344'); f.prot_p()
print(f.nlst('/htdocs')); f.quit()
EOF
```

### 3.3 MySQL database
| Item | Value |
|---|---|
| Host | `sql210.infinityfree.com` |
| Port | `3306` |
| Database | `if0_43054013_lab` |
| User | `if0_43054013` |
| Password | `minato344` |
| Server type | MariaDB/MySQL (exact version unconfirmed; the app is set to `serverVersion=mariadb-10.6.0`, and the dumps are tested on both MariaDB 10.6 and MySQL 8.0) |
| Remote access | **Not possible.** InfinityFree only allows connections from its own web servers. You **cannot** connect from the owner's machine. |
| How to change data | **phpMyAdmin**, opened by the owner from the Control Panel (MySQL Databases → phpMyAdmin) |

**Importing a database:** the owner does it: phpMyAdmin → select `if0_43054013_lab` → **Import** tab →
choose the `.sql` file → **Import**. The dumps contain `DROP TABLE IF EXISTS`, so an import **replaces all live
data**. Never ask for one casually.

> Don't upload a temporary PHP script to the server to run SQL or probe it. That was tried and **blocked** by
> the assistant's safety checks. Use phpMyAdmin through the owner.

### 3.4 Server facts and InfinityFree quirks (learned the hard way)
- **No SSH, no Composer, no cron, no background workers.** Everything is built locally and uploaded ready to run.
- **PHP:** unknown exact version, assumed **≥ 8.2**. The project is pinned to PHP 8.2
  (`composer.json` → `config.platform.php = 8.2.0`). **Never** run `composer update` without that pin, because it would pull
  packages needing PHP 8.4 and the live site would crash.
- **Disabled functions:** `proc_open`, `exec`, `shell_exec`, etc. are disabled, and there is no `mysqldump` binary.
  (The admin "database backup" button falls back to a pure-PHP SQL dump for this reason.)
- **10 MB per-file upload limit.** The build automatically shrinks PDFs over the limit (copy only, not the originals).
- **FTP Connection Limit (421 error):** InfinityFree strictly limits accounts to 7 concurrent connections (`421 I can't accept more than 7 connections as the same user`). Use **3 parallel worker connections** (`upload.py --workers 3`), which keeps total sockets comfortably under the limit.
- **Twig Template Cache in Production:** In `APP_ENV=prod`, Symfony never re-reads `.twig` templates once compiled into `var/cache/prod/twig/`. If updated templates or invoice designs do not appear on the live site after deployment, clear or rename the remote `var/cache/prod/twig` directory (or use `upload.py --delete`). Twig will automatically recompile fresh templates on the next request.
- **~0.7 files/s per FTP connection.** Using 3 workers achieves ~2 files/s safely. A full upload (~9,940 files) takes ~40 min; a typical redeploy (~1,100 changed files) takes ~10–15 min including remote directory verification.
- **The host re-encodes every uploaded image with GD** (quality 90). So remote image sizes never match local ones,
  and those images get re-sent on each upload. That's harmless.
- **Files whose contents don't match their extension get locked** (permissions `000` → **403** redirect to
  `errors.infinityfree.net/errors/403/`). Example: an AVIF photo named `.jpg`. `build.sh` runs `fix_images.py` to prevent this.
  If you ever see a 403 on a static file, check its permissions over FTP (`MLSD` fact `unix.mode`). If it shows `00`,
  **delete** the remote file (a locked file can't be overwritten) and upload a correctly-encoded one.
- **Anti-bot check:** InfinityFree serves a JavaScript challenge (`aes.js`, sets a `__test` cookie, adds `?i=1`).
  **`curl`/`wget` get the challenge page, not the site.** Test the live site with a real browser (Playwright/Chromium).
  The same check blocks non-browser API clients (Postman, mobile apps) from calling `/api/...` directly.
- FTP sometimes drops idle connections (`421 Timeout`). `upload.py` reconnects automatically.
- **Server error logs aren't accessible.** The app writes its own logs to `htdocs/var/log/prod-YYYY-MM-DD.log`
  (rotated daily, 7 kept). **On a 500 error, download that file over FTP and read the bottom.**
- **Outgoing email (Gmail SMTP for password-reset codes) is UNTESTED** on this host. It may be blocked.

---

## 4. How the live site is laid out on the server

InfinityFree serves `/htdocs/` as the web root and that can't be changed, so the **whole Symfony project lives inside
`/htdocs/`**, and `/htdocs/.htaccess` (from `deploy/infinityfree/htaccess-root`) rewrites every request into `public/`:

```
/htdocs/
  .htaccess            ← routes everything to public/ (nothing else is web-reachable)
  .env.local.php       ← production settings (DB URL, APP_SECRET, JWT passphrase, mailer) — generated by build.sh
  composer.json        ← must exist: Symfony uses it to find the project root
  config/  (config/jwt/private.pem + public.pem — JWT keys, regenerated on every build)
  src/  templates/  assets/  migrations/  translations/  importmap.php  bin/
  vendor/              ← production dependencies (no dev packages)
  var/cache/prod/      ← pre-warmed cache (built locally; relocatable)
  var/log/             ← app logs (protected from deletion by upload.py --delete)
  public/
    .htaccess  index.php  favicon.ico
    assets/            ← compiled CSS/JS (AssetMapper output, hashed filenames)
    images/  partner-logos/  tenants/<company-slug>/{images,pdfs}/
```
Verified: `.env.local.php`, `config/jwt/*.pem`, `vendor/`, `src/` and `var/` **are not downloadable** from the web.
(Unknown top-level paths like `/.env.local.php` return the tenant home page, because any `/{slug}` is treated as a company. That's expected.)

---

## 5. Deploying (the normal workflow)

All commands run from `/home/ubaid/Public/Lab/backend/`.

### 5.1 Redeploy after code changes (most common)
```bash
deploy/infinityfree/build.sh --no-db
python3 deploy/infinityfree/upload.py --workers 3
```
- `build.sh` copies the project to `var/deploy/infinityfree/htdocs/` and then:
  - installs production dependencies (`composer install --no-dev`) and trims vendor;
  - gzips the symfony/intl data;
  - writes `.env.local.php` from `prod.env`;
  - regenerates the JWT keys;
  - builds Tailwind CSS (minified);
  - runs `importmap:install`, `assets:install` and `asset-map:compile`;
  - warms the prod cache;
  - fixes mislabeled images;
  - shrinks PDFs over 10 MB;
  - fails if any file is still over 10 MB.
- `upload.py` is **resumable**: if it stops, run it again and it skips files already uploaded with the same size.
  Files under `var/cache/` and `.env.local.php` are always re-sent.
- Add `--delete` to `upload.py` to remove remote files that aren't in the build (stale cache etc.). It never deletes
  `var/log`, `var/sessions` or `public/uploads`. It's slower because it lists every remote folder.
- `upload.py --dry-run` shows what would happen without changing anything.
- Run long uploads **in the background** and poll the log; a full upload can take 40+ minutes.
- Regenerating JWT keys on every build logs out anyone using API tokens. Browser sessions use cookies and keep working.

### 5.2 First deploy / full database push (only on explicit request)
```bash
deploy/infinityfree/build.sh          # also exports the LOCAL database to var/deploy/infinityfree/database.sql
python3 deploy/infinityfree/upload.py --workers 3 --delete
```
Then the **owner** imports `database.sql` in phpMyAdmin (see 3.3). Remember this **overwrites live data with local data**.

### 5.3 Importing a dump from somewhere else
Convert collations InfinityFree may not support, then have the owner import it:
```bash
sed -e 's/utf8mb4_0900_ai_ci/utf8mb4_unicode_ci/g' -e 's/utf8mb4_uca1400_[a-z0-9_]*/utf8mb4_unicode_ci/g' \
    -e 's/ DEFINER=`[^`]*`@`[^`]*`//g' in.sql > out.sql
```
Test it first in a throwaway container (the images are already pulled locally):
```bash
docker run -d --name imp-test --network host -e MARIADB_ROOT_PASSWORD=t -e MARIADB_DATABASE=lab \
  -v "$PWD/out.sql:/d.sql:ro" mariadb:10.6 --port=3307
# wait until it accepts connections, then:
docker exec imp-test sh -c 'mariadb -uroot -pt lab < /d.sql && echo imported'
DATABASE_URL="mysql://root:t@127.0.0.1:3307/lab?serverVersion=mariadb-10.6.0&charset=utf8mb4" php bin/console doctrine:schema:validate
docker rm -f imp-test
```

### 5.4 Settings file `deploy/infinityfree/prod.env` (git-ignored, already filled in)
Keys: `DB_HOST`, `DB_PORT`, `DB_NAME`, `DB_USER`, `DB_PASSWORD`, `DB_SERVER_VERSION`, `MAILER_DSN` (empty = reuse the Gmail
SMTP from local `.env`), `APP_SECRET` and `JWT_PASSPHRASE` (generated once, keep them), `FTP_HOST`, `FTP_PORT`, `FTP_USER`, `FTP_PASSWORD`.
Plain `KEY=value` lines, no quotes. Template: `prod.env.dist`. Human guide: `deploy/infinityfree/README.md`.

---

## 6. Verifying the live site

Use a real browser, because of the anti-bot check:
```bash
cd /home/ubaid/Public/Lab/backend/deploy/infinityfree/ai-handoff
npm i playwright && npx playwright install chromium     # first time only
BASE=https://apps.alphasoft360.com SLUG=unique-healthcare-solutions \
  USER_EMAIL=<a real super-admin email> USER_PASSWORD=<its password> node smoke-test.mjs
```
It opens 48 routes logged out and logged in, and prints `routes with problems: N/48` for each pass. The last live
run (with the old test data) was **0/48** for both.

Checking a single static file's status from inside a browser context (Playwright):
`await ctx.request.get('https://lab.freehosting.dev/images/logo.png', { maxRedirects: 0 })`. A `302` to
`errors.infinityfree.net/errors/403/` means the file is locked (see 3.4).

---

## 7. Database and accounts

### Current live data, and an important open item
1. The site was first deployed with the **local test database** (contains `admin@gmail.com` / `password123` as super admin).
2. The owner then provided the **real data from the old Next.js app**: `/home/ubaid/Downloads/db_backup_2026-10-01-infinityfree.sql`.
   It contains 248 products, 304 orders, 1,159 order items, 72 customers, 147 cash recoveries, 39 categories, 7 users and 5 companies.
   It was verified: `doctrine:schema:validate` OK, imports on MariaDB 10.6 and MySQL 8.0, all 48 routes OK locally.
   The owner was asked to import it via phpMyAdmin. **Whether they did is unconfirmed.** Ask them, or check by
   logging in on the live site.

**Accounts in the real (Next.js) data:** `admin@gmail.com` does **not** exist there.
| Email | Role | Company |
|---|---|---|
| `superadmin@gmail.com` | ROLE_SUPER_ADMIN | 1 |
| `admin-acme@gmail.com` | ROLE_ADMIN | 2 (acme) |
| `admin-tesla@gmail.com` | ROLE_ADMIN | 3 (tesla) |
| `admin-test@gmail.com` | ROLE_ADMIN, ROLE_COMPANY_ADMIN | 4 (test) |
| `mishalmuhammad22@gmail.com`, `drahmadhassan1@gmail.com`, `faizandev46@gmail.com` | ROLE_ARCHITECTURAL, ROLE_USER | 1 |

Passwords are bcrypt hashes; only the owner knows them.

**Companies (tenants) in the real data:** `unique-healthcare-solutions` (id 1, the main one), `acme`, `tesla`, `test`, `demo`.
In the **old test data** the ids differ, and there is also a duplicate `uniquehealthcaresolutions` with no images.

### Local development database
`mysql://symfony:password123@127.0.0.1:3306/symfony_db` (MariaDB 12). User `symfony` has rights only on `symfony_db`
(it can't create other databases, so use a Docker container for scratch DBs). It holds the **test** data
(super admin `admin@gmail.com` / `password123`).

---

## 8. URLs and pages

- `/`: AlphaSoft360 landing page · `/login`: global (super-admin) login · `/logout` (POST)
- `/super-admin`, `/super-admin/companies`, `/super-admin/banner`
- `/{companySlug}`: tenant home · `/{slug}/login|register|forgot-password|reset-password`
- `/{slug}/marketplace`, `/{slug}/marketplace/{productSlug}`, `/{slug}/marketplace/post-ad`, `/{slug}/cart`,
  `/{slug}/checkout/address`, `/{slug}/checkout/summary/{id}`, `/{slug}/posts`, `/{slug}/profile/my-ads`, `/{slug}/profile/settings`
- `/{slug}/admin/...`: dashboard-lab, reagents (+new, {id}/edit), users, stock (+low, new, {id}/in, vendors/new), customers-lab (+new, {id}/edit),
  sales-lab, reports-lab, invoices-lab, cash-recovery, lab-expenses, settings, pos, orders (+new), vendors (+new), categories (+{id}), products, customers, dashboard
- JSON API (unchanged from the original backend): `/api/...` and `/{slug}/api/...` (e.g. `POST /{slug}/api/login` returns a JWT)
- Tenant assets: `/tenants/{slug}/images/*`, `/tenants/{slug}/pdfs/*`, served from `public/tenants/`

---

## 9. Code architecture (for code changes)

- **Symfony 6.4**, Doctrine ORM 3 (doctrine-bundle 2.x), LexikJWT, AssetMapper + importmap (**no Node build step**),
  Tailwind v4 via `symfonycasts/tailwind-bundle` (standalone binary), Symfony UX Twig Components, UX Icons (lucide),
  Stimulus (theme toggle, sidebar, toaster, flash), Turbo loaded but `Turbo.session.drive = false`.
- **Pages:** `src/Controller/Web/PageController.php` holds all page routes. Templates are in `templates/` (`pages/`, `security/`, `shop/`,
  `marketplace/`, `admin/`, `super_admin/`, `components/`). Each page's behaviour is an **Alpine.js** module in
  `assets/pages/<name>.js`, registered in the `pages` map in `assets/app.js` and selected with `{% block pages %}<name>{% endblock %}`.
  Pages call the **same JSON API** the React app used.
- **JS shims (`assets/lib/`):** `api.js` (axios-like; same-origin session plus `X-CSRF-Token`), `toast.js` (sonner API), `auth.js`, `router.js`,
  `utils.js`, `motion.js`, `ui.js` (Alpine Select/Dialog, `x-react-value`).
- **Security:** `src/Security/` holds LoginFormAuthenticator (AJAX JSON or form login, remember-me 1 year), ApiCsrfListener (CSRF on
  cookie-authenticated API writes), AdminAreaGuard / AdminAccess (role rules from the old React layout), LogoutRedirectSubscriber.
  The API firewall shares the session context with the web firewall and also accepts JWT.
- **Twig gotchas:** on `<twig:Component>` tags use `x-bind:` / `x-on:` (a `:` prefix means a Twig prop). `{% import %}` macros don't work inside
  component content. Component templates must **not** end with a newline. Never put `*/` inside CSS/PHP comments.
- **Local dev:** `cd backend && symfony server:start -d --no-tls --port=8100`. After changing CSS classes, run `php bin/console tailwind:build`.
  `backend/php.ini` (git-ignored, dev only) sets `opcache.revalidate_freq=0`. Stop it afterwards with `symfony server:stop`.
- Commit messages end with: `Co-Authored-By: <your model> <noreply@anthropic.com>` (or follow the owner's preference).

---

## 10. Troubleshooting cheat sheet

| Symptom | Cause / fix |
|---|---|
| Live site shows HTTP 500 | Download `/htdocs/var/log/prod-<today>.log` over FTP and read the last lines. |
| "An exception occurred in the driver" / DB connection errors | Wrong `DB_*` in `prod.env` (host must be `sql210.infinityfree.com`), or the password was changed. Fix it, then rebuild and re-upload (at least `.env.local.php`). |
| A static image/file returns 403 | Locked by the host (permissions `000`). Delete it via FTP, fix its encoding, re-upload. |
| `curl` shows a page with `aes.js` | InfinityFree anti-bot check. Use Playwright instead. |
| Upload very slow or drops | Normal for this host. Use `--workers 6`; just re-run (resumable). |
| Page loads "forever" | Look for repeated failing requests (image `onerror` loops were one cause; now fixed). |
| Password-reset email doesn't arrive | SMTP may be blocked on free hosting. Check `var/log`. Consider an HTTP-API mail provider. |
| Build fails "files still over the 10MB upload limit" | A non-PDF big file was added to `public/`. Compress it or host it elsewhere. |
| `composer` wants PHP 8.4 | Someone removed `config.platform.php` in `composer.json`. Restore `8.2.0`. |

---

## 11. Before you finish any task
- Stop any local servers or Docker containers you started (`symfony server:stop`, `docker rm -f <name>`). Never touch port 3000.
- If code changed: commit on `twig-monolith`, rebuild, upload, then run the live smoke test.
- Tell the owner plainly what changed, what you verified, and anything they must do themselves (e.g. a phpMyAdmin import).


## Subdomain deployment correction (2026-10-01)

`apps.alphasoft360.com` resolves to this hosting account, but its document root is
`/apps.alphasoft360.com/htdocs`, not `/htdocs` or `/apps.alphasoft360.com`.
The initial subdomain folder contained only `index2.html`; the application directory
structure above it was empty. Deploy the complete production build inside its htdocs.
`prod.env` now sets `FTP_ROOT=/apps.alphasoft360.com/htdocs`, and `upload.py` reads this
setting. Use `--remote-root /htdocs` explicitly when updating `lab.freehosting.dev`.
Both deployments use the existing database; no database import is needed.

---

## 12. Financial Calculator & Lab Summary Report Architecture (2026-10-29)

### Service Architecture
- All financial calculations are centralized in `App\Service\FinancialCalculatorService`.
- Extensively verified with comprehensive test suites:
  - `tests/Service/FinancialCalculatorTest.php` (1,100+ assertions across 150 test scenarios)
  - `tests/Controller/RandomDateRangeAuditTest.php` (100 randomized multi-tenant audit tests)
  - `tests/Controller/LabInvoiceSummaryReportTest.php`

### Dual-Matrix Financial Reporting
The summary report (`/admin/labs/invoice/summary` rendered by `templates/invoice/summary_report.html.twig`) separates invoiced accounting from cash collected:
1. **Sales & Profitability Matrix (Invoiced Activity)**:
   - **Billed Revenue**: Sum of order totals in the period ($\text{Net Revenue} = \text{Gross} - \text{Discounts}$).
   - **Cost of Goods (COGS)**: Sum of reagent/product purchase prices ($\text{Purchase Price} \times \text{Quantity}$) for items dispatched in those orders.
   - **Sales Profit**: $\text{Billed Revenue} - \text{COGS}$. Represents gross trading profit on dispatched orders.
   - **Discounts Given**: Total client concessions and price write-offs.
   - **Pending Receivables**: Uncollected balance on orders generated during this period (unpaid orders where $\text{Amount Tendered} < \text{Total}$).
2. **Cash Flow & Collections Matrix (Cash In Hand)**:
   - **POS Cash Collected**: Cash tendered at the counter for new orders during this period.
   - **Debt Recoveries**: Cash collected via `CashRecovery` records for older/existing unpaid customer balances.
   - **Total Cash Inflow**: $\text{POS Cash Collected} + \text{Debt Recoveries}$.
   - **Operating Expenses**: Total lab expenses (`LabExpense`) + inventory vendor restocking costs (`VendorOrder`).
   - **Net Cash Flow (Net Income)**: $\text{Total Cash Inflow} - \text{Operating Expenses}$. Represents real cash surplus/deficit.
