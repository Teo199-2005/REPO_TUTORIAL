<?php

declare(strict_types=1);

namespace App\Libraries;

use Throwable;

/**
 * Live progress + concurrency tracker for long running backup/restore jobs.
 *
 * A single JSON sidecar (`<backupDir>/.operation.json`) records which operation
 * is running, its current stage, who started it and when it was last heard
 * from. The admin page polls it (through Admin\Backups::status) to render real
 * progress instead of a frozen tab spinner, and the backup service consults it
 * before starting a restore so two restores can never stream into MySQL at the
 * same time (which would corrupt data).
 *
 * Safety properties:
 *  - every filesystem action is best effort: a tracker failure is swallowed and
 *    can never break a backup or a restore;
 *  - writes are atomic (temp file + rename) and token-scoped, so two processes
 *    can never mix up each other's state and a stale process can never clobber
 *    a live one;
 *  - no credential ever touches the file: only actor name, stage, timestamps
 *    and the dump filename (all already shown on the admin page) are stored;
 *  - an operation with no heartbeat for `STALE_SECONDS` is treated as
 *    abandoned, so a killed process cannot block the feature forever.
 */
final class BackupOperationTracker
{
    /** Sidecar filename inside the backup directory. */
    public const FILENAME = '.operation.json';

    /** Seconds without a heartbeat after which a silent operation is abandoned. */
    public const STALE_SECONDS = 300;

    /** Minimum gap (seconds) between two heartbeat writes. */
    public const HEARTBEAT_SECONDS = 2;

    /** Human labels for every stage the service can report. */
    private const STAGE_LABELS = [
        'starting' => 'Preparing the operation',
        'dump'     => 'Running mysqldump',
        'verify'   => 'Verifying the dump (structure + SHA-256)',
        'register' => 'Registering the backup',
        'rotate'   => 'Applying the retention window',
        'safety'   => 'Creating the pre-restore safety backup',
        'restore'  => 'Streaming the dump into the database',
        'done'     => 'Completed',
        'failed'   => 'Failed',
    ];

    /** Human labels for the operation kinds. */
    private const OPERATION_LABELS = [
        'backup'  => 'Creating a database backup',
        'restore' => 'Restoring the database',
    ];

    private string $directory;

    /** Token of the operation owned by THIS process, null when it owns none. */
    private ?string $token = null;

    /** Timestamp of the most recent write, used for the in-memory throttle. */
    private float $lastWrite = 0.0;

    public function __construct(string $directory)
    {
        $this->directory = rtrim(str_replace(['/', '\\'], DIRECTORY_SEPARATOR, $directory), DIRECTORY_SEPARATOR);
    }

    public function path(): string
    {
        return $this->directory . DIRECTORY_SEPARATOR . self::FILENAME;
    }

    /**
     * Claims the tracker for a new operation.
     *
     * Returns:
     *  - `ok = false` when another live operation (fresh heartbeat, different
     *    process) must not be disturbed — the backup service refuses a restore
     *    in that case;
     *  - `ok = true, tracked = false` when THIS process already owns a live
     *    operation (the nested pre-restore safety backup): the parent operation
     *    keeps the slot and the nested one must not report stages of its own;
     *  - `ok = true, tracked = true` otherwise (the slot was claimed).
     *
     * @param array<string, mixed> $context Extra display context (filename, kind).
     *
     * @return array{ok:bool,tracked:bool,error:?string,state:array<string,mixed>|null}
     */
    public function begin(string $operation, ?int $actorId = null, string $actorName = '', string $origin = 'web', array $context = []): array
    {
        $operation = in_array($operation, ['backup', 'restore'], true) ? $operation : 'backup';

        $existing = $this->current();

        if ($existing !== null && ($existing['running'] ?? false)) {
            // Same process: this is the nested safety backup of a running
            // restore — keep the parent's slot and its heartbeat.
            if ((int) ($existing['pid'] ?? 0) === getmypid()) {
                return ['ok' => true, 'tracked' => false, 'error' => null, 'state' => $existing];
            }

            // Another process is working. A restore must never start, and a
            // backup must never start while a restore is in flight.
            if ($operation === 'restore' || ($existing['operation'] ?? '') === 'restore') {
                $who  = $existing['actor_name'] !== '' ? $existing['actor_name'] : 'another admin';
                $at   = (string) ($existing['started_at'] ?? '');
                $what = self::OPERATION_LABELS[$existing['operation'] ?? ''] ?? 'An operation';
                $then = $operation === 'restore'
                    ? ' Wait for it to finish before starting another restore.'
                    : ' Wait for it to finish before starting a backup.';

                return [
                    'ok'      => false,
                    'tracked' => false,
                    'error'   => $what . ' is already running (started by ' . $who . ($at !== '' ? ' at ' . $at : '') . ').' . $then,
                    'state'   => $existing,
                ];
            }

            // Backup vs backup: harmless — the newest one takes the slot.
        }

        return $this->claim($operation, $actorId, $actorName, $origin, $context);
    }
    /**
     * Writes the initial state and takes ownership of the slot.
     *
     * @param array<string, mixed> $context
     *
     * @return array{ok:bool,tracked:bool,error:?string,state:array<string,mixed>|null}
     */
    private function claim(string $operation, ?int $actorId, string $actorName, string $origin, array $context): array
    {
        $token = bin2hex(random_bytes(8));

        $state = [
            'version'    => 1,
            'token'      => $token,
            'pid'        => getmypid(),
            'operation'  => $operation,
            'stage'      => 'starting',
            'result'     => '',
            'detail'     => '',
            'actor_id'   => $actorId,
            'actor_name' => $this->clean($actorName, 120),
            'origin'     => $this->clean($origin, 40),
            'filename'   => $this->clean((string) ($context['filename'] ?? ''), 191),
            'kind'       => $this->clean((string) ($context['kind'] ?? ''), 20),
            'started_at' => time(),
            'updated_at' => time(),
        ];

        $this->token     = $token;
        $this->lastWrite = microtime(true);

        if (! $this->write($state)) {
            // The tracker is not writable: run untracked rather than fail.
            $this->token = null;

            return ['ok' => true, 'tracked' => false, 'error' => null, 'state' => null];
        }

        return ['ok' => true, 'tracked' => true, 'error' => null, 'state' => $state];
    }

    /**
     * Records the stage the operation has reached. Stage transitions always
     * write; a foreign or finished slot is left untouched.
     *
     * @param array<string, mixed> $extra
     */
    public function stage(string $stage, array $extra = []): void
    {
        $stage = isset(self::STAGE_LABELS[$stage]) ? $stage : 'starting';

        $this->update(true, ['stage' => $stage] + $extra);
    }

    /**
     * Marks the operation as finished (result `ok` or `failed`). Never throws;
     * a killed process simply leaves the slot to go stale.
     *
     * @param array<string, mixed> $extra
     */
    public function finish(string $result, string $detail = '', array $extra = []): void
    {
        $this->update(true, [
            'result' => $result === 'ok' ? 'ok' : 'failed',
            'stage'  => $result === 'ok' ? 'done' : 'failed',
            'detail' => $this->clean($detail, 500),
        ] + $extra);

        $this->token = null;
    }

    /**
     * Keeps the slot fresh while a long process runs. Throttled: at most one
     * write per HEARTBEAT_SECONDS, and only while this process owns the slot.
     */
    public function heartbeat(): void
    {
        if ($this->token === null) {
            return;
        }

        $this->update(false, []);
    }

    /**
     * Current tracker state enriched for display. Null when no operation has
     * ever been recorded, or when the sidecar is unreadable/corrupt.
     *
     * @return array<string, mixed>|null
     */
    public function current(): ?array
    {
        $state = $this->read();

        if ($state === null) {
            return null;
        }

        $now       = time();
        $updatedAt = (int) ($state['updated_at'] ?? 0);
        $finished  = ($state['result'] ?? '') !== '';
        $stale     = ! $finished && ($now - $updatedAt) > self::STALE_SECONDS;
        $operation = (string) ($state['operation'] ?? '');
        $stage     = (string) ($state['stage'] ?? 'starting');

        return [
            'running'         => ! $finished && ! $stale,
            'stale'           => $stale,
            'finished'        => $finished,
            'result'          => (string) ($state['result'] ?? ''),
            'operation'       => $operation,
            'operation_label' => self::OPERATION_LABELS[$operation] ?? 'Database operation',
            'stage'           => $stage,
            'stage_label'     => self::STAGE_LABELS[$stage] ?? $stage,
            'actor_name'      => (string) ($state['actor_name'] ?? ''),
            'origin'          => (string) ($state['origin'] ?? ''),
            'filename'        => (string) ($state['filename'] ?? ''),
            'kind'            => (string) ($state['kind'] ?? ''),
            'detail'          => (string) ($state['detail'] ?? ''),
            'pid'             => (int) ($state['pid'] ?? 0),
            'started_at'      => $this->toDateTime((int) ($state['started_at'] ?? 0)),
            'updated_at'      => $this->toDateTime($updatedAt),
            'age_seconds'     => max(0, $now - (int) ($state['started_at'] ?? $now)),
            'heartbeat_age'   => max(0, $now - $updatedAt),
        ];
    }

    /**
     * True when a fresh, unfinished operation was recorded (by any process).
     */
    public function isBusy(): bool
    {
        $state = $this->current();

        return $state !== null && ($state['running'] ?? false);
    }

    /**
     * True while THIS process owns the slot (so the service knows a nested
     * operation must not report its own stages).
     */
    public function ownedByCurrentProcess(): bool
    {
        return $this->token !== null;
    }

    /**
     * Human label for a stage (used by the view for its checklist).
     */
    public static function stageLabel(string $stage): string
    {
        return self::STAGE_LABELS[$stage] ?? $stage;
    }


    // -------------------------------------------------------------------------
    // Internals
    // -------------------------------------------------------------------------

    /**
     * Updates the sidecar in place when this process owns it.
     *
     * @param array<string, mixed> $changes
     */
    private function update(bool $force, array $changes): void
    {
        if ($this->token === null) {
            return;
        }

        if (! $force && (microtime(true) - $this->lastWrite) < self::HEARTBEAT_SECONDS) {
            return;
        }

        $state = $this->read();

        if ($state === null || (string) ($state['token'] ?? '') !== $this->token || ($state['result'] ?? '') !== '') {
            // Someone else owns the slot now (or the operation already ended):
            // stop reporting rather than clobber their state.
            $this->token = null;

            return;
        }

        foreach ($changes as $key => $value) {
            $state[$key] = $value;
        }

        $state['updated_at'] = time();

        if ($this->write($state)) {
            $this->lastWrite = microtime(true);
        }
    }

    /**
     * @return array<string, mixed>|null
     */
    private function read(): ?array
    {
        try {
            $path = $this->path();

            if (! is_file($path) || ($raw = @file_get_contents($path)) === false || $raw === '') {
                return null;
            }

            $state = json_decode($raw, true);

            if (! is_array($state) || ! isset($state['token'], $state['operation'], $state['started_at'])) {
                return null;
            }

            return $state;
        } catch (Throwable $e) {
            return null;
        }
    }

    /**
     * Atomic write (temp file + rename). Returns false when the directory is not
     * writable — the caller then continues untracked.
     *
     * @param array<string, mixed> $state
     */
    private function write(array $state): bool
    {
        try {
            $target = $this->path();
            $temp   = $target . '.' . bin2hex(random_bytes(4)) . '.tmp';

            $json = json_encode($state, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);

            if ($json === false || @file_put_contents($temp, $json, LOCK_EX) === false) {
                @unlink($temp);

                return false;
            }

            @chmod($temp, 0600);

            if (! @rename($temp, $target)) {
                // Windows may refuse replacing an existing file: retry once.
                @unlink($target);

                if (! @rename($temp, $target)) {
                    @unlink($temp);

                    return false;
                }
            }

            return true;
        } catch (Throwable $e) {
            return false;
        }
    }

    private function toDateTime(int $timestamp): string
    {
        return $timestamp > 0 ? date('Y-m-d H:i:s', $timestamp) : '';
    }

    /** Control characters removed and length capped; the value is display-only. */
    private function clean(string $value, int $maxLength): string
    {
        $value = (string) preg_replace('/[\x00-\x1F\x7F]/u', ' ', trim($value));
        $value = (string) preg_replace('/\s{2,}/u', ' ', $value);

        return trim(mb_substr($value, 0, $maxLength));
    }


}
