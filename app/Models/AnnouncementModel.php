<?php
namespace App\Models;

use CodeIgniter\Database\Exceptions\DatabaseException;
use CodeIgniter\Model;

class AnnouncementModel extends Model
{
    protected $table            = 'announcements';
    protected $primaryKey       = 'id';
    protected $returnType       = 'array';
    protected $useSoftDeletes   = true;

    protected $allowedFields    = [
        'title',
        'slug',
        'body',
        'target_roles', // comma-separated: admin,teacher,student,parent or 'all'
        'published_at',
        'created_by',
    ];

    protected $useTimestamps    = true;
    protected $createdField     = 'created_at';
    protected $updatedField     = 'updated_at';
    protected $deletedField     = 'deleted_at';

    protected $validationRules      = [
        'title' => 'required|min_length[3]|max_length[255]',
        'slug'  => 'required|min_length[3]|max_length[255]',
        'body'  => 'required',
    ];
    protected $validationMessages   = [];
    protected $skipValidation       = false;

    /**
     * Build a slug that is unique across EVERY physical row of the table.
     *
     * The `announcements.slug` column carries a plain UNIQUE index, so a
     * soft-deleted (trashed) row still occupies its slug even though the model
     * hides it from normal queries. Checking uniqueness with the default,
     * soft-delete-filtered model query made PHP believe a slug was free while
     * MySQL rejected the insert with
     * "Duplicate entry 'dasd-1' for key 'slug'".
     *
     * Looking up the raw table (no `deleted_at IS NULL` filter) keeps the PHP
     * check and the database index in agreement.
     *
     * @param string   $source    Title (or slug) used as the slug base
     * @param int|null $excludeId Row to ignore, e.g. the record being updated
     */
    public function generateUniqueSlug(?string $source, ?int $excludeId = null): string
    {
        $base = trim(url_title((string) $source, '-', true), '-');
        if ($base === '') {
            $base = 'announcement';
        }

        // Leave room for the "-<counter>" suffix inside the 255 char column.
        if (mb_strlen($base) > 200) {
            $base = mb_substr($base, 0, 200);
        }

        $taken = $this->slugsWithPrefix($base, $excludeId);

        $candidate = $base;
        $counter   = 1;
        while (in_array($candidate, $taken, true)) {
            $candidate = $base . '-' . $counter;
            $counter++;
        }

        return $candidate;
    }

    /**
     * Save an announcement, generating a slug that is unique against the
     * physical table.
     *
     * The retry loop covers the (small) race where another request inserts the
     * same slug between this request's uniqueness check and its INSERT; the
     * UNIQUE index would otherwise surface as an unhandled DatabaseException.
     *
     * @param array<string, mixed> $data          Row data (must contain 'title')
     * @param int|null             $updateId      Set to update an existing row
     * @param int                  $maxAttempts
     *
     * @return bool True on success, false when model validation fails
     *
     * @throws DatabaseException When the row still cannot be written
     */
    public function saveWithUniqueSlug(array $data, ?int $updateId = null, int $maxAttempts = 3): bool
    {
        $source = (string) ($data['title'] ?? ($data['slug'] ?? ''));

        for ($attempt = 1; $attempt <= $maxAttempts; $attempt++) {
            $data['slug'] = $this->generateUniqueSlug($source, $updateId);

            try {
                if ($updateId === null) {
                    return $this->insert($data) !== false;
                }

                return $this->update($updateId, $data) !== false;
            } catch (DatabaseException $e) {
                log_message(
                    'warning',
                    'Announcement slug "' . $data['slug'] . '" collided on attempt ' . $attempt . ': ' . $e->getMessage()
                );

                if ($attempt === $maxAttempts) {
                    throw $e;
                }
            }
        }

        return false;
    }

    /**
     * Slugs of all rows (soft-deleted ones included) that start with $base.
     *
     * Uses the query builder directly so the model's soft-delete filter cannot
     * hide trashed rows from the uniqueness check.
     *
     * @return list<string>
     */
    private function slugsWithPrefix(string $base, ?int $excludeId = null): array
    {
        $builder = $this->db->table($this->table)
            ->select('id, slug')
            ->like('slug', $base, 'after'); // LIKE 'base%' (wildcards escaped)

        if ($excludeId !== null) {
            $builder->where('id !=', $excludeId);
        }

        $slugs = [];
        foreach ($builder->get()->getResultArray() as $row) {
            $slugs[] = (string) ($row['slug'] ?? '');
        }

        return $slugs;
    }
}

