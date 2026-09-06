<?php
declare(strict_types=1);
if (!defined('_PS_VERSION_')) { exit; }

class CciBlogTag extends ObjectModel
{
    public $id_lang;
    public $name;
    public $slug;

    public static $definition = [
        'table'   => 'cci_blog_tag',
        'primary' => 'id_tag',
        'fields'  => [
            'id_lang' => ['type' => self::TYPE_INT,    'validate' => 'isUnsignedInt', 'required' => true],
            'name'    => ['type' => self::TYPE_STRING, 'validate' => 'isCleanHtml',   'size' => 128, 'required' => true],
            'slug'    => ['type' => self::TYPE_STRING, 'validate' => 'isLinkRewrite', 'size' => 128, 'required' => true],
        ],
    ];

    /**
     * Tag cloud with post counts.
     */
    public static function getCloud(int $langId, int $shopId, int $limit = 16): array
    {
        $sql = "
            SELECT t.id_tag, t.name, t.slug, COUNT(pt.id_post) AS post_count
            FROM `" . _DB_PREFIX_ . "cci_blog_tag` t
            INNER JOIN `" . _DB_PREFIX_ . "cci_blog_post_tag` pt ON pt.id_tag = t.id_tag
            INNER JOIN `" . _DB_PREFIX_ . "cci_blog_post` p
                ON p.id_post = pt.id_post
                AND p.active = 1
                AND (p.date_published IS NULL OR p.date_published <= '" . pSQL(date('Y-m-d H:i:s')) . "')
            INNER JOIN `" . _DB_PREFIX_ . "cci_blog_post_shop` ps ON ps.id_post = p.id_post AND ps.id_shop = $shopId
            WHERE t.id_lang = $langId
            GROUP BY t.id_tag
            ORDER BY post_count DESC
            LIMIT $limit
        ";

        return Db::getInstance()->executeS($sql) ?: [];
    }

    /**
     * Get or create tag by name. Returns id_tag.
     */
    public static function getOrCreate(string $name, int $langId): int
    {
        $slug = Tools::str2url($name);
        $row  = Db::getInstance()->getRow(
            "SELECT id_tag FROM `" . _DB_PREFIX_ . "cci_blog_tag` WHERE slug='" . pSQL($slug) . "' AND id_lang=$langId"
        );
        if ($row) {
            return (int) $row['id_tag'];
        }
        $tag = new self();
        $tag->id_lang = $langId;
        $tag->name    = $name;
        $tag->slug    = $slug;
        $tag->add();
        return (int) $tag->id;
    }

    public static function setPostTags(int $postId, array $tagNames, int $langId): void
    {
        Db::getInstance()->delete('cci_blog_post_tag', 'id_post = ' . $postId);
        foreach ($tagNames as $name) {
            $name = trim($name);
            if ($name === '') {
                continue;
            }
            $tagId = self::getOrCreate($name, $langId);
            Db::getInstance()->insert('cci_blog_post_tag', ['id_post' => $postId, 'id_tag' => $tagId], false, true);
        }
    }
}
