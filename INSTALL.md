# Collegekhoj PHP portal setup

## Requirements

- PHP 8.1+ with PDO, PDO MySQL, Fileinfo, GD, and mbstring enabled.
- MySQL 8.0+ (or a compatible MariaDB release).
- Apache (XAMPP) or OpenLiteSpeed (CyberPanel). The root `.htaccess` uses rewrite rules, which both servers honour, to serve `index.php` at `/` and to block `.env`, `schema.sql`, `includes/` and scripts in `assets/uploads`.

## Install locally with XAMPP

1. Start Apache and MySQL in the XAMPP Control Panel.
2. In phpMyAdmin, create an empty `collegekhoj` database (collation `utf8mb4_unicode_ci`), select it, and import `schema.sql`. It creates the tables and sample catalogue data.
3. Copy `.env.example` to `.env` and set `DB_HOST`, `DB_NAME`, `DB_USER`, and `DB_PASSWORD`. Local XAMPP defaults are `127.0.0.1`, `collegekhoj`, `root`, and an empty password. Real server environment variables take precedence over `.env`.
4. If the project is served from a subdirectory, the app detects its path relative to `DOCUMENT_ROOT`. Set `APP_BASE_PATH` (for example, `/webs/collegekhoj_site`) if the server's document-root mapping makes automatic detection inaccurate. Use an empty value when hosted at the domain root.
5. Create the first administrator from PowerShell in the project directory:

   ```powershell
   $env:DB_NAME = 'collegekhoj'
   $env:DB_USER = 'root'
   $env:DB_PASSWORD = ''
   $env:ADMIN_EMAIL = 'admin@example.com'
   $env:ADMIN_PASSWORD = 'replace-with-a-unique-password-of-at-least-12-characters'
   php admin\create-admin.php
   ```

   `admin/create-admin.php` is CLI-only and never creates a built-in/default administrator. Remove the `ADMIN_*` values from the shell after setup.
   
   Alternatively, from Windows CMD, run `php admin\create-admin-account.php` and follow the prompts. This interactive setup is CLI-only, creates only the first admin account, and the password is visible while typed.
6. Visit `/` and `/admin/login.php`. Configure `assets/uploads` as writable by the PHP worker; uploads are restricted to JPG, PNG, and WebP files (5 MB maximum), and PHP execution is denied in that folder on Apache.

## Deploy to a CyberPanel VPS (OpenLiteSpeed)

1. **Website:** in CyberPanel, go to *Websites → Create Website*, choose **PHP 8.1 or newer**, and enable SSL (*SSL → Manage SSL*). Upload the project into `/home/<domain>/public_html` with File Manager, SFTP or `git clone`. Do not upload your local `.env`.
2. **PHP extensions:** over SSH, run `/usr/local/lsws/lsphp81/bin/php -m` (use your version's folder) and confirm that `pdo_mysql`, `mbstring`, `fileinfo` and `gd` are listed. Install any missing ones under *Server → PHP → Install Extensions*.
3. **PHP limits:** under *Server → PHP → Edit PHP Configs*, set `upload_max_filesize = 6M` and `post_max_size = 8M`. The defaults (2M) are smaller than the app's 5 MB image limit. Keep `display_errors = Off`.
4. **Database:** go to *Databases → Create Database*. CyberPanel prefixes the name and user (for example `colle_collegekhoj`). Open *phpMyAdmin*, select the new database, and import `schema.sql`.
5. **Config:** create `/home/<domain>/public_html/.env` from `.env.example` with `DB_HOST=localhost` and the prefixed `DB_NAME`, `DB_USER` and password. Leave `APP_BASE_PATH` commented out when the site is at the domain root.
6. **Restart LiteSpeed** (*Server Status → LiteSpeed Status → Restart*, or `systemctl restart lsws`) so OpenLiteSpeed loads the `.htaccess` rules. OpenLiteSpeed ignores `<FilesMatch>`, `Require`, `Options` and `DirectoryIndex`, so the protection relies on the rewrite rules.
7. **Check that sensitive files are blocked:** each of these must return `403`:

   ```bash
   for p in .env schema.sql includes/bootstrap.php assets/uploads/test.php; do curl -s -o /dev/null -w "$p %{http_code}
" https://<domain>/$p; done
   ```

8. **First admin:** over SSH, as the site's user, run:

   ```bash
   cd /home/<domain>/public_html
   ADMIN_EMAIL='admin@example.com' ADMIN_PASSWORD='a-unique-password-of-12+-chars' /usr/local/lsws/lsphp81/bin/php admin/create-admin.php
   ```

9. **Permissions:** files must be owned by the website user (CyberPanel does this for uploads made through File Manager). After a `git clone` or SFTP upload as root, run `chown -R <siteuser>:<siteuser> public_html` so `assets/uploads` is writable. Use `chmod 640 .env`.
10. Update `sitemap.xml` and `robots.txt` if the domain is not `collegekhoj.co.in`.

## Deployment and security notes

- Use HTTPS, a dedicated least-privilege MySQL account, a strong unique administrator password, and production PHP settings with error display disabled and errors logged.
- Back up the database and uploaded images. The database connection uses prepared PDO statements and exception mode.
- Course and university navigation is populated from active database records. Admin changes appear in the public navigation without template edits.
- Tag-manager snippets intentionally execute in visitors' browsers. Only trusted administrators should be able to manage them; use vendor-generated snippets and follow your consent/privacy requirements.
- CMS page bodies support HTML and are output as HTML. Treat page editing as privileged access and only publish reviewed, trusted markup.
- The seed course, fee, exam and institution data is illustrative. Confirm current details and recognition with the institution before publishing them as factual.
- Configure a real privacy notice, consent controls where applicable, and production analytics settings before collecting personal information or enabling tracking.
- Replace the privacy page's contact placeholder and have its contents reviewed for your jurisdiction before enabling registration in production.
