<?php
namespace Config;

use CodeIgniter\Config\BaseService;

/**
 * Services Configuration file.
 *
 * Services are simply other classes/libraries that the system uses
 * to do its job. This is used by CodeIgniter to allow the core of the
 * framework to be swapped out easily without affecting the usage within
 * the rest of your application.
 *
 * This file holds any application-specific services, or service overrides
 * that you might need. An example has been included with the general
 * method format you should use for your service methods. For more examples,
 * see the core Services file at system/Config/Services.php.
 */
class Services extends BaseService
{
    /*
     * public static function example($getShared = true)
     * {
     *     if ($getShared) {
     *         return static::getSharedInstance('example');
     *     }
     *
     *     return new \CodeIgniter\Example();
     * }
     */

    /**
     * Shared activity/audit log writer (single instance per request so the
     * request id and hash chain stay consistent across every event).
     */
    public static function auditLogger($getShared = true): \App\Libraries\AuditLogger
    {
        if ($getShared) {
            return static::getSharedInstance('auditLogger');
        }

        return new \App\Libraries\AuditLogger();
    }

    /**
     * Database backup / restore engine (native mysqldump + mysql clients).
     * Shared so the resolved client paths and directory checks are memoized.
     */
    public static function databaseBackup($getShared = true): \App\Libraries\DatabaseBackupService
    {
        if ($getShared) {
            return static::getSharedInstance('databaseBackup');
        }

        return new \App\Libraries\DatabaseBackupService();
    }
}
