# Deploying to InfinityFree

InfinityFree has no SSH and no Composer, so everything is prepared on your computer and uploaded ready to run.
You'll need PHP 8.2+ and Composer on your computer, plus Python 3 for the upload script.

## 1. Create the database (InfinityFree control panel)

1. Open your hosting account in the InfinityFree client area and click **Control Panel**.
2. Go to **MySQL Databases** and create a database, e.g. `lab`. Its full name looks like `if0_12345678_lab`.
3. The same page shows the **MySQL hostname** (like `sql123.infinityfree.com`) and **username** (`if0_12345678`).
   The password is your hosting account password. It's shown under **Account details** in the client area.
4. Click **phpMyAdmin** next to the database. On the phpMyAdmin home page, note the **Server version**
   (for example `10.6.21-MariaDB` or `8.0.36`).
5. Check **PHP version** in the control panel. It must be **8.2 or newer**.

## 2. Fill in the settings

```bash
cd backend
cp deploy/infinityfree/prod.env.dist deploy/infinityfree/prod.env
```

Edit `deploy/infinityfree/prod.env`. It's git-ignored, so your passwords never get committed.

| Setting | Value |
|---|---|
| `DB_HOST` / `DB_NAME` / `DB_USER` / `DB_PASSWORD` | from step 1 |
| `DB_SERVER_VERSION` | `mariadb-10.6.21` for "10.6.21-MariaDB", or `8.0.36` for MySQL 8.0.36 |
| `MAILER_DSN` | leave empty to reuse the Gmail SMTP setting from your local `.env` |
| `APP_SECRET`, `JWT_PASSPHRASE` | leave empty; generated on the first build and saved here |
| `FTP_HOST` / `FTP_USER` / `FTP_PASSWORD` | client area → **FTP Details** (host is usually `ftpupload.net`) |
| `FTP_ROOT` | website directory from the hosting panel; this project uses `/apps.alphasoft360.com/htdocs` |

Write values without quotes. Passwords may contain any character.

## 3. Build

```bash
deploy/infinityfree/build.sh
```

This takes about a minute and creates `var/deploy/infinityfree/` containing:

- `htdocs/`: the complete site (production dependencies, compiled CSS/JS, warmed cache, fresh JWT keys,
  production settings in `.env.local.php`). PDFs over the host's 10 MB upload limit are compressed in this
  copy only; your originals are not touched.
- `database.sql`: a copy of your **local** database, converted so it imports on InfinityFree's MySQL/MariaDB.

Use `--no-db` to skip the database export when you only redeploy code.

## 4. Import the database (first deploy only)

phpMyAdmin → select your `if0_..._lab` database → **Import** → choose `var/deploy/infinityfree/database.sql` → **Import**.
It should report 19 tables. Your local logins, including `admin@gmail.com`, now work on the live site too.

Importing again later **replaces all live data** with your local data. Only do that on purpose.

To import a dump made elsewhere, e.g. with MySQL 8's `mysqldump`, first convert collations the host may not know:
`sed -e 's/utf8mb4_0900_ai_ci/utf8mb4_unicode_ci/g' -e 's/utf8mb4_uca1400_[a-z0-9_]*/utf8mb4_unicode_ci/g' in.sql > out.sql`

## 5. Upload the files

Use the domain’s website directory, not its parent folder. For `apps.alphasoft360.com`,
set `FTP_ROOT=/apps.alphasoft360.com/htdocs` in `prod.env`. The uploader uses `/htdocs` only
when `FTP_ROOT` is absent; `--remote-root` overrides the setting for a one-off upload.

Use either option.

**Option A, upload script (recommended).** It's resumable: if the connection drops, run it again and it skips
files that are already there.

```bash
python3 deploy/infinityfree/upload.py --delete
```

`--delete` removes InfinityFree's placeholder `index2.html` and leftovers from earlier builds.
It never touches `var/log`, `var/sessions` or `public/uploads`. The first upload is about 10,000 files and can take
15–60 minutes on InfinityFree's FTP. Later uploads only send what changed.

**Option B, FileZilla.** Connect with your FTP details, open the configured website directory (for this project, `apps.alphasoft360.com/htdocs/`) on the server, delete the default files
in it, and upload everything **inside** `var/deploy/infinityfree/htdocs/`, including the hidden `.htaccess` and
`.env.local.php` (in FileZilla: Server → Force showing hidden files).

## 6. Open the site

- Visit your domain. The first request after an upload can take a few seconds.
- If `https://` doesn't work, request a certificate under **Free SSL Certificates** in the client area.
- Log in at `/login` (super admin) or `/{company}/login`.

## Redeploying after code changes

```bash
deploy/infinityfree/build.sh --no-db
python3 deploy/infinityfree/upload.py --delete
```

## Troubleshooting

| Problem | Fix |
|---|---|
| "500 Internal Server Error" | Download `htdocs/var/log/prod-<date>.log` over FTP; the error is at the bottom. |
| "421 Too many connections" | InfinityFree limits accounts to 7 FTP connections. Use `--workers 3` (default). |
| Templates or invoices don't reflect updates | In `APP_ENV=prod`, Symfony caches Twig PHP classes in `var/cache/prod/twig/`. Clear that folder over FTP or redeploy with `--delete`. |
| Database connection error | Re-check the `DB_*` values (host is `sqlXXX.infinityfree.com`, not `localhost`), rebuild, re-upload. |
| Page shows "Index of /" or the InfinityFree placeholder | `.htaccess` wasn't uploaded, or `index2.html` is still there. |
| Password-reset emails don't arrive | Free hosting may block outgoing SMTP. Check `var/log/` and test from the site's "Forgot password". |
| Apps/Postman calling `/api/...` directly get an HTML page | InfinityFree's anti-bot check only lets real browsers through. The website itself is unaffected. |

## How it works

- InfinityFree serves `htdocs/` as the web root and that can't be changed. The root `.htaccess` (from
  `htaccess-root`) sends every request into `public/`, so nothing else (settings, keys, code) is reachable from the web.
- The host disables `proc_open`/`exec`, so the admin "database backup" button falls back to a PHP-based SQL dump.
- Errors are logged to `var/log/` (rotated daily, 7 days kept), because the server's own logs aren't accessible.
