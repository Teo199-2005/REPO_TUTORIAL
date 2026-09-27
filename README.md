# CSCS Tap n Track

A comprehensive School Management System built with CodeIgniter 4 for Loboc Pilot High School (LPHS).

## Features

- **Student Management**: Complete student enrollment, profile management, and academic tracking
- **Teacher Management**: Teacher profiles, schedules, and class assignments
- **Grade Management**: Grade recording, report cards, and academic analytics
- **Attendance Tracking**: Daily attendance monitoring and reporting
- **Announcements**: School-wide communication system
- **Parent Portal**: Parent access to student information and progress
- **Document Management**: Upload and manage student documents
- **Analytics Dashboard**: Comprehensive reporting and analytics

## System Requirements

- PHP version 8.1 or higher
- MySQL/MariaDB database
- Web server (Apache/Nginx)
- Composer for dependency management

## Installation

1. Clone the repository:
```bash
git clone https://github.com/Teo199-2005/REPO_TUTORIAL.git
cd REPO_TUTORIAL
```

2. Install dependencies:
```bash
composer install
```

3. Configure environment. `.env` is git-ignored; copy the committed template and
   fill in real values:
```bash
copy .env.example .env
```

4. Update database settings in `.env` file:
```
database.default.hostname = localhost
database.default.database = cscs_sms
database.default.username = your_username
database.default.password = your_password
```

5. Create the schema. All migrations are tracked, so this is the reliable path:
```bash
php spark migrate
```
Alternatively, if you were given a SQL dump, import it (`*.sql` files are
git-ignored, so they are not part of a fresh clone):
```bash
mysql -u your_username -p cscs_sms < lphs_sms_live_import.sql
```

6. Set up web server to point to the `public` folder

## Development

```bash
php spark serve            # dev server at http://localhost:8080
php spark routes           # print the route table
vendor/bin/phpunit         # run the test suite (or: composer test)
```

## Backup & Restore

Administrators get a complete backup & restore system for the MySQL/MariaDB
database, using the database's own native clients (`mysqldump` / `mysql`).

### Schedule (admin-configurable)

A master administrator configures the plan on the page itself
(**Administration → Backup & Restore → Schedule & maintenance**): how often
(once a day, every 12 / 8 / 6 hours, every hour, or once a week) and the time
of day — plus the weekday for a weekly plan — with one switch to pause or
resume automatic backups. The schedule is stored in `system_settings`, so
changing it needs neither a `.env` edit nor a cron edit.

The scheduler only has to *call the command*; the command itself decides
whether the current window needs a backup, so the plan is always honoured:

```cron
# Linux — daily / weekly plan at the configured time:
0 2 * * * /usr/bin/php /path/to/project/spark backup:daily >> /dev/null 2>&1

# Linux — hourly / every-N-hours plan (minute matches the configured time):
0 * * * * /usr/bin/php /path/to/project/spark backup:daily >> /dev/null 2>&1

# Weekly integrity re-check of every stored dump:
0 3 * * 0 /usr/bin/php /path/to/project/spark backup:verify
```

On Windows, create the equivalent Task Scheduler task: program `php`,
arguments `"C:\path\to\project\spark" backup:daily` (`/SC HOURLY` for interval
plans). The exact `schtasks` / cron line for the current plan is shown on the
admin page together with the **next expected run**.

`backup:daily` is **idempotent per window**: at most one automatic backup is
created per day / N-hour block / hour / week, so a missed run (server reboot,
deployment) is caught up on the next pass and extra runs never duplicate a
dump. Until a schedule is saved on the page, the effective values fall back to
`backup.enabled` and `backup.scheduleHour` from `.env`.

### Rotation (rolling maximum of 30 backups)

* A new backup is registered only when the dump is complete and passes the
  integrity check (dump header, `Dump completed` marker, table definitions,
  SHA-256 checksum).
* **After** a successful dump the oldest backups are deleted until at most
  `backup.retentionCount` (default **30**) remain — always oldest first, so the
  newest backup of every day inside the window is retained. With the daily
  schedule that means one backup per day for the last 30 days.
* A **failed** dump deletes nothing: the partial file is removed and every
  existing backup stays untouched.
* Rotation never removes the dump a running restore depends on (it is protected
  for that pass).

### Restore

* Admin page: *Backup & Restore → Restore* (master admins only). The dialog
  requires typing `RESTORE` to confirm.
* Command line (disaster recovery, when the admin area itself is unusable):

```bash
php spark backup:restore --id=12 --confirm=RESTORE
php spark backup:restore --file=db-auto-20260926-020001-ab12cd34.sql --confirm=RESTORE
```

Every restore runs the same pipeline:

1. the dump must pass the integrity check (structure + SHA-256 against the
   checksum recorded at creation time, or its `.meta.json` sidecar);
2. a **pre-restore safety backup** of the current database is created — if that
   fails, the restore is aborted and the database is left untouched;
3. the dump is streamed into the `mysql` client;
4. the operation is written to the activity log and to the file log
   (`writable/logs/`), because a full restore also replaces the audit table.

### Admin dashboard

| Action | Who | Notes |
| --- | --- | --- |
| View / Create / Verify / Download | master admin + admin staff granted the *Backup & Restore* page | `Download` streams the dump with `application/sql` |
| Restore / Delete | master admin only | Restore requires the typed confirmation and a safety backup; Delete asks for confirmation |
| Configure the automatic schedule | master admin only | Frequency, time of day (weekday for weekly) and enable/pause; audited as `system.backup_schedule_updated` |

The page renders every state on the server and enhances it with AJAX, so it stays
fully usable without JavaScript (every action is also a real form POST):

* **KPI tiles** — backups stored vs the retention window (auto / manual /
  safety), total disk usage plus free space, newest backup (relative age) and
  the automatic schedule with its next expected run.
* **Live progress** — creating a backup or restoring runs through real,
  server-reported stages (`mysqldump` → verify → register → rotate, or
  verify → safety backup → restore) with a heartbeat-backed elapsed timer. The
  panel re-attaches automatically: open the page while an operation started in
  another session is still running and it picks up the live state from
  `GET admin/backups/status` (JSON, permission-checked).
* **One write operation at a time** — restores are refused while another
  backup/restore is in flight (tracked in `writable/backups/.operation.json`);
  a backup is refused while a restore runs. A process that stops reporting
  progress is considered abandoned after 5 minutes, so a killed process can
  never block the feature permanently.
* **Inventory** — search (file, id, creator), type and integrity filters,
  sortable columns ("needs attention" rows are surfaced separately), per-row
  download plus a menu with verify / restore / delete, and a *Verify all* action
  that re-checks every listed backup serially with an i/n counter. The row menu
  is positioned with a fixed Popper strategy, so it escapes the scrollable
  table wrapper instead of being clipped / forcing a scroll on the last rows.
* **Restore dialog** — shows the target file, size, tables, integrity state and
  previous restores; explains the exact steps; blocks missing/failed dumps with
  the reason; requires typing `RESTORE`. Afterwards the page explains that the
  registry (and possibly the signed-in admin row) now comes from the restored
  dump.

Everything is listed with type (automatic / manual / pre-restore safety), size,
table count, creator, integrity state and the newest/oldest backup.

### Storage & security

* Dumps live in `writable/backups/` (outside the web root, denied by
  `.htaccess`) plus a `<dump>.meta.json` sidecar carrying the checksum — that is
  what keeps a restore verifiable even when the registry itself was lost.
* Credentials are never hard-coded, stored on disk or passed on the command
  line: the clients read the `database.default.*` settings from `.env` and
  receive the password through the `MYSQL_PWD` process environment.
* Commands are executed as argv arrays without a shell (plus `bypass_shell` on
  Windows), so no shell interpretation — and therefore no command injection —
  is possible; filenames are strictly validated and every path is resolved
  inside the backup directory with a realpath containment check.
* Only master admins (and staff explicitly granted the page) can reach
  `/admin/backups`; every action is CSRF-protected and audited
  (`system.backup_created`, `system.backup_verified`, `system.backup_restored`,
  `system.backup_rotated`, `system.backup_deleted`, …).

### Configuration (optional, `.env`)

```ini
backup.retentionCount = 30          # rolling maximum; empty/invalid falls back to 30
backup.mysqldumpPath =              # absolute path when the client is not on PATH
backup.mysqlPath =                  # same for the mysql client (restore)
backup.backupDir =                  # defaults to writable/backups (keep it outside public/)
backup.enabled = true               # fallback: enabled until a schedule is saved on the admin page
backup.scheduleHour = 2             # fallback: time (HH:00) prefilled / used in the cron hint
```

The schedule chosen on the admin page is stored in `system_settings` and takes
precedence; the last two keys are only the defaults used until then (and the
values the page prefills).

`php spark backup:daily --list` prints the stored backups;
`php spark backup:verify --id=12` re-checks a single dump.

## Usage

### Default Login Credentials

**Admin:**
- Username: admin
- Password: admin123

**Teacher:**
- Username: teacher1
- Password: teacher123

**Student:**
- LRN: [Student LRN]
- Password: [Default password]

## Project Structure

```
lphs-sms/
├── app/
│   ├── Controllers/     # Application controllers
│   ├── Models/         # Database models
│   ├── Views/          # View templates
│   ├── Config/         # Configuration files
│   └── Database/       # Migrations and seeds
├── public/             # Web accessible files
├── writable/           # Cache, logs, uploads
└── vendor/             # Composer dependencies
```

## Key Features

### For Administrators
- Complete student and teacher management
- Grade oversight and analytics
- System configuration
- Report generation

### For Teachers
- Class management
- Grade recording
- Attendance tracking
- Student progress monitoring

### For Students
- View grades and attendance
- Access announcements
- Profile management

### For Parents
- Monitor child's academic progress
- View attendance records
- Receive school announcements

## Contributing

This is a capstone project for educational purposes. For any issues or suggestions, please contact the development team.

## License

This project is developed as part of an academic capstone project.

## Support

For technical support or questions about the system, please contact the development team.
