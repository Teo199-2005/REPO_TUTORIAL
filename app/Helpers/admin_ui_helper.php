<?php

declare(strict_types=1);

/**
 * Helpers for the shared admin UI standard (public/css/admin-ui.css and the
 * views/admin/partials/* components).
 *
 * These are presentation-only helpers: they shape data for the view layer and
 * never touch the database, so they are safe to call from any admin view.
 */

if (! function_exists('admin_filter_option')) {
    /**
     * One <option> for a filter field.
     *
     * @param string $value Stored value, written to the query string.
     * @param string $label Text shown to the administrator.
     */
    function admin_filter_option(string $value, string $label): array
    {
        return ['value' => $value, 'label' => $label];
    }
}

if (! function_exists('admin_filter_count_active')) {
    /**
     * Count how many of a page's filters are currently narrowing the list.
     *
     * A filter counts as active when its value is neither empty nor one of the
     * "no filter" sentinels a page uses — most commonly 'all'.
     *
     * @param array<string, mixed> $values Current filter values, keyed by field name.
     * @param list<string>          $names  Restrict the count to these field names.
     * @param list<string>          $ignore Values that mean "not filtering".
     *
     * @return array{total:int,names:list<string>}
     */
    function admin_filter_count_active(array $values, array $names, array $ignore = ['', 'all']): array
    {
        $active = [];

        foreach ($names as $name) {
            $raw = $values[$name] ?? null;

            if (is_array($raw)) {
                continue;
            }

            $value = strtolower(trim((string) $raw));

            if ($value === '' || in_array($value, $ignore, true)) {
                continue;
            }

            $active[] = (string) $name;
        }

        return ['total' => count($active), 'names' => $active];
    }
}

if (! function_exists('admin_filter_value')) {
    /**
     * Read one filter value as a string, tolerating a missing key.
     *
     * @param array<string, mixed> $values
     */
    function admin_filter_value(array $values, string $name, string $default = ''): string
    {
        $raw = $values[$name] ?? $default;

        return is_array($raw) ? $default : (string) $raw;
    }
}

if (! function_exists('admin_filter_query')) {
    /**
     * The current filter values as a query-string array, for pagination links.
     *
     * Values that are empty, or that mean "not filtering" (the 'all' sentinel
     * used by dropdowns), are dropped — otherwise every page link would carry
     * noise such as `?status=all`.
     *
     * @param array<string, mixed> $values
     * @param list<string>          $keep   Only these keys are carried over.
     * @param list<string>          $ignore Values that mean "not filtering".
     *
     * @return array<string, mixed>
     */
    function admin_filter_query(array $values, array $keep, array $ignore = ['', 'all']): array
    {
        $query = [];

        foreach ($keep as $key) {
            $raw = $values[$key] ?? null;

            if ($raw === null || is_array($raw)) {
                continue;
            }

            $value = trim((string) $raw);

            if ($value === '' || in_array(strtolower($value), $ignore, true)) {
                continue;
            }

            $query[$key] = $value;
        }

        return $query;
    }
}

if (! function_exists('admin_page_icon_class')) {
    /**
     * Wrap a page key in the Bootstrap Icons class the sidebar expects.
     *
     * The canonical per-page icon lives in admin_page_icon(); this keeps view
     * files from having to remember the "bi " prefix.
     */
    function admin_page_icon_class(string $pageKey): string
    {
        helper('admin_access');

        $icon = admin_page_icon($pageKey);

        return str_starts_with($icon, 'bi-') ? $icon : 'bi-' . $icon;
    }
}

if (! function_exists('admin_row_action')) {
    /**
     * One icon-only row-action button, using the shared icon vocabulary.
     *
     * @param array{url:string,icon:string,label:string,variant?:string,attrs?:string} $action
     */
    function admin_row_action(array $action): string
    {
        $url     = (string) ($action['url'] ?? '#');
        $icon    = (string) ($action['icon'] ?? 'bi-three-dots');
        $label   = (string) ($action['label'] ?? 'Action');
        $variant = (string) ($action['variant'] ?? 'outline-secondary');
        $attrs   = (string) ($action['attrs'] ?? '');

        return '<a class="btn btn-sm btn-' . esc($variant) . '" href="' . esc($url) . '"'
            . ' title="' . esc($label) . '" aria-label="' . esc($label) . '"'
            . ($attrs !== '' ? ' ' . $attrs : '') . '>'
            . '<i class="bi ' . esc($icon) . '" aria-hidden="true"></i>'
            . '<span class="visually-hidden">' . esc($label) . '</span></a>';
    }
}
