# Work Clock — plain PHP time tracker (v2)

Minimal single-user time-clock website, responsive on phone and desktop. Requires PHP 8.1+, PDO SQLite, mbstring, and HTTPS for public hosting. No framework, Composer, Docker, or JavaScript build step. A few lines of vanilla JavaScript update the live timer; all records remain server-side.

## Install or upgrade

1. Make a **database backup** before updating an existing installation. The old `storage/clock.sqlite` remains compatible; do not overwrite or delete it.
2. Upload `index.php`, `config.php`, `.htaccess` and `storage/.htaccess` to your web hosting. On upgrades, **preserve your existing `config.php`** (setup secret/timezone) and your existing `storage/clock.sqlite`.
3. For a new installation, set `SETUP_CODE` in `config.php` to a unique random string of at least 20 characters and load your site's URL to create the first account. Passwords are hashed using PHP `password_hash`.
4. The `storage/` folder must be writable by PHP. Protect it from direct HTTP access. On Apache, the bundled `storage/.htaccess` blocks HTTP requests; on Nginx or other webservers, **add an equivalent explicit block**, or preferably keep storage outside the public document root and change the database path in `index.php`.
5. Serve the website **only via HTTPS**; do not expose the setup page or login on plain HTTP.

## Features

- One-click clock in/out; live elapsed timer while page is open, backed by server-side timestamps.
- Edit start/end timestamps, notes, break minutes and any number of projects, including while clocked in.
- Add completed sessions manually, delete entries with confirmation, filter history by start date.
- Today and current calendar-week net totals (30h weekly target), and project totals for all records.
- Sessions can run overnight. **Dashboard totals currently assign the full session to its start date/week**, rather than splitting a session at midnight. Breaks are deducted from its total.
- Dark mode follows system preference.
- CSV export; CSV import supports this site's **own exported CSV format** (complete entries only), up to 2 MB/5000 entries per import. Imports **append** rows and do not detect duplicates. Back up before importing. CSV format of third-party tools may require conversion.
- Consistent SQLite backup via download, authenticated access only. The backup includes projects, account hash, entries and their associations.

## Security and operational notes

- Prevent direct download of `storage/clock.sqlite`, including auxiliary SQLite `-wal` and `-shm` files. `.htaccess` only applies on Apache.
- Protect `config.php` from HTTP access. Basic IP login throttling, CSRF tokens, secure session cookies on HTTPS and password hashing are included.
- The built-in PHP development server (`php -S`) ignores `.htaccess` rules. Use it **only bound to localhost** for local testing, not publicly.
- Keep PHP updated; back up the database regularly. Prefer a SQLite backup download over copying a database file while it is in use.
- Concurrent device access uses one central SQLite database. Reload other devices to see changes made elsewhere.
- Project totals deliberately count a multiply-tagged session **in full for each assigned project**. They can exceed total work hours; projects are labels, not time allocations.
- Weekly balance measures progress against the entire 30-hour target; early in the week it will appear negative.
- SQLite database access requires server file write permission. Never commit an actual database, setup code or credentials to a public repository.
