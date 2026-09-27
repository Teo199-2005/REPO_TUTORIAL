<?php

/**
 * Standard server-side pagination footer.
 *
 * Server-side (not JavaScript) so that a page of results can be linked to,
 * bookmarked and combined with the filter bar's query string. The injected
 * pager in public/js/admin-table-enhancements.js is deliberately not used for
 * these lists — see the note in that file.
 *
 * @var array $pager
 *   baseUrl    string  base URL; current filters are re-applied
 *   params     array   current filter query (e.g. $filters)
 *   page       int     current page (1-based)
 *   totalPages int     number of pages
 *   total      int     total records across all pages
 *   from       int     first record number on this page
 *   to         int     last record number on this page
 *   label      string  noun for the record type, e.g. "students"
 */

$pager = $pager ?? [];

$baseUrl    = (string) ($pager['baseUrl'] ?? current_url());
$params     = (array) ($pager['params'] ?? []);
$page       = max(1, (int) ($pager['page'] ?? 1));
$totalPages = max(1, (int) ($pager['totalPages'] ?? 1));
$total      = (int) ($pager['total'] ?? 0);
$from       = (int) ($pager['from'] ?? 0);
$to         = (int) ($pager['to'] ?? 0);
$label      = (string) ($pager['label'] ?? 'records');

// Keep the filters in every page link; only `page` changes.
$urlFor = static function (int $target) use ($baseUrl, $params): string {
    $query = array_filter($params, static fn ($v) => $v !== '' && $v !== null);

    return $baseUrl . '?' . http_build_query(array_merge($query, ['page' => $target]));
};

// Window of page numbers around the current page.
$start = max(1, $page - 2);
$end   = min($totalPages, $start + 4);
?>
<?php if ($totalPages > 1): ?>
  <div class="admin-pagination">
    <div>
      Showing <?= (int) $from ?>&ndash;<?= (int) $to ?> of <?= (int) $total ?> <?= esc($label) ?>
    </div>

    <nav aria-label="<?= esc(ucfirst($label)) ?> list pages">
      <ul class="pagination pagination-sm mb-0">
        <li class="page-item <?= $page <= 1 ? 'disabled' : '' ?>">
          <a class="page-link" href="<?= esc($urlFor(max(1, $page - 1))) ?>"
             title="Previous page" rel="prev">
            <i class="bi bi-chevron-left" aria-hidden="true"></i><span class="visually-hidden">Previous</span>
          </a>
        </li>

        <?php for ($p = $start; $p <= $end; $p++): ?>
          <li class="page-item <?= $p === $page ? 'active' : '' ?>">
            <a class="page-link" href="<?= esc($urlFor($p)) ?>"
               <?= $p === $page ? 'aria-current="page"' : '' ?>><?= $p ?></a>
          </li>
        <?php endfor; ?>

        <li class="page-item <?= $page >= $totalPages ? 'disabled' : '' ?>">
          <a class="page-link" href="<?= esc($urlFor(min($totalPages, $page + 1))) ?>"
             title="Next page" rel="next">
            <i class="bi bi-chevron-right" aria-hidden="true"></i><span class="visually-hidden">Next</span>
          </a>
        </li>
      </ul>
    </nav>
  </div>
<?php elseif ($total > 0): ?>
  <div class="admin-pagination">
    <div>Showing all <?= (int) $total ?> <?= esc($label) ?></div>
  </div>
<?php endif; ?>
