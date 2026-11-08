<?php

declare(strict_types=1);

if (!defined('_PS_VERSION_')) {
    exit;
}

/** Read-only catalogue definitions shared by the editor, renderer and audit adapter. */
final class CciBlogComparisonFields
{
    public static function selection(array $block): array
    {
        $rawIds = is_array($block['ids'] ?? null) ? $block['ids'] : preg_split('/[\s,]+/', (string) ($block['ids'] ?? ''));
        $ids = array_values(array_unique(array_filter(array_map('intval', $rawIds ?: []), static fn (int $id): bool => $id > 0)));
        $rawFields = $block['fields'] ?? ['price', 'reference'];
        $rawFields = is_array($rawFields) ? $rawFields : preg_split('/[\s,]+/', (string) $rawFields);
        $fields = array_values(array_unique(array_filter($rawFields ?: [], static fn ($field): bool => is_string($field) && $field !== '')));

        return ['ids' => array_slice($ids, 0, 4), 'fields' => array_slice($fields, 0, 12), 'productCount' => count($ids), 'fieldCount' => count($fields)];
    }

    public static function available(int $shopId, int $langId, array $productIds = []): array
    {
        $fields = [['value' => 'price', 'label' => 'Price'], ['value' => 'reference', 'label' => 'Reference'], ['value' => 'brand', 'label' => 'Brand']];
        if (!Feature::isFeatureActive()) {
            return $fields;
        }
        $where = '';
        $productIds = self::selection(['ids' => $productIds])['ids'];
        if ($productIds) {
            $where = ' AND EXISTS (SELECT 1 FROM ' . _DB_PREFIX_ . 'feature_product fp INNER JOIN ' . _DB_PREFIX_ . 'product_shop ps ON ps.id_product=fp.id_product AND ps.id_shop=' . $shopId . ' WHERE fp.id_feature=f.id_feature AND fp.id_product IN (' . implode(',', $productIds) . '))';
        }
        $rows = Db::getInstance()->executeS('SELECT f.id_feature, fl.name FROM ' . _DB_PREFIX_ . 'feature f INNER JOIN ' . _DB_PREFIX_ . 'feature_shop fs ON fs.id_feature=f.id_feature AND fs.id_shop=' . $shopId . ' INNER JOIN ' . _DB_PREFIX_ . 'feature_lang fl ON fl.id_feature=f.id_feature AND fl.id_lang=' . $langId . ' WHERE 1' . $where . ' ORDER BY f.position, fl.name LIMIT 200');
        if ($rows === false) {
            throw new RuntimeException('Product comparison fields could not be loaded.');
        }
        foreach ($rows as $row) {
            $fields[] = ['value' => 'feature:' . (int) $row['id_feature'], 'label' => (string) $row['name']];
        }

        return $fields;
    }
}
