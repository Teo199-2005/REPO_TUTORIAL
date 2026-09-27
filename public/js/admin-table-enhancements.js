(function () {
  'use strict';

  function inAdminArea() {
    var path = (window.location.pathname || '').toLowerCase();
    return path.indexOf('/admin') !== -1;
  }

  function getCellText(row, colIdx) {
    var cell = row.children[colIdx];
    if (!cell) return '';
    return (cell.textContent || '').trim().toLowerCase();
  }

  function enhanceTable(table, idx) {
    if (table.dataset.tableEnhanced === '1') return;
    if (table.dataset.noEnhance === '1') return;
    // Small info / dashboard tables (admin staff list, recent lists):
    // never inject checkboxes / pagination toolbar into them.
    if (table.rows.length <= 12) return;
    if (table.querySelector('[colspan]')) return;

    // Paging policy
    // -------------
    // The admin standard is SERVER-side pagination: a page of results has to be
    // linkable, bookmarkable and combinable with the filter bar's query string.
    // A table opts IN to the injected pager with data-js-paged="1", and is
    // otherwise only given checkboxes and column sorting — never a second,
    // competing pager. Anything else is left alone rather than guessing.
    var wantsJsPager = table.dataset.jsPaged === '1';

    var thead = table.tHead;
    var tbody = table.tBodies && table.tBodies[0];
    if (!thead || !tbody || !thead.rows[0]) return;

    var headerRow = thead.rows[0];
    // Table already has its own checkbox column (pending approvals):
    // do NOT inject a second auto checkbox, just add sort/pagination.
    var hasOwnCheckbox = !!headerRow.querySelector('input[type="checkbox"]');

    var rows = Array.from(tbody.rows);
    if (rows.length === 0) return;

    table.classList.add('admin-table-enhanced');
    table.dataset.tableEnhanced = '1';

    rows.forEach(function (row, i) {
      row.dataset.originalIndex = String(i);
    });

    if (!hasOwnCheckbox) {
      var selectTh = document.createElement('th');
      selectTh.className = 'table-select-col';
      selectTh.innerHTML = '<input type="checkbox" class="table-select-all" aria-label="Select all rows">';
      headerRow.insertBefore(selectTh, headerRow.firstChild);

      rows.forEach(function (row) {
        var cell = document.createElement('td');
        cell.className = 'table-select-col';
        cell.innerHTML = '<input type="checkbox" class="table-select-row" aria-label="Select row">';
        row.insertBefore(cell, row.firstChild);
      });
    }

    var checkboxColIndex = hasOwnCheckbox ? 0 : 1;
    var skipTitles = new Set(['actions', 'action', '']);
    var sortableHeaders = [];
    Array.from(headerRow.cells).forEach(function (th, colIndex) {
      if (!hasOwnCheckbox && colIndex === 0) return;
      if (hasOwnCheckbox && colIndex === checkboxColIndex) return;
      var title = (th.textContent || '').trim().toLowerCase();
      if (skipTitles.has(title)) return;
      th.classList.add('sortable');
      th.dataset.sortDir = 'none';
      th.insertAdjacentHTML('beforeend', '<span class="sort-indicator">↕</span>');
      sortableHeaders.push(th);
    });

    // Client-side paging is opt-in only. Tables that already render a
    // server-side pager keep their checkboxes and column sorting but are NOT
    // given a second, competing pager. See the policy note above.
    if (!wantsJsPager) return;

    var pageContainer = document.querySelector('.dashboard-page-container');
    if (!pageContainer) return;

    var toolbar = document.createElement('div');
    toolbar.className = 'admin-table-toolbar';
    toolbar.innerHTML =
      '<div class="admin-table-count" id="adminTableCount' + idx + '"></div>' +
      '<div class="d-flex align-items-center gap-2">' +
        '<label class="small text-muted mb-0" for="adminTableSize' + idx + '">Rows</label>' +
        '<select class="form-select form-select-sm admin-table-size" id="adminTableSize' + idx + '">' +
          '<option value="10">10</option>' +
          '<option value="25" selected>25</option>' +
          '<option value="50">50</option>' +
          '<option value="100">100</option>' +
        '</select>' +
      '</div>';
    pageContainer.appendChild(toolbar);

    var pager = document.createElement('div');
    pager.className = 'admin-table-pagination';
    pageContainer.appendChild(pager);

    var pageSizeEl = toolbar.querySelector('.admin-table-size');
    var countEl = toolbar.querySelector('.admin-table-count');
    var currentPage = 1;
    var pageSize = parseInt(pageSizeEl.value, 10);
    var currentRows = rows.slice();

    function updateSelectAllState() {
      if (hasOwnCheckbox) return;
      var visibleRows = currentRows.slice((currentPage - 1) * pageSize, currentPage * pageSize);
      var checkboxes = visibleRows.map(function (r) { return r.querySelector('.table-select-row'); }).filter(Boolean);
      var allChecked = checkboxes.length > 0 && checkboxes.every(function (c) { return c.checked; });
      var selectAll = table.querySelector('.table-select-all');
      if (selectAll) selectAll.checked = allChecked;
    }

    function renderPager(totalPages) {
      pager.innerHTML = '';
      if (totalPages <= 1) return;

      function btn(label, target, active, disabled) {
        var b = document.createElement('button');
        b.type = 'button';
        b.className = 'page-btn' + (active ? ' active' : '');
        b.textContent = label;
        if (disabled) b.disabled = true;
        b.addEventListener('click', function () {
          currentPage = target;
          render();
        });
        pager.appendChild(b);
      }

      btn('Prev', Math.max(1, currentPage - 1), false, currentPage === 1);
      for (var p = 1; p <= totalPages; p++) {
        if (p === 1 || p === totalPages || Math.abs(p - currentPage) <= 1) {
          btn(String(p), p, p === currentPage, false);
        }
      }
      btn('Next', Math.min(totalPages, currentPage + 1), false, currentPage === totalPages);
    }

    function render() {
      rows.forEach(function (r) { r.style.display = 'none'; });

      var total = currentRows.length;
      var totalPages = Math.max(1, Math.ceil(total / pageSize));
      if (currentPage > totalPages) currentPage = totalPages;
      var start = (currentPage - 1) * pageSize;
      var end = Math.min(total, start + pageSize);

      currentRows.slice(start, end).forEach(function (r) {
        r.style.display = '';
      });

      if (countEl) {
        countEl.textContent = 'Showing ' + (total === 0 ? 0 : (start + 1)) + '-' + end + ' of ' + total + ' rows';
      }
      renderPager(totalPages);
      updateSelectAllState();
    }

    sortableHeaders.forEach(function (th) {
      th.addEventListener('click', function () {
        var colIndex = Array.from(headerRow.cells).indexOf(th);
        var dir = th.dataset.sortDir === 'asc' ? 'desc' : 'asc';

        sortableHeaders.forEach(function (h) {
          h.dataset.sortDir = 'none';
          var si = h.querySelector('.sort-indicator');
          if (si) si.textContent = '↕';
        });
        th.dataset.sortDir = dir;
        var ind = th.querySelector('.sort-indicator');
        if (ind) ind.textContent = dir === 'asc' ? '↑' : '↓';

        currentRows.sort(function (a, b) {
          var at = getCellText(a, colIndex);
          var bt = getCellText(b, colIndex);
          var an = parseFloat(at.replace(/[^0-9.-]/g, ''));
          var bn = parseFloat(bt.replace(/[^0-9.-]/g, ''));
          var bothNum = !isNaN(an) && !isNaN(bn);
          var result = bothNum ? (an - bn) : at.localeCompare(bt);
          return dir === 'asc' ? result : -result;
        });

        currentPage = 1;
        render();
      });
    });

    if (!hasOwnCheckbox) {
      table.querySelector('.table-select-all').addEventListener('change', function (e) {
        var visibleRows = currentRows.slice((currentPage - 1) * pageSize, currentPage * pageSize);
        visibleRows.forEach(function (r) {
          var cb = r.querySelector('.table-select-row');
          if (cb) cb.checked = e.target.checked;
        });
      });

      tbody.addEventListener('change', function (e) {
        if (e.target && e.target.classList.contains('table-select-row')) {
          updateSelectAllState();
        }
      });
    }

    pageSizeEl.addEventListener('change', function () {
      pageSize = parseInt(pageSizeEl.value, 10) || 25;
      currentPage = 1;
      render();
    });

    render();
  }

  document.addEventListener('DOMContentLoaded', function () {
    if (!inAdminArea()) return;
    var tables = Array.from(document.querySelectorAll('.dashboard-page-container table'));
    tables.forEach(function (table, i) {
      if (table.dataset.tableEnhanced === '1') return;
      if (table.dataset.noEnhance === '1') return;
      enhanceTable(table, i + 1);
    });
  });
})();

