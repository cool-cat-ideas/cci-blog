<?php

declare(strict_types=1);
if (!defined('_PS_VERSION_')) {
    exit;
}

/**
 * SEO utilities: canonical, hreflang, OG tags, sitemap rows.
 */
class CciBlogSeo
{
    /**
     * Build <link rel="alternate" hreflang="..."> tags for a post (all languages).
     */
    public static function hreflangTags(int $postId, int $shopId): string
    {
        $html = '';
        foreach (self::hreflangUrls($postId, $shopId) as $code => $url) {
            $html .= '<link rel="alternate" hreflang="' . htmlspecialchars($code, ENT_QUOTES, 'UTF-8') . '" href="' . htmlspecialchars($url, ENT_QUOTES, 'UTF-8') . '">' . "\n";
        }

        return $html;
    }

    /** Feed the theme's native hreflang block to avoid duplicate annotations. */
    public static function hreflangUrls(int $postId, int $shopId): array
    {
        $urls = [];
        foreach (Language::getLanguages(true, $shopId) as $lang) {
            $row = Db::getInstance()->getRow(
                'SELECT pl.slug, pl.title, pl.content, pl.intro FROM `' . _DB_PREFIX_ . 'cci_blog_post_lang` pl'
                . ' INNER JOIN `' . _DB_PREFIX_ . 'cci_blog_post` p ON p.id_post = pl.id_post'
                . ' INNER JOIN `' . _DB_PREFIX_ . 'cci_blog_post_shop` ps ON ps.id_post = p.id_post AND ps.id_shop = pl.id_shop'
                . ' WHERE pl.id_post = ' . $postId . ' AND pl.id_lang = ' . (int) $lang['id_lang'] . ' AND pl.id_shop = ' . $shopId
                . ' AND p.active = 1 AND (p.date_published IS NULL OR p.date_published <= "' . pSQL(date('Y-m-d H:i:s')) . '")'
            );
            if (!$row || trim((string) $row['slug']) === '' || trim((string) $row['title']) === ''
                || trim((string) $row['content'] . (string) $row['intro']) === '') {
                continue;
            }
            $code = (string) ($lang['language_code'] ?: $lang['iso_code']);
            $urls[$code] = Context::getContext()->link->getModuleLink('cci_blog', 'post', ['slug' => $row['slug']], true, (int) $lang['id_lang'], $shopId);
        }

        return $urls;
    }

    /**
     * Add social metadata that is not already rendered by the active
     * PrestaShop theme. The theme owns the canonical OG title, description,
     * URL, site name and type, so repeating them here would create conflicting
     * metadata for crawlers and link previews.
     */
    public static function ogTags(array $post, string $url): string
    {
        $title = htmlspecialchars(
            (string) (!empty($post['og_title']) ? $post['og_title'] : (!empty($post['meta_title']) ? $post['meta_title'] : ($post['title'] ?? ''))),
            ENT_QUOTES,
            'UTF-8'
        );
        $desc = htmlspecialchars(
            strip_tags((string) (!empty($post['og_description']) ? $post['og_description'] : (!empty($post['meta_description']) ? $post['meta_description'] : ($post['intro'] ?? '')))),
            ENT_QUOTES,
            'UTF-8'
        );
        $image = htmlspecialchars(
            (string) (!empty($post['og_image']) ? $post['og_image'] : ($post['cover_image'] ?? '')),
            ENT_QUOTES,
            'UTF-8'
        );
        $imageTag = $image !== '' ? "<meta property=\"og:image\" content=\"$image\">\n" : '';
        $twitterImageTag = $image !== '' ? "<meta name=\"twitter:image\" content=\"$image\">\n" : '';

        return "
{$imageTag}<meta name=\"twitter:card\" content=\"summary_large_image\">
<meta name=\"twitter:title\" content=\"$title\">
<meta name=\"twitter:description\" content=\"$desc\">
{$twitterImageTag}
";
    }

    /**
     * Register old slug as 301 redirect after slug change.
     */
    public static function saveSlugRedirect(string $oldSlug, int $postId, int $langId): void
    {
        $oldSlug = trim($oldSlug);
        if ($oldSlug === '' || $postId <= 0 || $langId <= 0) {
            return;
        }

        $existingPostId = (int) Db::getInstance()->getValue(
            'SELECT id_post FROM `' . _DB_PREFIX_ . 'cci_blog_slug_redirect`'
            . ' WHERE entity_type = "post" AND old_slug = "' . pSQL($oldSlug) . '" AND id_lang = ' . $langId
            . ' ORDER BY id_redirect ASC'
        );
        if ($existingPostId > 0) {
            return;
        }

        Db::getInstance()->insert('cci_blog_slug_redirect', [
            'entity_type' => 'post',
            'old_slug' => pSQL($oldSlug),
            'id_post' => $postId,
            'id_lang' => $langId,
            'date_add' => date('Y-m-d H:i:s'),
        ], false, true);
    }

    public static function saveCategorySlugRedirect(string $oldSlug, int $categoryId, int $langId): void
    {
        $oldSlug = trim($oldSlug);
        if ($oldSlug === '' || $categoryId <= 0 || $langId <= 0) {
            return;
        }

        $existingCategoryId = (int) Db::getInstance()->getValue(
            'SELECT id_category FROM `' . _DB_PREFIX_ . 'cci_blog_slug_redirect`'
            . ' WHERE entity_type = "category" AND old_slug = "' . pSQL($oldSlug) . '"'
            . ' AND id_lang = ' . $langId . ' ORDER BY id_redirect ASC'
        );
        if ($existingCategoryId > 0) {
            return;
        }

        Db::getInstance()->insert('cci_blog_slug_redirect', [
            'entity_type' => 'category',
            'old_slug' => pSQL($oldSlug),
            'id_post' => null,
            'id_category' => $categoryId,
            'id_lang' => $langId,
            'date_add' => date('Y-m-d H:i:s'),
        ], false, true);
    }

    /**
     * Return published posts assigned to the requested shop and language.
     *
     * The rows are shared by the native PrestaShop HTML sitemap integration
     * and the gsitemap XML hook. Posts do not depend on a category assignment,
     * so uncategorized articles remain discoverable.
     */
    public static function getSitemapEntries(int $langId, int $shopId): array
    {
        $sql = '
            SELECT p.id_post, p.id_category, p.date_upd, p.cover_image,
                   pl.title, pl.slug
            FROM `' . _DB_PREFIX_ . 'cci_blog_post` p
            INNER JOIN `' . _DB_PREFIX_ . "cci_blog_post_lang` pl
                ON pl.id_post = p.id_post AND pl.id_lang = $langId AND pl.id_shop = $shopId
            INNER JOIN `" . _DB_PREFIX_ . "cci_blog_post_shop` ps
                ON ps.id_post = p.id_post AND ps.id_shop = $shopId
            WHERE p.active = 1
                AND (p.date_published IS NULL OR p.date_published <= '" . pSQL(date('Y-m-d H:i:s')) . "')
            ORDER BY p.date_published DESC, p.id_post DESC
        ";

        return Db::getInstance()->executeS($sql) ?: [];
    }

    /**
     * Return active blog categories assigned to the requested shop and language.
     */
    public static function getSitemapCategoryEntries(int $langId, int $shopId): array
    {
        $sql = '
            SELECT c.id_category, c.id_parent, c.date_upd, cl.name, cl.slug
            FROM `' . _DB_PREFIX_ . 'cci_blog_category` c
            INNER JOIN `' . _DB_PREFIX_ . "cci_blog_category_lang` cl
                ON cl.id_category = c.id_category AND cl.id_lang = $langId AND cl.id_shop = $shopId
            INNER JOIN `" . _DB_PREFIX_ . "cci_blog_category_shop` cs
                ON cs.id_category = c.id_category AND cs.id_shop = $shopId
            WHERE c.active = 1
            ORDER BY c.position ASC, cl.name ASC
        ";

        return Db::getInstance()->executeS($sql) ?: [];
    }
}
