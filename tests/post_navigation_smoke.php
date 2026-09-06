<?php
declare(strict_types=1);

define('_PS_VERSION_', '9.1.5');
define('_DB_PREFIX_', 'ps_');

function pSQL(string $value): string
{
    return addslashes($value);
}

class ObjectModel
{
    public const TYPE_INT = 1;
    public const TYPE_BOOL = 2;
    public const TYPE_STRING = 3;
    public const TYPE_DATE = 4;
    public const TYPE_HTML = 5;
}

class Validate
{
    public static function isDateFormat(string $value): bool
    {
        return (bool) preg_match('/^\d{4}-\d{2}-\d{2} \d{2}:\d{2}:\d{2}$/', $value);
    }
}

class Db
{
    private static ?self $instance = null;
    public array $queries = [];

    public static function getInstance(): self
    {
        return self::$instance ??= new self();
    }

    public function getRow(string $sql): array
    {
        $this->queries[] = $sql;

        if (str_contains($sql, 'ORDER BY COALESCE(p.date_published, p.date_add) DESC')) {
            return ['id_post' => 8, 'title' => 'Older post', 'slug' => 'older-post', 'cover_image' => ''];
        }

        return ['id_post' => 12, 'title' => 'Newer post', 'slug' => 'newer-post', 'cover_image' => ''];
    }
}

require_once __DIR__ . '/../classes/CciBlogPost.php';

function assertPostNavigation(bool $condition, string $message): void
{
    if (!$condition) {
        fwrite(STDERR, "FAIL: {$message}\n");
        exit(1);
    }
}

$adjacent = CciBlogPost::getAdjacentPosts(10, '2026-07-14 12:00:00', 2, 3);

assertPostNavigation($adjacent['previous']['id_post'] === 8, 'The nearest older post should be returned as previous.');
assertPostNavigation($adjacent['next']['id_post'] === 12, 'The nearest newer post should be returned as next.');
assertPostNavigation(count(Db::getInstance()->queries) === 2, 'Navigation should execute one bounded query per direction.');

foreach (Db::getInstance()->queries as $query) {
    assertPostNavigation(str_contains($query, 'pl.id_lang = 2'), 'The query must be scoped to the current language.');
    assertPostNavigation(str_contains($query, 'pl.id_shop = 3'), 'The language row must be scoped to the current shop.');
    assertPostNavigation(str_contains($query, 'ps.id_shop = 3'), 'The post must be assigned directly to the current shop.');
    assertPostNavigation(str_contains($query, 'p.active = 1'), 'Only active posts may appear in navigation.');
}

$template = (string) file_get_contents(__DIR__ . '/../views/templates/front/_post_navigation.tpl');
assertPostNavigation(str_contains($template, 'rel="prev"'), 'The previous link should expose rel="prev".');
assertPostNavigation(str_contains($template, 'rel="next"'), 'The next link should expose rel="next".');
assertPostNavigation(str_contains($template, "s='Previous post'"), 'The previous label must use module translations.');
assertPostNavigation(str_contains($template, "s='Next post'"), 'The next label must use module translations.');

echo "OK: post navigation smoke test passed.\n";
