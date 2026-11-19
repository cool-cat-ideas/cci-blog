<?php

declare(strict_types=1);

if (!defined('_PS_VERSION_')) {
    exit;
}

/** Private, shop/language-scoped reader feedback. No customer login is required. */
final class CciBlogReaderFeedback
{
    public const MESSAGE_STORAGE_READY = 'CCB_READER_MESSAGE_STORAGE_READY';

    private static bool $installed = false;

    public static function install(): bool
    {
        if (self::$installed) {
            return true;
        }
        $db = Db::getInstance();
        if (!$db->execute('CREATE TABLE IF NOT EXISTS `' . _DB_PREFIX_ . 'cci_blog_reader_feedback` (
            id_feedback INT UNSIGNED NOT NULL AUTO_INCREMENT,
            id_post INT UNSIGNED NOT NULL, id_shop INT UNSIGNED NOT NULL, id_lang INT UNSIGNED NOT NULL,
            voter_hash CHAR(64) NOT NULL, network_hash CHAR(64) NOT NULL,
            helpful TINYINT UNSIGNED NOT NULL, message VARCHAR(1000) NOT NULL DEFAULT \'\',
            date_add DATETIME NOT NULL, date_upd DATETIME NOT NULL,
            PRIMARY KEY (id_feedback), UNIQUE KEY reader (id_post,id_shop,id_lang,voter_hash),
            KEY report (id_shop,id_lang,date_add), KEY throttle (network_hash,date_add)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4') || !$db->execute('CREATE TABLE IF NOT EXISTS `' . _DB_PREFIX_ . 'cci_blog_reader_message` (
            id_message INT UNSIGNED NOT NULL AUTO_INCREMENT,
            id_legacy_feedback INT UNSIGNED DEFAULT NULL,
            id_post INT UNSIGNED NOT NULL, id_shop INT UNSIGNED NOT NULL, id_lang INT UNSIGNED NOT NULL,
            message VARCHAR(1000) NOT NULL, date_add DATETIME NOT NULL,
            PRIMARY KEY (id_message), UNIQUE KEY legacy_feedback (id_legacy_feedback),
            KEY report (id_shop,id_lang,date_add), KEY article (id_post)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4')) {
            return false;
        }
        if (!Configuration::getGlobalValue(self::MESSAGE_STORAGE_READY)) {
            self::migrateMessages($db);
            if (!Configuration::updateGlobalValue(self::MESSAGE_STORAGE_READY, 1)) {
                return false;
            }
        }

        return self::$installed = true;
    }

    private static function migrateMessages(Db $db): void
    {
        $votes = '`' . _DB_PREFIX_ . 'cci_blog_reader_feedback`';
        $messages = '`' . _DB_PREFIX_ . 'cci_blog_reader_message`';
        self::inTransaction($db, static function () use ($db, $votes, $messages): void {
            if (!$db->execute('INSERT INTO ' . $messages . ' (id_legacy_feedback,id_post,id_shop,id_lang,message,date_add)
                SELECT id_feedback,id_post,id_shop,id_lang,message,date_upd FROM ' . $votes . ' WHERE message<>\'\'
                ON DUPLICATE KEY UPDATE id_message=id_message')
                || !$db->execute('UPDATE ' . $votes . ' f INNER JOIN ' . $messages . ' m ON m.id_legacy_feedback=f.id_feedback
                    SET f.message=\'\' WHERE f.message<>\'\'')) {
                throw new RuntimeException('Could not migrate reader messages.');
            }
        });
    }

    private static function inTransaction(Db $db, callable $operation): void
    {
        $link = $db->getLink();
        $nested = $link instanceof PDO && $link->inTransaction();
        $savepoint = 'cci_feedback_' . bin2hex(random_bytes(5));
        if (!$db->execute($nested ? 'SAVEPOINT ' . $savepoint : 'START TRANSACTION')) {
            throw new RuntimeException('Could not start reader feedback transaction.');
        }
        try {
            $operation();
            if (!$db->execute($nested ? 'RELEASE SAVEPOINT ' . $savepoint : 'COMMIT')) {
                throw new RuntimeException('Could not commit reader feedback.');
            }
        } catch (Throwable $error) {
            $db->execute($nested ? 'ROLLBACK TO SAVEPOINT ' . $savepoint : 'ROLLBACK');
            if ($nested) {
                $db->execute('RELEASE SAVEPOINT ' . $savepoint);
            }
            throw $error;
        }
    }

    private static function reader(Context $context): string
    {
        $reader = (string) ($context->cookie->ccb_feedback_reader ?? '');
        if (!preg_match('/^[a-f0-9]{48}$/D', $reader)) {
            $reader = bin2hex(random_bytes(24));
            $context->cookie->ccb_feedback_reader = $reader;
        }

        return $reader;
    }

    public static function token(int $postId, Context $context): string
    {
        return hash_hmac('sha256', implode(':', [$postId, (int) $context->shop->id,
            (int) $context->language->id, self::reader($context)]), _COOKIE_KEY_);
    }

    public static function currentVote(int $postId, Context $context): ?int
    {
        $voter = hash_hmac('sha256', self::reader($context), _COOKIE_KEY_);
        $row = Db::getInstance()->getRow('SELECT helpful FROM `' . _DB_PREFIX_ . 'cci_blog_reader_feedback`
            WHERE id_post=' . $postId . ' AND id_shop=' . (int) $context->shop->id . ' AND id_lang=' . (int) $context->language->id
            . ' AND voter_hash="' . pSQL($voter) . '" AND date_upd >= DATE_SUB(NOW(), INTERVAL 365 DAY)', false);

        return $row ? (int) $row['helpful'] : null;
    }

    public static function submit(int $postId, Context $context, array $input, bool $allowMessage): void
    {
        if (!is_string($input['token'] ?? null) || !hash_equals(self::token($postId, $context), $input['token'])) {
            throw new DomainException('invalid_token');
        }
        if (!in_array($input['helpful'] ?? null, ['0', '1', 0, 1], true)) {
            throw new DomainException('invalid_vote');
        }
        $shopId = (int) $context->shop->id;
        $langId = (int) $context->language->id;
        if (!self::isPublished($postId, $shopId, $langId)) {
            throw new DomainException('unavailable');
        }
        $raw = $input['message'] ?? '';
        if (!is_string($raw) || Tools::strlen($raw) > 1000) {
            throw new DomainException('invalid_message');
        }
        $message = $allowMessage ? trim(strip_tags($raw)) : '';
        $voter = hash_hmac('sha256', self::reader($context), _COOKIE_KEY_);
        // A daily keyed digest limits abuse without retaining the IP address.
        $network = hash_hmac('sha256', date('Y-m-d') . ':' . Tools::getRemoteAddr(), _COOKIE_KEY_);
        $db = Db::getInstance();
        if (self::currentVote($postId, $context) !== null) {
            throw new DomainException('already_voted');
        }
        if ((int) $db->getValue('SELECT COUNT(*) FROM `' . _DB_PREFIX_ . 'cci_blog_reader_feedback`
            WHERE network_hash = "' . pSQL($network) . '" AND date_add >= "' . date('Y-m-d') . ' 00:00:00"') >= 40) {
            throw new DomainException('rate_limited');
        }
        if (!CciBlogCaptcha::verify($context, $input['captchaToken'] ?? '', 'feedback')) {
            throw new DomainException('captcha');
        }
        self::cleanup();
        $now = date('Y-m-d H:i:s');
        $helpful = (int) $input['helpful'];
        self::inTransaction($db, static function () use ($db, $postId, $shopId, $langId, $voter, $network, $helpful, $message, $now): void {
            $sql = 'INSERT INTO `' . _DB_PREFIX_ . 'cci_blog_reader_feedback`
                (id_post,id_shop,id_lang,voter_hash,network_hash,helpful,date_add,date_upd) VALUES ('
                . $postId . ',' . $shopId . ',' . $langId . ',"' . pSQL($voter) . '","' . pSQL($network)
                . '",' . $helpful . ',"' . $now . '","' . $now . '")
                ON DUPLICATE KEY UPDATE id_feedback=id_feedback';
            if (!$db->execute($sql)) {
                throw new RuntimeException('Could not save reader vote.');
            }
            if ($db->Affected_Rows() === 0) {
                throw new DomainException('already_voted');
            }
            if ($message !== '' && !$db->insert('cci_blog_reader_message', [
                'id_post' => $postId, 'id_shop' => $shopId, 'id_lang' => $langId,
                'message' => pSQL($message), 'date_add' => $now,
            ])) {
                throw new RuntimeException('Could not save reader message.');
            }
        });
    }

    public static function cleanup(): void
    {
        if (!self::install()) {
            throw new RuntimeException('Could not prepare reader feedback storage.');
        }
        $db = Db::getInstance();
        $db->execute('UPDATE `' . _DB_PREFIX_ . 'cci_blog_reader_feedback` SET network_hash="" WHERE date_upd < DATE_SUB(NOW(), INTERVAL 90 DAY) AND network_hash<>""');
        $db->execute('DELETE FROM `' . _DB_PREFIX_ . 'cci_blog_reader_feedback` WHERE date_upd < DATE_SUB(NOW(), INTERVAL 365 DAY) AND message=""');
    }

    public static function deleteMessage(int $messageId, int $shopId, int $langId): bool
    {
        return $messageId > 0 && self::install() && Db::getInstance()->delete(
            'cci_blog_reader_message',
            'id_message=' . $messageId . ' AND id_shop=' . $shopId . ' AND id_lang=' . $langId,
            1
        );
    }

    public static function isPublished(int $postId, int $shopId, int $langId): bool
    {
        $now = (new DateTimeImmutable('now', new DateTimeZone((string) (Configuration::get('PS_TIMEZONE') ?: date_default_timezone_get()))))->format('Y-m-d H:i:s');

        return (bool) Db::getInstance()->getValue('SELECT p.id_post FROM `' . _DB_PREFIX_ . 'cci_blog_post` p
            INNER JOIN `' . _DB_PREFIX_ . 'cci_blog_post_shop` ps ON ps.id_post=p.id_post AND ps.id_shop=' . $shopId . '
            INNER JOIN `' . _DB_PREFIX_ . 'cci_blog_post_lang` pl ON pl.id_post=p.id_post AND pl.id_shop=' . $shopId . ' AND pl.id_lang=' . $langId . '
            WHERE p.id_post=' . $postId . ' AND p.active=1 AND pl.title<>"" AND pl.slug<>""
            AND (p.date_published IS NULL OR p.date_published<="' . pSQL($now) . '")');
    }

    public static function report(int $shopId, int $langId, int $page, bool $detailed): array
    {
        self::cleanup();
        $page = max(1, $page);
        $scope = 'f.id_shop=' . $shopId . ' AND f.id_lang=' . $langId;
        $join = ' FROM `' . _DB_PREFIX_ . 'cci_blog_reader_feedback` f
            INNER JOIN `' . _DB_PREFIX_ . 'cci_blog_post_shop` ps ON ps.id_post=f.id_post AND ps.id_shop=f.id_shop
            INNER JOIN `' . _DB_PREFIX_ . 'cci_blog_post_lang` pl ON pl.id_post=f.id_post AND pl.id_shop=f.id_shop AND pl.id_lang=f.id_lang WHERE ' . $scope;
        $rows = Db::getInstance()->executeS('SELECT f.id_post,MAX(pl.title) AS title,COUNT(*) AS votes,SUM(f.helpful) AS helpful,
            MAX(f.date_upd) AS latest' . $join . ' GROUP BY f.id_post ORDER BY (COUNT(*)-SUM(f.helpful)) DESC,latest DESC
            LIMIT 25 OFFSET ' . (($page - 1) * 25)) ?: [];
        $total = (int) Db::getInstance()->getValue('SELECT COUNT(DISTINCT f.id_post)' . $join);
        $messageJoin = ' FROM `' . _DB_PREFIX_ . 'cci_blog_reader_message` m
            INNER JOIN `' . _DB_PREFIX_ . 'cci_blog_post_shop` ps ON ps.id_post=m.id_post AND ps.id_shop=m.id_shop
            INNER JOIN `' . _DB_PREFIX_ . 'cci_blog_post_lang` pl ON pl.id_post=m.id_post AND pl.id_shop=m.id_shop AND pl.id_lang=m.id_lang
            WHERE m.id_shop=' . $shopId . ' AND m.id_lang=' . $langId;
        $messages = $detailed ? (Db::getInstance()->executeS('SELECT m.id_message,m.id_post,pl.title,m.message,m.date_add'
            . $messageJoin . ' ORDER BY m.date_add DESC,m.id_message DESC LIMIT 25 OFFSET ' . (($page - 1) * 25)) ?: []) : [];
        $messageTotal = $detailed ? (int) Db::getInstance()->getValue('SELECT COUNT(*)' . $messageJoin) : 0;
        $topics = $detailed ? (Db::getInstance()->executeS('SELECT cl.name,COUNT(*) AS votes,SUM(f.helpful) AS helpful
            FROM `' . _DB_PREFIX_ . 'cci_blog_reader_feedback` f
            INNER JOIN `' . _DB_PREFIX_ . 'cci_blog_post` p ON p.id_post=f.id_post
            INNER JOIN `' . _DB_PREFIX_ . 'cci_blog_post_shop` ps ON ps.id_post=p.id_post AND ps.id_shop=f.id_shop
            INNER JOIN `' . _DB_PREFIX_ . 'cci_blog_category_lang` cl ON cl.id_category=p.id_category AND cl.id_shop=f.id_shop AND cl.id_lang=f.id_lang
            WHERE ' . $scope . ' GROUP BY cl.id_category,cl.name ORDER BY (COUNT(*)-SUM(f.helpful)) DESC,COUNT(*) DESC LIMIT 25') ?: []) : [];

        return ['rows' => $rows, 'messages' => $messages, 'topics' => $topics, 'page' => $page, 'pages' => max(1, (int) ceil(max($total, $messageTotal) / 25))];
    }
}
