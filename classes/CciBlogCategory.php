<?php
declare(strict_types=1);

if (!defined('_PS_VERSION_')) { exit; }

class CciBlogCategory extends ObjectModel
{
    public $id_parent;
    public $id_author;
    public $active;
    public $position;
    public $image_url;
    public $date_add;
    public $date_upd;

    // Multilang
    public $name;
    public $slug;
    public $description;
    public $meta_title;
    public $meta_description;
    public $meta_keywords;

    public static $definition = [
        'table'          => 'cci_blog_category',
        'primary'        => 'id_category',
        'multilang'      => true,
        'multilang_shop' => true,
        'fields'         => [
            'id_parent' => ['type' => self::TYPE_INT,  'validate' => 'isUnsignedInt'],
            'id_author' => ['type' => self::TYPE_INT,  'validate' => 'isUnsignedInt'],
            'active'    => ['type' => self::TYPE_BOOL, 'validate' => 'isBool'],
            'position'  => ['type' => self::TYPE_INT,  'validate' => 'isUnsignedInt'],
            'image_url' => ['type' => self::TYPE_STRING, 'validate' => 'isCleanHtml', 'size' => 2048],
            'date_add'  => ['type' => self::TYPE_DATE, 'validate' => 'isDateFormat'],
            'date_upd'  => ['type' => self::TYPE_DATE, 'validate' => 'isDateFormat'],
            // Lang
            'name'             => ['type' => self::TYPE_STRING, 'lang' => true, 'validate' => 'isCleanHtml', 'size' => 255, 'required' => true],
            'slug'             => ['type' => self::TYPE_STRING, 'lang' => true, 'validate' => 'isLinkRewrite', 'size' => 255, 'required' => true],
            'description'      => ['type' => self::TYPE_HTML,   'lang' => true, 'validate' => 'isCleanHtml'],
            'meta_title'       => ['type' => self::TYPE_STRING, 'lang' => true, 'validate' => 'isCleanHtml', 'size' => 255],
            'meta_description' => ['type' => self::TYPE_STRING, 'lang' => true, 'validate' => 'isCleanHtml', 'size' => 512],
            'meta_keywords'    => ['type' => self::TYPE_STRING, 'lang' => true, 'validate' => 'isCleanHtml', 'size' => 255],
        ],
    ];

    /**
     * All active categories with post count.
     */
    public static function getAllWithCount(int $langId, int $shopId): array
    {
        $postCategoryJoin = self::postCategoryTableExists()
            ? "LEFT JOIN `" . _DB_PREFIX_ . "cci_blog_post_category` pc
                ON pc.id_category = c.id_category
               LEFT JOIN `" . _DB_PREFIX_ . "cci_blog_post` p
                ON p.id_post = pc.id_post
                AND p.active = 1
                AND (p.date_published IS NULL OR p.date_published <= '" . pSQL(date('Y-m-d H:i:s')) . "')"
            : "LEFT JOIN `" . _DB_PREFIX_ . "cci_blog_post` p
                ON p.id_category = c.id_category
                AND p.active = 1
                AND (p.date_published IS NULL OR p.date_published <= '" . pSQL(date('Y-m-d H:i:s')) . "')";

        $imageSelect = self::categoryImageColumnExists()
            ? 'c.image_url,'
            : '"" AS image_url,';

        $sql = "
            SELECT c.id_category, c.id_parent, c.position,
                   $imageSelect
                   cl.name, cl.slug, cl.description,
                   COUNT(DISTINCT ps.id_post) AS post_count
            FROM `" . _DB_PREFIX_ . "cci_blog_category` c
            INNER JOIN `" . _DB_PREFIX_ . "cci_blog_category_lang` cl
                ON cl.id_category = c.id_category AND cl.id_lang = $langId AND cl.id_shop = $shopId
            $postCategoryJoin
            LEFT JOIN `" . _DB_PREFIX_ . "cci_blog_post_shop` ps
                ON ps.id_post = p.id_post AND ps.id_shop = $shopId
            WHERE c.active = 1
            GROUP BY c.id_category
            ORDER BY c.position ASC, cl.name ASC
        ";

        return Db::getInstance()->executeS($sql) ?: [];
    }

    /**
     * Prepare a storefront navigation tree. SmartBlog installations commonly
     * contain a technical root named Home/Blog; it must not be exposed as a
     * real category. Empty categories are omitted from the compact sidebar.
     */
    public static function getSidebarCategories(int $langId, int $shopId): array
    {
        $categories = self::getAllWithCount($langId, $shopId);
        $technicalRootIds = [];

        foreach ($categories as $category) {
            $isRoot = (int) ($category['id_parent'] ?? 0) === 0;
            $identifier = Tools::str2url((string) ($category['slug'] ?: $category['name'] ?? ''));
            if ($isRoot && in_array($identifier, ['home', 'blog'], true)) {
                $technicalRootIds[] = (int) $category['id_category'];
            }
        }

        $result = [];
        foreach ($categories as $category) {
            $categoryId = (int) ($category['id_category'] ?? 0);
            $parentId = (int) ($category['id_parent'] ?? 0);
            if (in_array($categoryId, $technicalRootIds, true) || (int) ($category['post_count'] ?? 0) <= 0) {
                continue;
            }
            if (in_array($parentId, $technicalRootIds, true)) {
                $category['id_parent'] = 0;
            }
            $result[] = $category;
        }

        return $result;
    }

    public static function getBySlug(string $slug, int $langId, int $shopId): array
    {
        $sql = "
            SELECT c.*, cl.*
            FROM `" . _DB_PREFIX_ . "cci_blog_category` c
            INNER JOIN `" . _DB_PREFIX_ . "cci_blog_category_lang` cl
                ON cl.id_category = c.id_category AND cl.id_lang = $langId AND cl.id_shop = $shopId
            WHERE cl.slug = '" . pSQL($slug) . "' AND c.active = 1
        ";

        return Db::getInstance()->getRow($sql) ?: [];
    }

    /**
     * Build nested tree from flat list.
     */
    public static function buildTree(array $flat, int $parentId = 0): array
    {
        $tree = [];
        foreach ($flat as $item) {
            if ((int) $item['id_parent'] === $parentId) {
                $item['children'] = self::buildTree($flat, (int) $item['id_category']);
                $tree[] = $item;
            }
        }
        return $tree;
    }

    private static function postCategoryTableExists(): bool
    {
        return (bool) Db::getInstance()->getValue(
            'SELECT COUNT(*)
             FROM INFORMATION_SCHEMA.TABLES
             WHERE TABLE_SCHEMA = DATABASE()
             AND TABLE_NAME = "' . _DB_PREFIX_ . 'cci_blog_post_category"'
        );
    }

    private static function categoryImageColumnExists(): bool
    {
        return (bool) Db::getInstance()->getValue(
            'SELECT COUNT(*)
             FROM INFORMATION_SCHEMA.COLUMNS
             WHERE TABLE_SCHEMA = DATABASE()
             AND TABLE_NAME = "' . _DB_PREFIX_ . 'cci_blog_category"
             AND COLUMN_NAME = "image_url"'
        );
    }
}
