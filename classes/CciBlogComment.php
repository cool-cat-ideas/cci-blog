<?php
declare(strict_types=1);
if (!defined('_PS_VERSION_')) { exit; }

class CciBlogComment extends ObjectModel
{
    public $id_post;
    public $id_parent;
    public $id_customer;
    public $author_name;
    public $author_email;
    public $author_website;
    public $content;
    public $status;
    public $ip_address;
    public $date_add;

    public static $definition = [
        'table'   => 'cci_blog_comment',
        'primary' => 'id_comment',
        'fields'  => [
            'id_post'        => ['type' => self::TYPE_INT,    'validate' => 'isUnsignedInt', 'required' => true],
            'id_parent'      => ['type' => self::TYPE_INT,    'validate' => 'isUnsignedInt'],
            'id_customer'    => ['type' => self::TYPE_INT,    'validate' => 'isUnsignedInt'],
            'author_name'    => ['type' => self::TYPE_STRING, 'validate' => 'isCleanHtml', 'size' => 128, 'required' => true],
            'author_email'   => ['type' => self::TYPE_STRING, 'validate' => 'isEmail',     'size' => 255, 'required' => true],
            'author_website' => ['type' => self::TYPE_STRING, 'validate' => 'isUrl',        'size' => 255],
            'content'        => ['type' => self::TYPE_STRING, 'validate' => 'isCleanHtml', 'required' => true],
            'status'         => ['type' => self::TYPE_STRING, 'validate' => 'isCleanHtml'],
            'ip_address'     => ['type' => self::TYPE_STRING, 'validate' => 'isCleanHtml', 'size' => 45],
            'date_add'       => ['type' => self::TYPE_DATE,   'validate' => 'isDateFormat'],
        ],
    ];

    /**
     * Approved comments for a post, threaded.
     */
    public static function getApprovedForPost(int $postId): array
    {
        $sql = "
            SELECT *
            FROM `" . _DB_PREFIX_ . "cci_blog_comment`
            WHERE id_post = $postId AND status = 'approved'
            ORDER BY id_parent ASC, date_add ASC
        ";
        $flat = Db::getInstance()->executeS($sql) ?: [];
        return self::buildThread($flat);
    }

    /**
     * Pending comments count (for admin badge).
     */
    public static function getPendingCount(): int
    {
        return (int) Db::getInstance()->getValue(
            "SELECT COUNT(*) FROM `" . _DB_PREFIX_ . "cci_blog_comment` WHERE status = 'pending'"
        );
    }

    private static function buildThread(array $flat, int $parentId = 0): array
    {
        $thread = [];
        foreach ($flat as $c) {
            if ((int) $c['id_parent'] === $parentId) {
                $c['replies'] = self::buildThread($flat, (int) $c['id_comment']);
                $thread[] = $c;
            }
        }
        return $thread;
    }

    /**
     * Basic spam check: honeypot + rate limiting.
     */
    public static function isSpam(string $honeypot, string $ip): bool
    {
        if ($honeypot !== '') {
            return true; // Honeypot field filled
        }
        self::purgeExpiredIpFingerprints();
        $fingerprint = self::fingerprintIp($ip);
        // Max 5 comments from the same IP per hour
        $count = (int) Db::getInstance()->getValue(
            "SELECT COUNT(*) FROM `" . _DB_PREFIX_ . "cci_blog_comment`
             WHERE ip_address = '" . pSQL($fingerprint) . "'
               AND date_add > DATE_SUB(NOW(), INTERVAL 1 HOUR)"
        );
        return $count >= 5;
    }

    public static function fingerprintIp(string $ip): string
    {
        return substr(hash_hmac('sha256', trim($ip), (string) _COOKIE_KEY_), 0, 40);
    }

    public static function purgeExpiredIpFingerprints(): void
    {
        Db::getInstance()->execute(
            "UPDATE `" . _DB_PREFIX_ . "cci_blog_comment`
             SET ip_address = ''
             WHERE ip_address <> '' AND date_add < DATE_SUB(NOW(), INTERVAL 24 HOUR)"
        );
    }

    public static function parentBelongsToPost(int $parentId, int $postId): bool
    {
        return (bool) Db::getInstance()->getValue(
            "SELECT 1 FROM `" . _DB_PREFIX_ . "cci_blog_comment`
             WHERE id_comment = " . (int) $parentId . "
               AND id_post = " . (int) $postId . "
               AND status = 'approved'"
        );
    }

    public static function isDuplicate(int $postId, string $email, string $content): bool
    {
        return (bool) Db::getInstance()->getValue(
            "SELECT 1 FROM `" . _DB_PREFIX_ . "cci_blog_comment`
             WHERE id_post = " . (int) $postId . "
               AND author_email = '" . pSQL($email) . "'
               AND content = '" . pSQL($content, true) . "'
               AND date_add > DATE_SUB(NOW(), INTERVAL 24 HOUR)"
        );
    }
}
