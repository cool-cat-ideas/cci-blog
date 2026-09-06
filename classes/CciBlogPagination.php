<?php
declare(strict_types=1);

if (!defined('_PS_VERSION_')) {
    exit;
}

final class CciBlogPagination
{
    public static function build(
        int $page,
        int $perPage,
        int $total,
        string $baseUrl,
        array $query = [],
        ?string $pageUrlPattern = null
    ): array {
        $perPage = max(1, $perPage);
        $total = max(0, $total);
        $totalPages = max(1, (int) ceil($total / $perPage));
        $currentPage = min(max(1, $page), $totalPages);

        return [
            'items_shown_from' => $total > 0 ? (($currentPage - 1) * $perPage) + 1 : 0,
            'items_shown_to' => $total > 0 ? min($total, $currentPage * $perPage) : 0,
            'total_items' => $total,
            'should_be_displayed' => $totalPages > 1,
            'pages' => self::buildPages($currentPage, $totalPages, $baseUrl, $query, $pageUrlPattern),
        ];
    }

    private static function buildPages(
        int $currentPage,
        int $totalPages,
        string $baseUrl,
        array $query,
        ?string $pageUrlPattern
    ): array {
        $pages = [
            [
                'type' => 'previous',
                'page' => max(1, $currentPage - 1),
                'url' => self::buildUrl($baseUrl, max(1, $currentPage - 1), $query, $pageUrlPattern),
                'clickable' => $currentPage > 1,
                'current' => false,
            ],
        ];

        $visiblePages = self::visiblePages($currentPage, $totalPages);
        $previousVisiblePage = 0;

        foreach ($visiblePages as $visiblePage) {
            if ($previousVisiblePage > 0 && $visiblePage > $previousVisiblePage + 1) {
                $pages[] = [
                    'type' => 'spacer',
                    'page' => null,
                    'url' => '',
                    'clickable' => false,
                    'current' => false,
                ];
            }

            $pages[] = [
                'type' => 'page',
                'page' => $visiblePage,
                'url' => self::buildUrl($baseUrl, $visiblePage, $query, $pageUrlPattern),
                'clickable' => $visiblePage !== $currentPage,
                'current' => $visiblePage === $currentPage,
            ];

            $previousVisiblePage = $visiblePage;
        }

        $pages[] = [
            'type' => 'next',
            'page' => min($totalPages, $currentPage + 1),
            'url' => self::buildUrl($baseUrl, min($totalPages, $currentPage + 1), $query, $pageUrlPattern),
            'clickable' => $currentPage < $totalPages,
            'current' => false,
        ];

        return $pages;
    }

    private static function visiblePages(int $currentPage, int $totalPages): array
    {
        if ($totalPages <= 7) {
            return range(1, $totalPages);
        }

        $pages = [1, $totalPages];
        for ($page = $currentPage - 2; $page <= $currentPage + 2; $page++) {
            if ($page > 1 && $page < $totalPages) {
                $pages[] = $page;
            }
        }

        $pages = array_values(array_unique($pages));
        sort($pages);

        return $pages;
    }

    private static function buildUrl(
        string $baseUrl,
        int $page,
        array $query,
        ?string $pageUrlPattern
    ): string {
        $params = self::normalizeQuery($query);
        if ($page > 1 && $pageUrlPattern !== null && $pageUrlPattern !== '') {
            unset($params['page']);

            return self::appendQuery(
                str_replace('{page}', (string) $page, $pageUrlPattern),
                $params
            );
        }

        if ($page > 1) {
            $params['page'] = $page;
        } else {
            unset($params['page']);
        }

        return self::appendQuery($baseUrl, $params);
    }

    private static function appendQuery(string $baseUrl, array $params): string
    {
        if (!$params) {
            return $baseUrl;
        }

        $fragment = '';
        $fragmentPosition = strpos($baseUrl, '#');
        if ($fragmentPosition !== false) {
            $fragment = substr($baseUrl, $fragmentPosition);
            $baseUrl = substr($baseUrl, 0, $fragmentPosition);
        }

        $separator = strpos($baseUrl, '?') === false ? '?' : '&';

        return $baseUrl . $separator . http_build_query($params) . $fragment;
    }

    private static function normalizeQuery(array $query): array
    {
        $params = [];
        foreach ($query as $key => $value) {
            if ($value === null || $value === '') {
                continue;
            }

            $params[(string) $key] = $value;
        }

        return $params;
    }
}
