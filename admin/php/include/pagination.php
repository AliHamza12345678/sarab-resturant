<?php
/**
 * Pagination + sorting helpers shared across all admin list pages.
 */

/**
 * Read & sanitize the current page number and compute LIMIT/OFFSET.
 */
function paginate_params(int $perPage = 15): array
{
    $page = max(1, intval($_GET['page'] ?? 1));
    $offset = ($page - 1) * $perPage;
    return [$page, $perPage, $offset];
}

/**
 * Validate a requested sort column against a whitelist — this is
 * important: column/direction values are used directly in ORDER BY,
 * which can't be parameterized with a prepared statement placeholder,
 * so a strict whitelist is the injection defense here.
 */
function sort_params(array $allowedColumns, string $defaultColumn): array
{
    $col = $_GET['sort'] ?? $defaultColumn;
    if (!in_array($col, $allowedColumns, true)) {
        $col = $defaultColumn;
    }
    $dir = (isset($_GET['dir']) && strtoupper($_GET['dir']) === 'ASC') ? 'ASC' : 'DESC';
    return [$col, $dir];
}

/**
 * Build a query-string preserving helper: merges $overrides into the
 * current GET params and returns a "?a=1&b=2" string.
 */
function qs(array $overrides = []): string
{
    $params = array_merge($_GET, $overrides);
    foreach ($params as $k => $v) {
        if ($v === null || $v === '') unset($params[$k]);
    }
    return '?' . http_build_query($params);
}

/**
 * Render a sortable <th> — clicking toggles ASC/DESC and shows an arrow
 * for whichever column is currently active.
 */
function sort_header(string $column, string $label, string $currentCol, string $currentDir): string
{
    $nextDir = ($currentCol === $column && $currentDir === 'ASC') ? 'DESC' : 'ASC';
    $arrow = '';
    if ($currentCol === $column) {
        $arrow = $currentDir === 'ASC' ? ' <i class="bi bi-caret-up-fill small"></i>' : ' <i class="bi bi-caret-down-fill small"></i>';
    }
    $url = qs(['sort' => $column, 'dir' => $nextDir, 'page' => 1]);
    return '<a href="' . htmlspecialchars($url) . '" class="text-decoration-none text-reset">' . htmlspecialchars($label) . $arrow . '</a>';
}

/**
 * Render a Bootstrap pagination nav.
 */
function render_pagination(int $page, int $totalPages): string
{
    if ($totalPages <= 1) return '';
    $html = '<nav aria-label="pagination"><ul class="pagination pagination-sm mb-0">';
    $html .= '<li class="page-item ' . ($page <= 1 ? 'disabled' : '') . '"><a class="page-link" href="' . htmlspecialchars(qs(['page' => $page - 1])) . '">Previous</a></li>';

    $start = max(1, $page - 2);
    $end = min($totalPages, $page + 2);
    if ($start > 1) {
        $html .= '<li class="page-item"><a class="page-link" href="' . htmlspecialchars(qs(['page' => 1])) . '">1</a></li>';
        if ($start > 2) $html .= '<li class="page-item disabled"><span class="page-link">&hellip;</span></li>';
    }
    for ($p = $start; $p <= $end; $p++) {
        $html .= '<li class="page-item ' . ($p === $page ? 'active' : '') . '"><a class="page-link" href="' . htmlspecialchars(qs(['page' => $p])) . '">' . $p . '</a></li>';
    }
    if ($end < $totalPages) {
        if ($end < $totalPages - 1) $html .= '<li class="page-item disabled"><span class="page-link">&hellip;</span></li>';
        $html .= '<li class="page-item"><a class="page-link" href="' . htmlspecialchars(qs(['page' => $totalPages])) . '">' . $totalPages . '</a></li>';
    }

    $html .= '<li class="page-item ' . ($page >= $totalPages ? 'disabled' : '') . '"><a class="page-link" href="' . htmlspecialchars(qs(['page' => $page + 1])) . '">Next</a></li>';
    $html .= '</ul></nav>';
    return $html;
}
