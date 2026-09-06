<?php
/**
 * Seed rich demo content for CCI Blog and connect blog blocks to CCI Nice Menu.
 *
 * Run from the shop container:
 * php /var/www/html/modules/cci_blog/sql/seed_demo_content.php
 */

declare(strict_types=1);

$rootDir = dirname(__DIR__, 3);

require_once $rootDir . '/config/config.inc.php';
require_once $rootDir . '/modules/cci_blog/classes/CciBlogPost.php';
require_once $rootDir . '/modules/cci_blog/classes/CciBlogCategory.php';
require_once $rootDir . '/modules/cci_blog/classes/CciBlogTag.php';

if (!defined('_PS_VERSION_')) {
    fwrite(STDERR, "This script must run inside a PrestaShop context.\n");
    exit(1);
}

$db = Db::getInstance();
$now = date('Y-m-d H:i:s');
$shopId = (int) $db->getValue('SELECT `id_shop` FROM `' . _DB_PREFIX_ . 'shop` ORDER BY `id_shop` ASC');
$shopId = $shopId > 0 ? $shopId : 1;
$languages = Language::getLanguages(false);
$defaultLangId = (int) Configuration::get('PS_LANG_DEFAULT');
$activeLangId = (int) $db->getValue('SELECT `id_lang` FROM `' . _DB_PREFIX_ . 'lang` WHERE `active` = 1 ORDER BY `id_lang` ASC');
$contentLangId = $activeLangId > 0 ? $activeLangId : $defaultLangId;
$employeeId = cciBlogDemoEmployeeId($db);

if ($employeeId <= 0) {
    fwrite(STDERR, "No active employee found for demo post author.\n");
    exit(1);
}

cciBlogDemoEnsureTables($db);
cciBlogDemoConfigureBlog();
cciBlogDemoSetupContext($shopId, $contentLangId);
$productImages = cciBlogDemoProductImages($db, $shopId, $contentLangId, [1, 2, 3, 6, 12, 14, 15, 16, 18, 19]);

$categoryMap = cciBlogDemoSeedCategories($db, $languages, $shopId, $employeeId, $productImages, $now);
cciBlogDemoSeedAuthor($db, $languages, $employeeId, $now);
$postMap = cciBlogDemoSeedPosts($db, $languages, $shopId, $employeeId, $categoryMap, $productImages, $now);
cciBlogDemoSeedComments($db, $postMap, $now);
$menuBackup = cciBlogDemoUpdateNiceMenu($db, $shopId, $contentLangId, $postMap);

$postCount = (int) $db->getValue(
    'SELECT COUNT(*)
     FROM `' . _DB_PREFIX_ . 'cci_blog_post_lang`
     WHERE `slug` LIKE "cci-demo-%"
     AND `id_shop` = ' . (int) $shopId . '
     AND `id_lang` = ' . (int) $contentLangId
);
$categoryCount = (int) $db->getValue(
    'SELECT COUNT(*)
     FROM `' . _DB_PREFIX_ . 'cci_blog_category_lang`
     WHERE `slug` LIKE "cci-demo-%"
     AND `id_shop` = ' . (int) $shopId . '
     AND `id_lang` = ' . (int) $contentLangId
);
$relationCount = (int) $db->getValue(
    'SELECT COUNT(*)
     FROM `' . _DB_PREFIX_ . 'cci_blog_post_category` pc
     INNER JOIN `' . _DB_PREFIX_ . 'cci_blog_post_lang` pl ON pl.`id_post` = pc.`id_post`
     WHERE pl.`slug` LIKE "cci-demo-%"
     AND pl.`id_shop` = ' . (int) $shopId . '
     AND pl.`id_lang` = ' . (int) $contentLangId
);

echo "CCI Blog demo seed completed.\n";
echo "Author employee id: " . $employeeId . "\n";
echo "Demo categories: " . $categoryCount . "\n";
echo "Demo posts: " . $postCount . "\n";
echo "Demo post-category relations: " . $relationCount . "\n";
echo "Nice Menu backup: " . ($menuBackup ?: 'not updated') . "\n";

function cciBlogDemoEnsureTables(Db $db): void
{
    $hasCategoryAuthor = (bool) $db->getValue(
        'SELECT COUNT(*)
         FROM INFORMATION_SCHEMA.COLUMNS
         WHERE TABLE_SCHEMA = DATABASE()
         AND TABLE_NAME = "' . _DB_PREFIX_ . 'cci_blog_category"
         AND COLUMN_NAME = "id_author"'
    );
    if (!$hasCategoryAuthor) {
        $db->execute('ALTER TABLE `' . _DB_PREFIX_ . 'cci_blog_category` ADD `id_author` INT UNSIGNED NOT NULL DEFAULT 0 AFTER `id_parent`, ADD KEY `idx_author` (`id_author`)');
    }

    $hasCategoryImage = (bool) $db->getValue(
        'SELECT COUNT(*)
         FROM INFORMATION_SCHEMA.COLUMNS
         WHERE TABLE_SCHEMA = DATABASE()
         AND TABLE_NAME = "' . _DB_PREFIX_ . 'cci_blog_category"
         AND COLUMN_NAME = "image_url"'
    );
    if (!$hasCategoryImage) {
        $db->execute('ALTER TABLE `' . _DB_PREFIX_ . 'cci_blog_category` ADD `image_url` VARCHAR(2048) DEFAULT NULL AFTER `position`');
    }

    $db->execute(
        'CREATE TABLE IF NOT EXISTS `' . _DB_PREFIX_ . 'cci_blog_post_category` (
            `id_post` INT UNSIGNED NOT NULL,
            `id_category` INT UNSIGNED NOT NULL,
            `is_primary` TINYINT(1) NOT NULL DEFAULT 0,
            `position` INT UNSIGNED NOT NULL DEFAULT 0,
            PRIMARY KEY (`id_post`, `id_category`),
            KEY `idx_category` (`id_category`),
            KEY `idx_primary` (`is_primary`)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci'
    );
}

function cciBlogDemoConfigureBlog(): void
{
    $settings = [
        'CCB_POSTS_PER_PAGE' => 9,
        'CCB_LAYOUT' => 'grid',
        'CCB_SIDEBAR_POSITION' => 'right',
        'CCB_SHOW_AUTHOR' => 1,
        'CCB_SHOW_DATE' => 1,
        'CCB_SHOW_VIEWS' => 1,
        'CCB_SHOW_READ_TIME' => 1,
        'CCB_RELATED_PRODUCTS' => 1,
        'CCB_RELATED_POSTS' => 3,
        'CCB_SCHEMA_ORG' => 1,
        'CCB_OG_TAGS' => 1,
        'CCB_BREADCRUMB' => 1,
        'CCB_SOCIAL_SHARE' => 1,
        'CCB_FEED_ENABLED' => 1,
    'CCB_COMMENTS_ENABLED' => 1,
    'CCB_COMMENTS_PROVIDER' => 'disqus',
    'CCB_COMMENTS_MODERATION' => 1,
    'CCB_DISQUS_SHORTNAME' => '',
    'CCB_TABLE_OF_CONTENTS_ENABLED' => 1,
    'CCB_TABLE_OF_CONTENTS_MIN_HEADINGS' => 2,
];

    foreach ($settings as $key => $value) {
        Configuration::updateValue($key, $value);
    }
}

function cciBlogDemoSetupContext(int $shopId, int $langId): void
{
    $context = Context::getContext();
    $context->shop = new Shop($shopId);
    $context->language = new Language($langId);
    $currencyId = (int) Configuration::get('PS_CURRENCY_DEFAULT');
    if ($currencyId > 0) {
        $context->currency = new Currency($currencyId);
    }
    $context->link = new Link();
}

function cciBlogDemoEmployeeId(Db $db): int
{
    return (int) $db->getValue(
        'SELECT `id_employee`
         FROM `' . _DB_PREFIX_ . 'employee`
         WHERE `active` = 1
         ORDER BY `id_employee` ASC'
    );
}

function cciBlogDemoSeedAuthor(Db $db, array $languages, int $employeeId, string $now): void
{
    $displayName = 'John Smith';

    $authorId = (int) $db->getValue(
        'SELECT `id_author`
         FROM `' . _DB_PREFIX_ . 'cci_blog_author`
         WHERE `id_employee` = ' . (int) $employeeId
    );

    if ($authorId > 0) {
        $db->update('cci_blog_author', [
            'display_name' => pSQL($displayName),
            'active' => 1,
        ], '`id_author` = ' . (int) $authorId);
    } else {
        $db->insert('cci_blog_author', [
            'id_employee' => (int) $employeeId,
            'display_name' => pSQL($displayName),
            'avatar' => '',
            'twitter' => '',
            'linkedin' => '',
            'active' => 1,
        ]);
        $authorId = (int) $db->Insert_ID();
    }

    foreach ($languages as $language) {
        $langId = (int) $language['id_lang'];
        $bio = 'Demo author showing what CCI Blog can do with blocks, SEO, products and menu integrations.';

        $db->execute(
            'REPLACE INTO `' . _DB_PREFIX_ . 'cci_blog_author_lang`
             (`id_author`, `id_lang`, `bio`)
             VALUES (' . (int) $authorId . ', ' . (int) $langId . ', "' . pSQL($bio, true) . '")'
        );
    }
}

function cciBlogDemoSeedCategories(Db $db, array $languages, int $shopId, int $employeeId, array $productImages, string $now): array
{
    $definitions = [
        'strategy' => [
            'parent' => '',
            'position' => 10,
            'image_product' => 1,
            'lang' => [
                'en' => [
                    'name' => 'Inspiration and strategy',
                    'slug' => 'cci-demo-inspiration-strategy',
                    'description' => 'Planning content, campaigns and storefront journeys for a print commerce shop.',
                    'meta_title' => 'Inspiration and strategy',
                    'meta_description' => 'Demo blog category for strategy articles, campaign ideas and shop growth.',
                    'meta_keywords' => 'print commerce, strategy, inspiration',
                ],
                'pl' => [
                    'name' => 'Inspiracje i strategia',
                    'slug' => 'cci-demo-inspiracje-strategia',
                    'description' => 'Planowanie tresci, kampanii i sciezek zakupowych dla sklepu z drukiem.',
                    'meta_title' => 'Inspiracje i strategia',
                    'meta_description' => 'Demo kategorii bloga dla strategii, kampanii i rozwoju sklepu.',
                    'meta_keywords' => 'druk, strategia, inspiracje',
                ],
            ],
        ],
        'design-trends' => [
            'parent' => 'strategy',
            'position' => 20,
            'image_product' => 3,
            'lang' => [
                'en' => [
                    'name' => 'Design trends',
                    'slug' => 'cci-demo-design-trends',
                    'description' => 'Visual trends, product storytelling and creative direction for print collections.',
                    'meta_title' => 'Design trends',
                    'meta_description' => 'Demo category for design trends and creative direction.',
                    'meta_keywords' => 'design trends, products, collection',
                ],
                'pl' => [
                    'name' => 'Trendy projektowe',
                    'slug' => 'cci-demo-trendy-projektowe',
                    'description' => 'Trendy wizualne, storytelling produktowy i kierunek kreatywny kolekcji.',
                    'meta_title' => 'Trendy projektowe',
                    'meta_description' => 'Demo kategorii dla trendow projektowych i kierunku kreatywnego.',
                    'meta_keywords' => 'trendy, projekt, kolekcja',
                ],
            ],
        ],
        'store-growth' => [
            'parent' => 'strategy',
            'position' => 30,
            'image_product' => 18,
            'lang' => [
                'en' => [
                    'name' => 'Store growth',
                    'slug' => 'cci-demo-store-growth',
                    'description' => 'Content flows, menus and product discovery patterns that help customers decide.',
                    'meta_title' => 'Store growth',
                    'meta_description' => 'Demo category for content commerce and shop growth tactics.',
                    'meta_keywords' => 'store growth, content commerce, navigation',
                ],
                'pl' => [
                    'name' => 'Rozwoj sklepu',
                    'slug' => 'cci-demo-rozwoj-sklepu',
                    'description' => 'Przeplywy tresci, menu i odkrywanie produktow wspierajace decyzje klientow.',
                    'meta_title' => 'Rozwoj sklepu',
                    'meta_description' => 'Demo kategorii dla content commerce i rozwoju sklepu.',
                    'meta_keywords' => 'sklep, content commerce, nawigacja',
                ],
            ],
        ],
        'production' => [
            'parent' => '',
            'position' => 40,
            'image_product' => 3,
            'lang' => [
                'en' => [
                    'name' => 'Production guides',
                    'slug' => 'cci-demo-production-guides',
                    'description' => 'Practical guides for print production, artwork preparation and materials.',
                    'meta_title' => 'Production guides',
                    'meta_description' => 'Demo blog category for production education and print guides.',
                    'meta_keywords' => 'print production, artwork, materials',
                ],
                'pl' => [
                    'name' => 'Poradniki produkcyjne',
                    'slug' => 'cci-demo-poradniki-produkcyjne',
                    'description' => 'Praktyczne poradniki o produkcji, przygotowaniu plikow i materialach.',
                    'meta_title' => 'Poradniki produkcyjne',
                    'meta_description' => 'Demo kategorii dla edukacji produkcyjnej i poradnikow druku.',
                    'meta_keywords' => 'druk, produkcja, materialy',
                ],
            ],
        ],
        'printing-workflow' => [
            'parent' => 'production',
            'position' => 50,
            'image_product' => 12,
            'lang' => [
                'en' => [
                    'name' => 'Printing workflow',
                    'slug' => 'cci-demo-printing-workflow',
                    'description' => 'From artwork checks to finished displays, packaging and delivery.',
                    'meta_title' => 'Printing workflow',
                    'meta_description' => 'Demo category covering print production workflow.',
                    'meta_keywords' => 'workflow, large format, print',
                ],
                'pl' => [
                    'name' => 'Proces druku',
                    'slug' => 'cci-demo-proces-druku',
                    'description' => 'Od kontroli plikow po gotowe ekspozycje, pakowanie i wysylke.',
                    'meta_title' => 'Proces druku',
                    'meta_description' => 'Demo kategorii opisujacej proces produkcji druku.',
                    'meta_keywords' => 'proces, druk wielkoformatowy, produkcja',
                ],
            ],
        ],
        'materials-care' => [
            'parent' => 'production',
            'position' => 60,
            'image_product' => 2,
            'lang' => [
                'en' => [
                    'name' => 'Materials and care',
                    'slug' => 'cci-demo-materials-care',
                    'description' => 'Choosing print methods, fabrics and care routines for long-lasting products.',
                    'meta_title' => 'Materials and care',
                    'meta_description' => 'Demo category for materials, durability and care instructions.',
                    'meta_keywords' => 'materials, care, durability',
                ],
                'pl' => [
                    'name' => 'Materialy i pielegnacja',
                    'slug' => 'cci-demo-materialy-pielegnacja',
                    'description' => 'Dobor metod druku, tkanin i pielegnacji dla trwalych produktow.',
                    'meta_title' => 'Materialy i pielegnacja',
                    'meta_description' => 'Demo kategorii o materialach, trwalosci i pielegnacji.',
                    'meta_keywords' => 'materialy, pielegnacja, trwalosc',
                ],
            ],
        ],
        'stories' => [
            'parent' => '',
            'position' => 70,
            'image_product' => 6,
            'lang' => [
                'en' => [
                    'name' => 'Product stories',
                    'slug' => 'cci-demo-product-stories',
                    'description' => 'Editorial product inspiration for apparel, gifts and accessories.',
                    'meta_title' => 'Product stories',
                    'meta_description' => 'Demo category for editorial product stories.',
                    'meta_keywords' => 'product stories, gifts, apparel',
                ],
                'pl' => [
                    'name' => 'Historie produktowe',
                    'slug' => 'cci-demo-historie-produktowe',
                    'description' => 'Editorialowe inspiracje produktowe dla odziezy, prezentow i akcesoriow.',
                    'meta_title' => 'Historie produktowe',
                    'meta_description' => 'Demo kategorii dla historii produktowych.',
                    'meta_keywords' => 'produkty, prezenty, odziez',
                ],
            ],
        ],
        'apparel' => [
            'parent' => 'stories',
            'position' => 80,
            'image_product' => 1,
            'lang' => [
                'en' => [
                    'name' => 'Apparel',
                    'slug' => 'cci-demo-apparel',
                    'description' => 'T-shirts, sweatshirts and wearable print ideas.',
                    'meta_title' => 'Apparel',
                    'meta_description' => 'Demo category for apparel content and print ideas.',
                    'meta_keywords' => 'apparel, t-shirts, sweatshirts',
                ],
                'pl' => [
                    'name' => 'Odziez',
                    'slug' => 'cci-demo-odziez',
                    'description' => 'Koszulki, bluzy i pomysly na nadruki na odziezy.',
                    'meta_title' => 'Odziez',
                    'meta_description' => 'Demo kategorii o odziezy i pomyslach na nadruki.',
                    'meta_keywords' => 'odziez, koszulki, bluzy',
                ],
            ],
        ],
        'gifts-accessories' => [
            'parent' => 'stories',
            'position' => 90,
            'image_product' => 6,
            'lang' => [
                'en' => [
                    'name' => 'Gifts and accessories',
                    'slug' => 'cci-demo-gifts-accessories',
                    'description' => 'Personalized mugs, posters, notebooks and small giftable products.',
                    'meta_title' => 'Gifts and accessories',
                    'meta_description' => 'Demo category for personalized gift content.',
                    'meta_keywords' => 'gifts, accessories, personalization',
                ],
                'pl' => [
                    'name' => 'Prezenty i akcesoria',
                    'slug' => 'cci-demo-prezenty-akcesoria',
                    'description' => 'Personalizowane kubki, plakaty, notesy i drobne prezenty.',
                    'meta_title' => 'Prezenty i akcesoria',
                    'meta_description' => 'Demo kategorii o personalizowanych prezentach.',
                    'meta_keywords' => 'prezenty, akcesoria, personalizacja',
                ],
            ],
        ],
    ];

    $map = [];
    foreach ($definitions as $key => $definition) {
        $parentKey = (string) ($definition['parent'] ?? '');
        $parentId = $parentKey !== '' ? (int) ($map[$parentKey] ?? 0) : 0;
        $imageProductId = (int) ($definition['image_product'] ?? 0);
        $imageUrl = $imageProductId > 0 ? (string) ($productImages[$imageProductId] ?? '') : '';
        $defaultLangData = cciBlogDemoLangData($definition['lang'], 'en');
        $categoryId = cciBlogDemoFindCategoryBySlug($db, $defaultLangData['slug'], $shopId);

        if ($categoryId > 0) {
            $db->update('cci_blog_category', [
                'id_parent' => $parentId,
                'id_author' => $employeeId,
                'active' => 1,
                'position' => (int) $definition['position'],
                'image_url' => pSQL($imageUrl),
                'date_upd' => pSQL($now),
            ], '`id_category` = ' . (int) $categoryId);
        } else {
            $db->insert('cci_blog_category', [
                'id_parent' => $parentId,
                'id_author' => $employeeId,
                'active' => 1,
                'position' => (int) $definition['position'],
                'image_url' => pSQL($imageUrl),
                'date_add' => pSQL($now),
                'date_upd' => pSQL($now),
            ]);
            $categoryId = (int) $db->Insert_ID();
        }

        $db->execute(
            'INSERT IGNORE INTO `' . _DB_PREFIX_ . 'cci_blog_category_shop`
             (`id_category`, `id_shop`)
             VALUES (' . (int) $categoryId . ', ' . (int) $shopId . ')'
        );

        foreach ($languages as $language) {
            $langId = (int) $language['id_lang'];
            $data = cciBlogDemoLangData($definition['lang'], 'en');
            $db->execute(
                'REPLACE INTO `' . _DB_PREFIX_ . 'cci_blog_category_lang`
                 (`id_category`, `id_lang`, `id_shop`, `name`, `slug`, `description`, `meta_title`, `meta_description`, `meta_keywords`)
                 VALUES (
                    ' . (int) $categoryId . ',
                    ' . (int) $langId . ',
                    ' . (int) $shopId . ',
                    "' . pSQL($data['name'], true) . '",
                    "' . pSQL($data['slug']) . '",
                    "' . pSQL($data['description'], true) . '",
                    "' . pSQL($data['meta_title'], true) . '",
                    "' . pSQL($data['meta_description'], true) . '",
                    "' . pSQL($data['meta_keywords'], true) . '"
                 )'
            );
        }

        $map[$key] = $categoryId;
    }

    cciBlogDemoPruneLegacyCategories($db, $shopId, array_values($map));

    return $map;
}

function cciBlogDemoPruneLegacyCategories(Db $db, int $shopId, array $keepCategoryIds): void
{
    $keepCategoryIds = array_values(array_filter(array_unique(array_map('intval', $keepCategoryIds))));
    if (!$keepCategoryIds) {
        return;
    }

    $keepSql = implode(',', $keepCategoryIds);
    $legacyRows = $db->executeS(
        'SELECT DISTINCT `id_category`
         FROM `' . _DB_PREFIX_ . 'cci_blog_category_lang`
         WHERE `id_shop` = ' . (int) $shopId . '
         AND `slug` LIKE "cci-demo-%"
         AND `id_category` NOT IN (' . $keepSql . ')'
    );
    if (!is_array($legacyRows) || !$legacyRows) {
        return;
    }

    $legacyCategoryIds = array_values(array_filter(array_unique(array_map(static function ($row): int {
        return (int) ($row['id_category'] ?? 0);
    }, $legacyRows))));
    if (!$legacyCategoryIds) {
        return;
    }

    $legacySql = implode(',', $legacyCategoryIds);
    $db->delete('cci_blog_post_category', '`id_category` IN (' . $legacySql . ')');
    $db->delete('cci_blog_category_shop', '`id_shop` = ' . (int) $shopId . ' AND `id_category` IN (' . $legacySql . ')');
    $db->delete('cci_blog_category_lang', '`id_shop` = ' . (int) $shopId . ' AND `id_category` IN (' . $legacySql . ')');
    $db->delete('cci_blog_category', '`id_category` IN (' . $legacySql . ')');
}

function cciBlogDemoSeedPosts(
    Db $db,
    array $languages,
    int $shopId,
    int $employeeId,
    array $categoryMap,
    array $productImages,
    string $now
): array {
    $posts = [
        'collection' => [
            'primary' => 'strategy',
            'categories' => ['strategy', 'design-trends', 'store-growth'],
            'products' => [1, 2, 19],
            'views' => 1842,
            'featured' => 1,
            'published' => '2026-06-18 09:00:00',
            'cover_product' => 1,
            'tags' => [
                'en' => ['print on demand', 'collection planning', 'store growth'],
                'pl' => ['print on demand', 'planowanie kolekcji', 'rozwoj sklepu'],
            ],
            'lang' => [
                'en' => [
                    'title' => 'How to build a print-on-demand collection that sells',
                    'slug' => 'cci-demo-print-on-demand-collection',
                    'intro' => 'A complete editorial guide showing how strategy, links and structured article content can support a selling journey.',
                    'focus_keyword' => 'print-on-demand collection',
                    'meta_title' => 'How to build a print-on-demand collection that sells',
                    'meta_description' => 'Plan a print-on-demand collection with product stories, internal links and SEO-ready article structure.',
                    'meta_keywords' => 'print-on-demand collection, product content, ecommerce blog',
                    'og_title' => 'Print-on-demand collection guide',
                    'og_description' => 'A demo article with rich text, linked media and SEO content.',
                ],
                'pl' => [
                    'title' => 'Jak zbudowac kolekcje print-on-demand, ktora sprzedaje',
                    'slug' => 'cci-demo-kolekcja-print-on-demand',
                'intro' => 'Poradnik pokazujacy, jak strategia, linki i uporzadkowana tresc wspieraja sciezke sprzedazowa.',
                    'focus_keyword' => 'kolekcja print-on-demand',
                    'meta_title' => 'Jak zbudowac kolekcje print-on-demand, ktora sprzedaje',
                    'meta_description' => 'Zaplanuj kolekcje print-on-demand z historiami produktow, linkami wewnetrznymi i struktura SEO.',
                    'meta_keywords' => 'kolekcja print-on-demand, content produktowy, blog ecommerce',
                    'og_title' => 'Poradnik kolekcji print-on-demand',
                    'og_description' => 'Demo wpisu z tekstem, linkowanymi mediami i trescia SEO.',
                ],
            ],
        ],
        'large-format' => [
            'primary' => 'printing-workflow',
            'categories' => ['production', 'printing-workflow', 'materials-care'],
            'products' => [3, 4, 5],
            'views' => 1260,
            'featured' => 1,
            'published' => '2026-06-14 10:30:00',
            'cover_product' => 3,
            'tags' => [
                'en' => ['large format printing', 'artwork checklist', 'durability'],
                'pl' => ['druk wielkoformatowy', 'lista kontrolna plikow', 'trwalosc'],
            ],
            'lang' => [
                'en' => [
                    'title' => 'Large format printing: from artwork to durable display',
                    'slug' => 'cci-demo-large-format-printing-workflow',
                    'intro' => 'Use headings, checklists and linked media to explain a complex production workflow without losing the shopper.',
                    'focus_keyword' => 'large format printing',
                    'meta_title' => 'Large format printing workflow guide',
                    'meta_description' => 'Learn how artwork files become durable large format displays with a clear workflow and production checklist.',
                    'meta_keywords' => 'large format printing, artwork, display',
                    'og_title' => 'Large format printing workflow',
                    'og_description' => 'A demo production guide with headings, images and linked media.',
                ],
                'pl' => [
                    'title' => 'Druk wielkoformatowy: od pliku do trwalej ekspozycji',
                    'slug' => 'cci-demo-druk-wielkoformatowy-proces',
                    'intro' => 'Uzyj naglowkow, checklist i mediow, aby wyjasnic zlozony proces produkcyjny bez utraty uwagi klienta.',
                    'focus_keyword' => 'druk wielkoformatowy',
                    'meta_title' => 'Poradnik procesu druku wielkoformatowego',
                    'meta_description' => 'Zobacz jak pliki graficzne zmieniaja sie w trwale ekspozycje dzieki czytelnemu procesowi produkcyjnemu.',
                    'meta_keywords' => 'druk wielkoformatowy, pliki, ekspozycja',
                    'og_title' => 'Proces druku wielkoformatowego',
                    'og_description' => 'Demo poradnika produkcyjnego z naglowkami, obrazami i linkowanymi mediami.',
                ],
            ],
        ],
        'methods' => [
            'primary' => 'materials-care',
            'categories' => ['production', 'materials-care', 'apparel'],
            'products' => [1, 2, 12],
            'views' => 980,
            'featured' => 0,
            'published' => '2026-06-09 12:00:00',
            'cover_product' => 2,
            'tags' => [
                'en' => ['DTF', 'DTG', 'sublimation', 'apparel print'],
                'pl' => ['DTF', 'DTG', 'sublimacja', 'druk na odziezy'],
            ],
            'lang' => [
                'en' => [
                    'title' => 'DTF, DTG or sublimation: choosing the right decoration method',
                    'slug' => 'cci-demo-dtf-dtg-sublimation-guide',
                    'intro' => 'Compare common decoration methods and connect advice to the right buying intent.',
                    'focus_keyword' => 'decoration method',
                    'meta_title' => 'DTF, DTG or sublimation: decoration method guide',
                    'meta_description' => 'Compare DTF, DTG and sublimation for apparel, gifts and printed accessories.',
                    'meta_keywords' => 'DTF, DTG, sublimation, printing methods',
                    'og_title' => 'Decoration method guide',
                    'og_description' => 'A comparison article with practical recommendations.',
                ],
                'pl' => [
                    'title' => 'DTF, DTG czy sublimacja: jak wybrac metode zdobienia',
                    'slug' => 'cci-demo-dtf-dtg-sublimacja',
                    'intro' => 'Porownaj popularne metody zdobienia i polacz porady z intencja zakupu.',
                    'focus_keyword' => 'metoda zdobienia',
                    'meta_title' => 'DTF, DTG czy sublimacja: poradnik wyboru',
                    'meta_description' => 'Porownanie DTF, DTG i sublimacji dla odziezy, prezentow i akcesoriow drukowanych.',
                    'meta_keywords' => 'DTF, DTG, sublimacja, metody druku',
                    'og_title' => 'Poradnik metod zdobienia',
                    'og_description' => 'Artykul porownawczy z praktycznymi rekomendacjami.',
                ],
            ],
        ],
        'gift-guide' => [
            'primary' => 'gifts-accessories',
            'categories' => ['stories', 'gifts-accessories', 'store-growth'],
            'products' => [6, 15, 16, 19],
            'views' => 760,
            'featured' => 0,
            'published' => '2026-06-03 08:45:00',
            'cover_product' => 6,
            'tags' => [
                'en' => ['gift guide', 'personalization', 'mugs'],
                'pl' => ['poradnik prezentowy', 'personalizacja', 'kubki'],
            ],
            'lang' => [
                'en' => [
                    'title' => 'Gift guide: mugs, posters and notebooks with personal value',
                    'slug' => 'cci-demo-personalized-gift-guide',
                    'intro' => 'A gift-oriented article can mix storytelling and linked images to increase discovery.',
                    'focus_keyword' => 'personalized gift guide',
                    'meta_title' => 'Personalized gift guide for mugs, posters and notebooks',
                    'meta_description' => 'Build a personalized gift guide with linked images and conversion-oriented article structure.',
                    'meta_keywords' => 'gift guide, mugs, notebooks, personalization',
                    'og_title' => 'Personalized gift guide',
                    'og_description' => 'A demo article for gift content and product discovery.',
                ],
                'pl' => [
                    'title' => 'Poradnik prezentowy: kubki, plakaty i notesy z osobista wartoscia',
                    'slug' => 'cci-demo-personalizowany-poradnik-prezentowy',
                    'intro' => 'Artykul prezentowy moze laczyc storytelling i linkowane obrazy, aby zwiekszyc odkrywanie oferty.',
                    'focus_keyword' => 'personalizowany poradnik prezentowy',
                    'meta_title' => 'Personalizowany poradnik prezentowy dla kubkow i notesow',
                    'meta_description' => 'Zbuduj poradnik prezentowy z linkowanymi obrazami i struktura wspierajaca konwersje.',
                    'meta_keywords' => 'prezenty, kubki, notesy, personalizacja',
                    'og_title' => 'Personalizowany poradnik prezentowy',
                    'og_description' => 'Demo wpisu dla tresci prezentowych i odkrywania produktow.',
                ],
            ],
        ],
        'care-guide' => [
            'primary' => 'apparel',
            'categories' => ['stories', 'apparel', 'materials-care'],
            'products' => [1, 2],
            'views' => 610,
            'featured' => 0,
            'published' => '2026-05-28 11:15:00',
            'cover_product' => 1,
            'tags' => [
                'en' => ['care guide', 'printed apparel', 'washing'],
                'pl' => ['pielegnacja', 'odziez z nadrukiem', 'pranie'],
            ],
            'lang' => [
                'en' => [
                    'title' => 'Care guide for printed apparel after the first wash',
                    'slug' => 'cci-demo-care-guide-printed-apparel',
                    'intro' => 'Clear post-purchase education reduces support questions and can link back to relevant categories.',
                    'focus_keyword' => 'printed apparel care',
                    'meta_title' => 'Printed apparel care guide after the first wash',
                    'meta_description' => 'Teach customers how to care for printed apparel and keep designs vivid after the first wash.',
                    'meta_keywords' => 'printed apparel, care guide, washing',
                    'og_title' => 'Printed apparel care guide',
                    'og_description' => 'A support-friendly demo article for apparel care.',
                ],
                'pl' => [
                    'title' => 'Pielegnacja odziezy z nadrukiem po pierwszym praniu',
                    'slug' => 'cci-demo-pielegnacja-odziezy-z-nadrukiem',
                    'intro' => 'Czytelna edukacja posprzedazowa zmniejsza liczbe pytan do obslugi i kieruje do powiazanych kategorii.',
                    'focus_keyword' => 'pielegnacja odziezy z nadrukiem',
                    'meta_title' => 'Pielegnacja odziezy z nadrukiem po pierwszym praniu',
                    'meta_description' => 'Pokaz klientom jak dbac o odziez z nadrukiem, aby projekty zachowaly intensywnosc po praniu.',
                    'meta_keywords' => 'odziez z nadrukiem, pielegnacja, pranie',
                    'og_title' => 'Pielegnacja odziezy z nadrukiem',
                    'og_description' => 'Demo wpisu wsparciowego o pielegnacji odziezy.',
                ],
            ],
        ],
        'menu-content' => [
            'primary' => 'store-growth',
            'categories' => ['strategy', 'store-growth', 'design-trends'],
            'products' => [12, 14, 18],
            'views' => 420,
            'featured' => 0,
            'published' => '2026-05-21 14:20:00',
            'cover_product' => 14,
            'tags' => [
                'en' => ['mega menu', 'content commerce', 'blog blocks'],
                'pl' => ['mega menu', 'content commerce', 'bloki bloga'],
            ],
            'lang' => [
                'en' => [
                    'title' => 'How blog articles can guide shoppers inside a mega menu',
                    'slug' => 'cci-demo-menu-content-blocks-guide',
                    'intro' => 'Use blog posts as navigation content: sticky articles, latest lists, popular reads and carousels inside the menu.',
                    'focus_keyword' => 'blog blocks in menu',
                    'meta_title' => 'Blog blocks in mega menu navigation',
                    'meta_description' => 'Show blog content inside a mega menu using sticky, list, popular and carousel blocks.',
                    'meta_keywords' => 'blog blocks, mega menu, content commerce',
                    'og_title' => 'Blog blocks in menu',
                    'og_description' => 'A demo article explaining how the menu integration works.',
                ],
                'pl' => [
                    'title' => 'Jak wpisy blogowe prowadza klienta w mega menu',
                    'slug' => 'cci-demo-bloki-bloga-w-menu',
                    'intro' => 'Uzyj wpisow jako tresci nawigacyjnej: sticky artykul, najnowsze wpisy, popularne czytania i karuzele w menu.',
                    'focus_keyword' => 'bloki bloga w menu',
                    'meta_title' => 'Bloki bloga w nawigacji mega menu',
                    'meta_description' => 'Pokazuj tresci blogowe w mega menu przez sticky, liste, popularne wpisy i karuzele.',
                    'meta_keywords' => 'bloki bloga, mega menu, content commerce',
                    'og_title' => 'Bloki bloga w menu',
                    'og_description' => 'Demo wpisu wyjasniajacego integracje menu.',
                ],
            ],
        ],
    ];

    $postMap = [];
    foreach ($posts as $key => $definition) {
        $defaultLangData = cciBlogDemoLangData($definition['lang'], 'en');
        $postId = cciBlogDemoFindPostBySlug($db, $defaultLangData['slug'], $shopId);
        $primaryCategoryId = (int) ($categoryMap[$definition['primary']] ?? 0);
        $coverImage = (string) ($productImages[(int) $definition['cover_product']] ?? '');

        if ($postId > 0) {
            $db->update('cci_blog_post', [
                'id_category' => $primaryCategoryId,
                'id_author' => $employeeId,
                'active' => 1,
                'featured' => (int) $definition['featured'],
                'allow_comments' => 1,
                'views' => (int) $definition['views'],
                'cover_image' => pSQL($coverImage),
                'og_image' => pSQL($coverImage),
                'date_published' => pSQL($definition['published']),
                'date_upd' => pSQL($now),
            ], '`id_post` = ' . (int) $postId);
        } else {
            $db->insert('cci_blog_post', [
                'id_category' => $primaryCategoryId,
                'id_author' => $employeeId,
                'active' => 1,
                'featured' => (int) $definition['featured'],
                'allow_comments' => 1,
                'views' => (int) $definition['views'],
                'cover_image' => pSQL($coverImage),
                'og_image' => pSQL($coverImage),
                'date_published' => pSQL($definition['published']),
                'date_add' => pSQL($now),
                'date_upd' => pSQL($now),
            ]);
            $postId = (int) $db->Insert_ID();
        }

        $db->execute(
            'INSERT IGNORE INTO `' . _DB_PREFIX_ . 'cci_blog_post_shop`
             (`id_post`, `id_shop`)
             VALUES (' . (int) $postId . ', ' . (int) $shopId . ')'
        );

        cciBlogDemoAttachCategories($db, $postId, $definition['categories'], $definition['primary'], $categoryMap);
        cciBlogDemoAttachProducts($db, $postId, $definition['products']);
        cciBlogDemoAttachTags($db, $postId, $languages, $definition['tags']);

        foreach ($languages as $language) {
            $langId = (int) $language['id_lang'];
            $langData = cciBlogDemoLangData($definition['lang'], 'en');
            cciBlogDemoSetupContext($shopId, $langId);
            $blocks = cciBlogDemoBlocks('en', $langData, $definition['products'], $productImages, $categoryMap, $shopId);
            $content = CciBlogPost::renderBlocksToHtml($blocks, Context::getContext());
            $contentBlocks = json_encode($blocks, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);

            $db->execute(
                'REPLACE INTO `' . _DB_PREFIX_ . 'cci_blog_post_lang`
                 (`id_post`, `id_lang`, `id_shop`, `title`, `slug`, `intro`, `content`, `content_blocks`, `meta_title`, `meta_description`, `meta_keywords`, `focus_keyword`, `seo_content_type`, `og_title`, `og_description`)
                 VALUES (
                    ' . (int) $postId . ',
                    ' . (int) $langId . ',
                    ' . (int) $shopId . ',
                    "' . pSQL($langData['title'], true) . '",
                    "' . pSQL($langData['slug']) . '",
                    "' . pSQL($langData['intro'], true) . '",
                    "' . pSQL($content, true) . '",
                    "' . pSQL((string) $contentBlocks, true) . '",
                    "' . pSQL($langData['meta_title'], true) . '",
                    "' . pSQL($langData['meta_description'], true) . '",
                    "' . pSQL($langData['meta_keywords'], true) . '",
                    "' . pSQL($langData['focus_keyword'], true) . '",
                    "article",
                    "' . pSQL($langData['og_title'], true) . '",
                    "' . pSQL($langData['og_description'], true) . '"
                 )'
            );
        }

        $postMap[$key] = $postId;
    }

    return $postMap;
}

function cciBlogDemoBlocks(
    string $iso,
    array $langData,
    array $productIds,
    array $productImages,
    array $categoryMap,
    int $shopId
): array {
    $isPl = $iso === 'pl';
    $lead = $isPl
        ? 'Ten wpis laczy czytelna strukture, materialy wizualne i odnosniki, ktore prowadza odbiorce do kolejnych informacji.'
        : 'This article combines a clear structure, visual material and links that guide readers to related information.';
    $firstProductId = (int) ($productIds[0] ?? 1);
    $linkedImage = (string) ($productImages[$firstProductId] ?? '');
    $categoryUrl = cciBlogDemoCategoryUrl($categoryMap['strategy'] ?? 0, $shopId);

    $heading = $isPl ? 'Co pokazuje ten wpis' : 'What this article demonstrates';
    $summary = $isPl
        ? '<p><strong>Plan tresci.</strong> Dobrze uporzadkowany artykul pomaga szybko znalezc najwazniejsze informacje i przejsc do powiazanych materialow.</p><ul><li>Czytelne sekcje tematyczne</li><li>Kategorie i tagi dopasowane do jezyka</li><li>Obrazy prowadzace do powiazanych tresci</li></ul>'
        : '<p><strong>Content plan.</strong> A well-structured article helps readers find key information quickly and continue to related material.</p><ul><li>Clear topic sections</li><li>Language-aware categories and tags</li><li>Images that lead to related content</li></ul>';

    $blocks = [
        [
            'type' => 'heading',
            'level' => 2,
            'text' => $heading,
        ],
        [
            'type' => 'paragraph',
            'html' => '<p>' . htmlspecialchars($lead, ENT_QUOTES, 'UTF-8') . '</p>',
        ],
        [
            'type' => 'paragraph',
            'html' => $summary,
        ],
        [
            'type' => 'heading',
            'level' => 2,
            'text' => $isPl ? 'Powiazane materialy' : 'Related resources',
        ],
    ];

    if ($linkedImage !== '') {
        $blocks[] = [
            'type' => 'image',
            'src' => $linkedImage,
            'alt' => $isPl ? 'Przykladowy produkt uzyty jako obraz w artykule' : 'Example product used as an article image',
            'caption' => $isPl ? 'Obraz moze prowadzic do kategorii lub produktu.' : 'An image can link to a category or a product.',
            'link' => [
                'href' => $categoryUrl,
                'title' => $isPl ? 'Zobacz powiazana kategorie' : 'View related category',
                'rel' => 'noopener',
                'aria_label' => $isPl ? 'Otworz powiazana kategorie bloga' : 'Open related blog category',
            ],
        ];
    }

    $blocks[] = [
        'type' => 'link',
        'href' => $categoryUrl,
        'label' => $isPl ? 'Zobacz wiecej wpisow strategicznych' : 'View more strategy articles',
        'title' => $isPl ? 'Przejdz do kategorii bloga' : 'Open blog category',
        'target' => '_self',
        'rel' => 'bookmark',
    ];
    $blocks[] = [
        'type' => 'heading',
        'level' => 2,
        'text' => $isPl ? 'Kolejny krok' : 'Next step',
    ];
    $blocks[] = [
        'type' => 'paragraph',
        'html' => $isPl
            ? '<p>Powiązane linki i kategorie pomagają czytelnikowi przejść od poradnika do kolejnego artykułu, produktu lub wybranej sekcji sklepu.</p>'
            : '<p>Related links and categories help readers continue from a guide to another article, product or selected store section.</p>',
    ];

    return $blocks;
}

function cciBlogDemoSeedComments(Db $db, array $postMap, string $now): void
{
    $db->delete('cci_blog_comment', '`author_email` LIKE "cci-demo-%@example.com"');

    $comments = [
        ['post' => 'collection', 'name' => 'Demo Buyer', 'email' => 'cci-demo-buyer@example.com', 'status' => 'approved', 'content' => 'This is a useful way to connect editorial content with product discovery.'],
        ['post' => 'large-format', 'name' => 'Production Lead', 'email' => 'cci-demo-production@example.com', 'status' => 'approved', 'content' => 'The workflow checklist makes the production process much easier to explain to customers.'],
        ['post' => 'menu-content', 'name' => 'Store Manager', 'email' => 'cci-demo-manager@example.com', 'status' => 'pending', 'content' => 'Can we reuse the same blog carousel inside other menu tabs?'],
    ];

    foreach ($comments as $comment) {
        $postId = (int) ($postMap[$comment['post']] ?? 0);
        if ($postId <= 0) {
            continue;
        }

        $db->insert('cci_blog_comment', [
            'id_post' => $postId,
            'id_parent' => 0,
            'id_customer' => 0,
            'author_name' => pSQL($comment['name']),
            'author_email' => pSQL($comment['email']),
            'author_website' => '',
            'content' => pSQL($comment['content'], true),
            'status' => pSQL($comment['status']),
            'ip_address' => '127.0.0.1',
            'date_add' => pSQL($now),
        ]);
    }
}

function cciBlogDemoAttachCategories(Db $db, int $postId, array $categoryKeys, string $primaryKey, array $categoryMap): void
{
    $db->delete('cci_blog_post_category', '`id_post` = ' . (int) $postId);

    $position = 0;
    foreach (array_values(array_unique($categoryKeys)) as $categoryKey) {
        $categoryId = (int) ($categoryMap[$categoryKey] ?? 0);
        if ($categoryId <= 0) {
            continue;
        }

        $db->insert('cci_blog_post_category', [
            'id_post' => $postId,
            'id_category' => $categoryId,
            'is_primary' => $categoryKey === $primaryKey ? 1 : 0,
            'position' => $position++,
        ], false, true);
    }
}

function cciBlogDemoAttachProducts(Db $db, int $postId, array $productIds): void
{
    $db->delete('cci_blog_post_product', '`id_post` = ' . (int) $postId);

    foreach (array_values(array_unique(array_map('intval', $productIds))) as $position => $productId) {
        if ($productId <= 0) {
            continue;
        }
        $db->insert('cci_blog_post_product', [
            'id_post' => $postId,
            'id_product' => $productId,
            'position' => (int) $position,
        ], false, true);
    }
}

function cciBlogDemoAttachTags(Db $db, int $postId, array $languages, array $tagsByLanguage): void
{
    $db->delete('cci_blog_post_tag', '`id_post` = ' . (int) $postId);

    foreach ($languages as $language) {
        $langId = (int) $language['id_lang'];
        $tags = cciBlogDemoLangData($tagsByLanguage, 'en');
        foreach ($tags as $tagName) {
            $tagName = trim((string) $tagName);
            if ($tagName === '') {
                continue;
            }
            $tagId = CciBlogTag::getOrCreate($tagName, $langId);
            $db->insert('cci_blog_post_tag', [
                'id_post' => $postId,
                'id_tag' => $tagId,
            ], false, true);
        }
    }
}

function cciBlogDemoUpdateNiceMenu(Db $db, int $shopId, int $langId, array $postMap): string
{
    $menu = $db->getRow(
        'SELECT `id_menu`, `tree_json`
         FROM `' . _DB_PREFIX_ . 'cci_nice_menu`
         WHERE `is_active` = 1
         ORDER BY `position` ASC, `id_menu` ASC'
    );
    if (!is_array($menu) || empty($menu['id_menu'])) {
        return '';
    }

    $treeJson = (string) ($menu['tree_json'] ?? '');
    $tree = json_decode($treeJson, true);
    if (!is_array($tree)) {
        $tree = [];
    }

    $backupDir = dirname(__DIR__, 2) . '/cci_nice_menu';
    $backupPath = $backupDir . '/tmp-menu-' . (int) $menu['id_menu'] . '-before-blog-demo-' . date('YmdHis') . '.json';
    if (is_dir($backupDir)) {
        file_put_contents($backupPath, $treeJson);
    } else {
        $backupPath = '';
    }

    $blogListUrl = Context::getContext()->link->getModuleLink('cci_blog', 'list');
    $featuredPostId = (int) ($postMap['collection'] ?? reset($postMap));

    $blogChildren = [
        cciBlogDemoMenuNode('cci-blog-demo-row-main', 'row', 'Blog showcase', [
            'columns' => 12,
            'column_span' => 12,
            'description' => 'Demo row for CCI Blog blocks inside Nice Menu.',
        ], [
            cciBlogDemoMenuNode('cci-blog-demo-col-featured', 'column', 'Featured article', [
                'column_span' => 4,
            ], [
                cciBlogDemoMenuNode('cci-blog-demo-block-featured', 'block', 'Featured guide', [
                    'description' => 'Single selected article rendered as a sticky blog card.',
                    'heading_link' => $blogListUrl,
                    'heading_link_label' => 'View all',
                    'heading_link_icon' => 'arrow-right',
                ], [
                    cciBlogDemoMenuNode('cci-blog-demo-featured-post', 'blog_post', 'Featured article', [
                        'entity_id' => $featuredPostId,
                        'description' => 'A hand-picked article that anchors the menu panel.',
                    ]),
                ]),
            ]),
            cciBlogDemoMenuNode('cci-blog-demo-col-latest', 'column', 'Latest articles', [
                'column_span' => 4,
            ], [
                cciBlogDemoMenuNode('cci-blog-demo-block-latest', 'block', 'Latest articles', [
                    'description' => 'Automatically rendered newest posts.',
                    'heading_link' => $blogListUrl,
                    'heading_link_label' => 'Open blog',
                    'heading_link_icon' => 'arrow-right',
                ], [
                    cciBlogDemoMenuNode('cci-blog-demo-latest-list', 'blog_list', 'Latest blog list', [
                        'item_count' => 4,
                        'component_variant' => 'showcase_blog_list',
                        'description' => 'Newest active blog posts from CCI Blog.',
                    ]),
                ]),
            ]),
            cciBlogDemoMenuNode('cci-blog-demo-col-popular', 'column', 'Popular reads', [
                'column_span' => 4,
            ], [
                cciBlogDemoMenuNode('cci-blog-demo-block-popular', 'block', 'Popular reads', [
                    'description' => 'Articles sorted by collected view statistics.',
                    'heading_link' => $blogListUrl,
                    'heading_link_label' => 'Read more',
                    'heading_link_icon' => 'arrow-right',
                ], [
                    cciBlogDemoMenuNode('cci-blog-demo-popular-list', 'blog_popular', 'Popular posts', [
                        'item_count' => 4,
                        'component_variant' => 'showcase_blog_popular',
                        'description' => 'Popular articles ordered by blog view counter.',
                    ]),
                ]),
            ]),
        ]),
        cciBlogDemoMenuNode('cci-blog-demo-row-secondary', 'row', 'More from the blog', [
            'columns' => 12,
            'column_span' => 12,
            'component_variant' => 'showcase_secondary_row',
        ], [
            cciBlogDemoMenuNode('cci-blog-demo-col-carousel', 'column', 'Article carousel', [
                'column_span' => 8,
            ], [
                cciBlogDemoMenuNode('cci-blog-demo-block-carousel', 'block', 'Article carousel', [
                    'description' => 'A horizontal rail using all demo posts.',
                    'heading_link' => $blogListUrl,
                    'heading_link_label' => 'Browse blog',
                    'heading_link_icon' => 'arrow-right',
                ], [
                    cciBlogDemoMenuNode('cci-blog-demo-carousel', 'blog_carousel', 'Blog carousel', [
                        'item_count' => 6,
                        'component_variant' => 'showcase_blog_carousel',
                        'description' => 'Carousel block sourced from published blog posts.',
                    ]),
                ]),
            ]),
            cciBlogDemoMenuNode('cci-blog-demo-col-topics', 'column', 'Blog categories', [
                'column_span' => 4,
            ], [
                cciBlogDemoMenuNode('cci-blog-demo-block-topics', 'block', 'Blog categories', [
                    'description' => 'Automatic or selected category list with post counts.',
                ], [
                    cciBlogDemoMenuNode('cci-blog-demo-categories', 'blog_categories', 'Blog categories', [
                        'link' => '',
                        'item_count' => 8,
                        'component_variant' => 'showcase_blog_categories',
                        'description' => 'Automatic category listing from CCI Blog.',
                        'icon' => 'folder-tree',
                        'blog_category_mode' => 'all',
                        'blog_category_ids' => [],
                        'blog_category_hide_empty' => 1,
                        'blog_category_sort' => 'position',
                        'blog_category_show_count' => 1,
                    ]),
                ]),
            ]),
        ]),
    ];

    $blogIndex = cciBlogDemoFindTopLevelIndex($tree, 'Blog');
    if ($blogIndex < 0 && cciBlogDemoTreeHasLabel($tree, 'Blog')) {
        $updatedJson = json_encode($tree, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        if (!is_string($updatedJson)) {
            throw new RuntimeException('Unable to encode Nice Menu tree JSON.');
        }

        $db->execute(
            'UPDATE `' . _DB_PREFIX_ . 'cci_nice_menu`
             SET `tree_json` = "' . pSQL($updatedJson, true) . '",
                 `updated_at` = "' . pSQL(date('Y-m-d H:i:s')) . '"
             WHERE `id_menu` = ' . (int) $menu['id_menu']
        );

        return $backupPath;
    }

    $blogNode = $blogIndex >= 0 ? (array) $tree[$blogIndex] : cciBlogDemoMenuNode('cci-blog-demo-top-level', 'custom', 'Blog');
    $blogNode = array_replace(cciBlogDemoMenuNode(
        (string) ($blogNode['id'] ?? 'cci-blog-demo-top-level'),
        'custom',
        'Blog'
    ), $blogNode);
    $blogNode['type'] = 'custom';
    $blogNode['label'] = 'Blog';
    $blogNode['link'] = '#';
    $blogNode['icon'] = (string) ($blogNode['icon'] ?? 'file-text') ?: 'file-text';
    $blogNode['description'] = 'Blog guides, popular reads and product inspiration.';
    $blogNode['children'] = $blogChildren;

    if ($blogIndex >= 0) {
        $tree[$blogIndex] = $blogNode;
    } else {
        $tree[] = $blogNode;
    }

    $updatedJson = json_encode($tree, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    if (!is_string($updatedJson)) {
        throw new RuntimeException('Unable to encode Nice Menu tree JSON.');
    }

    $db->execute(
        'UPDATE `' . _DB_PREFIX_ . 'cci_nice_menu`
         SET `tree_json` = "' . pSQL($updatedJson, true) . '",
             `updated_at` = "' . pSQL(date('Y-m-d H:i:s')) . '"
         WHERE `id_menu` = ' . (int) $menu['id_menu']
    );

    return $backupPath;
}

function cciBlogDemoMenuNode(string $id, string $type, string $label, array $overrides = [], array $children = []): array
{
    $node = [
        'id' => $id,
        'type' => $type,
        'label' => $label,
        'link' => '#',
        'heading_link' => '',
        'heading_link_label' => '',
        'heading_link_icon' => 'arrow-right',
        'entity_id' => 0,
        'label_override' => '',
        'url_override' => '',
        'source_label' => '',
        'source_url' => '',
        'hook_name' => '',
        'image_url' => '',
        'description' => '',
        'price' => '',
        'video_provider' => 'youtube',
        'video_url' => '',
        'video_poster' => '',
        'video_aspect' => '16:9',
        'video_autoplay' => 0,
        'video_muted' => 1,
        'video_loop' => 0,
        'video_controls' => 1,
        'video_lazy' => 1,
        'video_start' => 0,
        'video_end' => 0,
        'target' => 0,
        'icon' => '',
        'icon_only' => 0,
        'badge' => '',
        'style_background' => '',
        'style_background_2' => '',
        'style_text' => '',
        'style_badge_background' => '',
        'style_badge_text' => '',
        'layout_role' => '',
        'tabs_orientation' => 'vertical',
        'columns' => 1,
        'column_span' => 0,
        'product_count' => 0,
        'item_count' => 0,
        'component_variant' => '',
        'hide_heading' => 0,
        'children' => $children,
    ];

    return array_replace($node, $overrides);
}

function cciBlogDemoFindTopLevelIndex(array $tree, string $label): int
{
    $needle = strtolower(trim($label));
    foreach ($tree as $index => $node) {
        if (!is_array($node)) {
            continue;
        }
        $nodeLabel = strtolower(trim((string) ($node['label'] ?? $node['label_override'] ?? '')));
        if ($nodeLabel === $needle) {
            return (int) $index;
        }
    }

    return -1;
}

function cciBlogDemoTreeHasLabel(array $tree, string $label): bool
{
    $needle = strtolower(trim($label));
    foreach ($tree as $node) {
        if (!is_array($node)) {
            continue;
        }

        $nodeLabel = strtolower(trim((string) ($node['label'] ?? $node['label_override'] ?? '')));
        if ($nodeLabel === $needle) {
            return true;
        }

        if (!empty($node['children']) && is_array($node['children']) && cciBlogDemoTreeHasLabel($node['children'], $label)) {
            return true;
        }
    }

    return false;
}

function cciBlogDemoCategoryUrl(int $categoryId, int $shopId): string
{
    if ($categoryId <= 0) {
        return Context::getContext()->link->getModuleLink('cci_blog', 'list');
    }

    $slug = (string) Db::getInstance()->getValue(
        'SELECT `slug`
         FROM `' . _DB_PREFIX_ . 'cci_blog_category_lang`
         WHERE `id_category` = ' . (int) $categoryId . '
         AND `id_shop` = ' . (int) $shopId . '
         AND `id_lang` = ' . (int) Context::getContext()->language->id
    );

    return $slug !== ''
        ? Context::getContext()->link->getModuleLink('cci_blog', 'category', ['slug' => $slug])
        : Context::getContext()->link->getModuleLink('cci_blog', 'list');
}

function cciBlogDemoFindCategoryBySlug(Db $db, string $slug, int $shopId): int
{
    return (int) $db->getValue(
        'SELECT `id_category`
         FROM `' . _DB_PREFIX_ . 'cci_blog_category_lang`
         WHERE `slug` = "' . pSQL($slug) . '"
         AND `id_shop` = ' . (int) $shopId
    );
}

function cciBlogDemoFindPostBySlug(Db $db, string $slug, int $shopId): int
{
    return (int) $db->getValue(
        'SELECT `id_post`
         FROM `' . _DB_PREFIX_ . 'cci_blog_post_lang`
         WHERE `slug` = "' . pSQL($slug) . '"
         AND `id_shop` = ' . (int) $shopId
    );
}

function cciBlogDemoLangData(array $data, string $iso): array
{
    if (isset($data[$iso]) && is_array($data[$iso])) {
        return $data[$iso];
    }
    if (isset($data['en']) && is_array($data['en'])) {
        return $data['en'];
    }
    if (isset($data['pl']) && is_array($data['pl'])) {
        return $data['pl'];
    }

    return (array) reset($data);
}

function cciBlogDemoProductImages(Db $db, int $shopId, int $langId, array $productIds): array
{
    cciBlogDemoSetupContext($shopId, $langId);
    $images = [];
    foreach (array_values(array_unique(array_map('intval', $productIds))) as $productId) {
        if ($productId <= 0) {
            continue;
        }
        $row = $db->getRow(
            'SELECT pl.`link_rewrite`, i.`id_image`
             FROM `' . _DB_PREFIX_ . 'product_lang` pl
             LEFT JOIN `' . _DB_PREFIX_ . 'image` i ON i.`id_product` = pl.`id_product` AND i.`cover` = 1
             WHERE pl.`id_product` = ' . (int) $productId . '
             AND pl.`id_lang` = ' . (int) $langId . '
             AND pl.`id_shop` = ' . (int) $shopId
        );
        if (!is_array($row) || empty($row['id_image'])) {
            continue;
        }

        $imageUrl = (string) Context::getContext()->link->getImageLink(
            (string) $row['link_rewrite'],
            $productId . '-' . (int) $row['id_image'],
            'home_default'
        );
        if ($imageUrl !== '' && !preg_match('/^https?:\/\//i', $imageUrl)) {
            if (str_starts_with($imageUrl, '//')) {
                $imageUrl = Tools::getShopProtocol() . ltrim($imageUrl, '/');
            } elseif (str_starts_with($imageUrl, '/')) {
                $imageUrl = rtrim(Tools::getShopDomainSsl(true), '/') . $imageUrl;
            } else {
                $imageUrl = Tools::getShopProtocol() . $imageUrl;
            }
        }

        $images[$productId] = $imageUrl;
    }

    return $images;
}
