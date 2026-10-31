# Collegekhoj PHP portal setup

## Requirements

- PHP 8.1+ with PDO, PDO MySQL, Fileinfo, GD, and mbstring enabled.
- MySQL 8.0+ (or a compatible MariaDB release).
- Apache with PHP enabled. The root `.htaccess` prefers `index.php`; the existing static `index.html` remains available as a fallback.

## Install locally with XAMPP

1. Start Apache and MySQL in the XAMPP Control Panel.
2. Import `schema.sql` in phpMyAdmin, or run it with the MySQL command-line client. It creates the `collegekhoj` database and sample catalogue data.
3. Configure `DB_HOST`, `DB_NAME`, `DB_USER`, and `DB_PASSWORD` in the Apache/PHP environment. Local XAMPP defaults are `127.0.0.1`, `collegekhoj`, `root`, and an empty password; replace these defaults for any deployed environment.
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

## Deployment and security notes

- Use HTTPS, a dedicated least-privilege MySQL account, a strong unique administrator password, and production PHP settings with error display disabled and errors logged.
- Back up the database and uploaded images. The database connection uses prepared PDO statements and exception mode.
- Course and university navigation is populated from active database records. Admin changes appear in the public navigation without template edits.
- Tag-manager snippets intentionally execute in visitors' browsers. Only trusted administrators should be able to manage them; use vendor-generated snippets and follow your consent/privacy requirements.
- CMS page bodies support HTML and are output as HTML. Treat page editing as privileged access and only publish reviewed, trusted markup.
- The seed course, fee, exam and institution data is illustrative. Confirm current details and recognition with the institution before publishing them as factual.
- Configure a real privacy notice, consent controls where applicable, and production analytics settings before collecting personal information or enabling tracking.
- Replace the privacy page's contact placeholder and have its contents reviewed for your jurisdiction before enabling registration in production.
