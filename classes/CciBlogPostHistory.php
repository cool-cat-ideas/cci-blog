<?php

declare(strict_types=1);

if (!defined('_PS_VERSION_')) {
    exit;
}

/** Immutable editorial snapshots, scoped to an article, shop and content language. */
final class CciBlogPostHistory
{
    private static bool $schemaReady = false;

    private const FIELDS = [
        'title', 'slug', 'intro', 'content', 'blocks', 'id_author', 'id_category', 'category_ids',
        'active', 'featured', 'allow_comments', 'cover_image', 'og_image', 'date_published',
        'meta_title', 'meta_description', 'meta_keywords', 'focus_keyword', 'seo_content_type',
        'og_title', 'og_description', 'tags',
    ];

    public static function ensureSchema(): void
    {
        if (self::$schemaReady) {
            return;
        }
        $db = Db::getInstance();
        $table = _DB_PREFIX_ . 'cci_blog_post_revision';
        $tables = $db->executeS('SHOW TABLES LIKE "' . $table . '"') ?: [];
        $exists = array_filter($tables, static fn (array $row): bool => in_array($table, $row, true));
        if (!$exists) {
            $sql = (string) file_get_contents(dirname(__DIR__) . '/sql/install.sql');
            if (!preg_match('/CREATE TABLE IF NOT EXISTS `PREFIX_cci_blog_post_revision`.*?;/s', $sql, $match)
                || !$db->execute(str_replace('PREFIX_', _DB_PREFIX_, $match[0]))) {
                throw new RuntimeException('Could not create the article version history.');
            }
        }
        self::$schemaReady = true;
    }

    public static function snapshot(array $post): array
    {
        $snapshot = [];
        foreach (self::FIELDS as $field) {
            $value = $post[$field] ?? '';
            if (in_array($field, ['id_author', 'id_category', 'active', 'featured', 'allow_comments'], true)) {
                $value = (int) $value;
            } elseif ($field === 'category_ids') {
                $value = array_values(array_unique(array_map('intval', is_array($value) ? $value : [])));
            } elseif ($field === 'blocks') {
                $value = is_array($value) ? $value : [];
            } else {
                $value = (string) $value;
            }
            $snapshot[$field] = $value;
        }
        return $snapshot;
    }

    /** Called in the same database transaction as the successful article save. */
    public static function record(?array $previous, array $saved, int $shopId, int $langId, Employee $employee): int
    {
        $postId = (int) $saved['id_post'];
        $scope = self::scope($postId, $shopId, $langId);
        $db = Db::getInstance();
        $before = $previous === null ? null : self::snapshot($previous);
        if ($before !== null && !$db->getValue('SELECT id_revision FROM `' . _DB_PREFIX_ . 'cci_blog_post_revision` WHERE ' . $scope . ' ORDER BY id_revision DESC')) {
            self::insert($postId, $shopId, $langId, 0, '', 'baseline', [], $before);
        }
        $after = self::snapshot($saved);
        $changed = [];
        foreach ($after as $field => $value) {
            // Blocks are the source of truth; generated storefront markup is not an editorial change.
            if ($field === 'content' && !empty($after['blocks'])) {
                continue;
            }
            if ($before === null || $before[$field] !== $value) {
                $changed[] = $field;
            }
        }
        return self::insert($postId, $shopId, $langId, (int) $employee->id,
            trim((string) $employee->firstname . ' ' . (string) $employee->lastname),
            'save', $changed, $after);
    }

    public static function page(int $postId, int $shopId, int $langId, int $beforeId = 0): array
    {
        $where = self::scope($postId, $shopId, $langId);
        if ($beforeId > 0) {
            $where .= ' AND id_revision < ' . $beforeId;
        }
        $rows = Db::getInstance()->executeS('SELECT id_revision, id_employee, employee_name, event_type, changed_fields, date_add'
            . ' FROM `' . _DB_PREFIX_ . 'cci_blog_post_revision` WHERE ' . $where . ' ORDER BY id_revision DESC LIMIT 21') ?: [];
        $more = count($rows) > 20;
        $rows = array_slice($rows, 0, 20);
        foreach ($rows as &$row) {
            $row['changed_fields'] = json_decode((string) $row['changed_fields'], true) ?: [];
        }
        unset($row);
        return ['revisions' => $rows, 'next_before_id' => $more ? (int) end($rows)['id_revision'] : null];
    }

    public static function revision(int $revisionId, int $postId, int $shopId, int $langId): ?array
    {
        $row = Db::getInstance()->getRow('SELECT id_revision, id_employee, employee_name, event_type, changed_fields, snapshot, date_add'
            . ' FROM `' . _DB_PREFIX_ . 'cci_blog_post_revision` WHERE id_revision = ' . $revisionId
            . ' AND ' . self::scope($postId, $shopId, $langId));
        if (!$row) {
            return null;
        }
        $row['snapshot'] = json_decode((string) $row['snapshot'], true, 512, JSON_THROW_ON_ERROR);
        $row['changed_fields'] = json_decode((string) $row['changed_fields'], true) ?: [];
        return $row;
    }

    private static function scope(int $postId, int $shopId, int $langId): string
    {
        if ($postId <= 0 || $shopId <= 0 || $langId <= 0) {
            throw new InvalidArgumentException('Invalid article history scope.');
        }
        return 'id_post = ' . $postId . ' AND id_shop = ' . $shopId . ' AND id_lang = ' . $langId;
    }

    private static function insert(int $postId, int $shopId, int $langId, int $employeeId, string $name, string $event, array $changed, array $snapshot): int
    {
        $db = Db::getInstance();
        if (!$db->insert('cci_blog_post_revision', [
            'id_post' => $postId, 'id_shop' => $shopId, 'id_lang' => $langId,
            'id_employee' => $employeeId, 'employee_name' => pSQL($name), 'event_type' => $event,
            'changed_fields' => pSQL(json_encode($changed, JSON_THROW_ON_ERROR)),
            'snapshot' => pSQL(json_encode($snapshot, JSON_THROW_ON_ERROR | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES), true),
            'date_add' => (new DateTimeImmutable('now', new DateTimeZone((string) (Configuration::get('PS_TIMEZONE') ?: date_default_timezone_get()))))->format('Y-m-d H:i:s'),
        ])) {
            throw new RuntimeException('Could not save the article version.');
        }
        return (int) $db->Insert_ID();
    }
}
