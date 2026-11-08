<?php

declare(strict_types=1);

if (!defined('_PS_VERSION_')) {
    exit;
}

/** Compact, per-shop/per-language publication results. Reading never evaluates rules. */
final class CciBlogPublicationAuditStorage
{
    private static bool $schemaReady = false;

    public static function ensureSchema(): void
    {
        if (self::$schemaReady) {
            return;
        }
        $db = Db::getInstance();
        foreach (['post', 'category'] as $entity) {
            $table = _DB_PREFIX_ . 'cci_blog_' . $entity . '_lang';
            $columns = array_column($db->executeS('SHOW COLUMNS FROM `' . $table . '`') ?: [], null, 'Field');
            if (!isset($columns['publication_audit_summary'])) {
                $db->execute('ALTER TABLE `' . $table . '` ADD `publication_audit_summary` TEXT NULL');
                // Scores created before this contract cannot prove a saved check.
                if (isset($columns['seo_score'])) {
                    $db->execute('ALTER TABLE `' . $table . '` MODIFY `seo_score` TINYINT UNSIGNED NULL DEFAULT NULL');
                    $db->execute('UPDATE `' . $table . '` SET seo_score=NULL');
                    $columns['seo_score']['Null'] = 'YES';
                }
            }
            if (!isset($columns['seo_score'])) {
                $db->execute('ALTER TABLE `' . $table . '` ADD `seo_score` TINYINT UNSIGNED NULL DEFAULT NULL');
            } elseif ($columns['seo_score']['Null'] !== 'YES') {
                $db->execute('ALTER TABLE `' . $table . '` MODIFY `seo_score` TINYINT UNSIGNED NULL DEFAULT NULL');
            }
        }
        self::$schemaReady = true;
    }

    public static function revision(int $shopId): string
    {
        return (string) (Configuration::get('CCB_PUBLICATION_DATA_REVISION', null, null, $shopId) ?: '0');
    }

    /** Conservative invalidation: retain the last result, never recalculate on a hook. */
    public static function invalidate(?int $shopId = null): void
    {
        $shops = $shopId === null ? Shop::getShops(false, null, true) : [$shopId];
        $revision = bin2hex(random_bytes(12));
        foreach ($shops as $id) {
            Configuration::updateValue('CCB_PUBLICATION_DATA_REVISION', $revision, false, null, (int) $id);
        }
    }

    public static function pack(array $audit): array
    {
        return [
            'score' => $audit['score'], 'overallStatus' => $audit['overallStatus'], 'complete' => $audit['complete'],
            'version' => $audit['version'], 'rulesFingerprint' => $audit['rulesFingerprint'],
            'contextFingerprint' => (string) ($audit['contextFingerprint'] ?? ''),
            'dataRevision' => (string) ($audit['dataRevision'] ?? ''),
            'validUntil' => $audit['validUntil'] ?? null, 'checkedAt' => gmdate('Y-m-d\TH:i:s\Z'),
        ];
    }

    public static function read($encoded, $storedScore, string $rulesFingerprint, string $contextFingerprint, string $revision, ?int $now = null): array
    {
        $empty = ['score' => null, 'overallStatus' => 'info', 'complete' => false, 'stale' => false, 'notChecked' => true, 'checkedAt' => null];
        $summary = is_string($encoded) ? json_decode($encoded, true) : null;
        if (!is_array($summary) || !array_key_exists('score', $summary)
            || !is_bool($summary['complete'] ?? null) || !is_string($summary['checkedAt'] ?? null)
            || strtotime($summary['checkedAt']) === false
            || !in_array($summary['overallStatus'] ?? '', ['good', 'warning', 'bad', 'info'], true)
            || ($summary['score'] !== null && (!is_int($summary['score']) || $summary['score'] < 0 || $summary['score'] > 100))
            || ($summary['score'] !== null && !$summary['complete'])
            || (isset($summary['validUntil']) && !is_int($summary['validUntil']))
            || (($storedScore === null ? null : (int) $storedScore) !== $summary['score'])) {
            return $empty;
        }
        foreach (['version', 'rulesFingerprint', 'contextFingerprint', 'dataRevision'] as $field) {
            if (!is_string($summary[$field] ?? null) || $summary[$field] === '') {
                return $empty;
            }
        }
        $summary = array_intersect_key($summary, array_flip(['score', 'overallStatus', 'complete', 'version', 'rulesFingerprint', 'contextFingerprint', 'dataRevision', 'validUntil', 'checkedAt']));
        $summary['notChecked'] = false;
        $summary['stale'] = ($summary['version'] ?? '') !== CciBlogPublicationAudit::VERSION
            || ($summary['rulesFingerprint'] ?? '') !== $rulesFingerprint
            || ($summary['contextFingerprint'] ?? '') !== $contextFingerprint
            || ($summary['dataRevision'] ?? '') !== $revision
            || (!empty($summary['validUntil']) && ($now ?? time()) >= (int) $summary['validUntil']);

        return $summary;
    }

    public static function store(string $entity, int $id, int $shopId, int $langId, array $audit): array
    {
        if (!in_array($entity, ['post', 'category'], true) || $id <= 0 || $shopId <= 0 || $langId <= 0) {
            throw new InvalidArgumentException('Invalid publication snapshot scope.');
        }
        self::ensureSchema();
        if (!Db::getInstance()->getValue('SELECT id_' . $entity . ' FROM `' . _DB_PREFIX_ . 'cci_blog_' . $entity . '_lang` WHERE id_' . $entity . '=' . $id . ' AND id_shop=' . $shopId . ' AND id_lang=' . $langId)) {
            throw new RuntimeException('The selected language row does not exist.');
        }
        $summary = self::pack($audit);
        if (!Db::getInstance()->update('cci_blog_' . $entity . '_lang', [
            'seo_score' => $summary['score'],
            'publication_audit_summary' => pSQL(json_encode($summary, JSON_THROW_ON_ERROR)),
        ], 'id_' . $entity . '=' . $id . ' AND id_shop=' . $shopId . ' AND id_lang=' . $langId, 0, true)) {
            throw new RuntimeException('The publication result could not be stored.');
        }

        return $summary;
    }
}
