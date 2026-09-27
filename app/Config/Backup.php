<?php

declare(strict_types=1);

namespace Config;

use CodeIgniter\Config\BaseConfig;

/**
 * Database backup / restore settings.
 *
 * Every property can be overridden from `.env` with the `backup.` prefix,
 * e.g.:
 *
 *     backup.retentionCount = 30
 *     backup.mysqldumpPath = /usr/bin/mysqldump
 *
 * No credential is stored here (and none may be added): the dump/restore
 * clients read the connection from Config\Database, which is fed by the
 * environment (.env), and the password is handed to the client through its
 * process environment — never on the command line and never in a log.
 */
class Backup extends BaseConfig
{
    /**
     * Absolute directory that stores the dumps. Must stay OUTSIDE the web root
     * (the default — writable/ — is not served and is protected by .htaccess).
     */
    public string $backupDir = WRITEPATH . 'backups';

    /**
     * Rolling maximum number of backups. When a new backup would exceed this
     * number the oldest backup is removed first, so at least the newest
     * backup of every one of the last 30 days is retained when the daily
     * schedule runs. Never lower than 1.
     */
    public int $retentionCount = 30;

    /**
     * Allows `php spark backup:daily` to create automatic backups.
     * Set `backup.enabled = false` to pause the schedule without touching cron.
     */
    public bool $enabled = true;

    /**
     * Hour of the day (server time) the daily job is expected to run at.
     * Informational: it is shown on the admin page and used in the cron hint.
     */
    public int $scheduleHour = 2;

    /**
     * Absolute path to the mysqldump client. Empty = auto-detect (PATH plus the
     * usual XAMPP / WAMP / Laragon / Linux / macOS locations).
     */
    public string $mysqldumpPath = '';

    /** Absolute path to the mysql client used by restore. Empty = auto-detect. */
    public string $mysqlPath = '';

    /** Hard timeout (seconds) for one dump or restore process. */
    public int $processTimeout = 3600;

    /** How many bytes of client diagnostics are captured for logs/audit. */
    public int $maxDiagnosticBytes = 8000;
}
