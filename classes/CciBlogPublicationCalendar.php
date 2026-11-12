<?php

declare(strict_types=1);

if (!defined('_PS_VERSION_')) {
    exit;
}

/** Read-only, shop-scoped publication dates for the administration calendar. */
final class CciBlogPublicationCalendar
{
    public static function month(int $shopId, int $langId, string $month): array
    {
        $start = DateTimeImmutable::createFromFormat('!Y-m', $month, new DateTimeZone('UTC'));
        if (!$start || $start->format('Y-m') !== $month || $month < '1900-01' || $month > '2199-12') {
            throw new InvalidArgumentException('Choose a valid calendar month.');
        }
        $end = $start->modify('+1 month');
        $posts = Db::getInstance()->executeS(
            'SELECT p.id_post, p.active, p.date_published, pl.title, pl.id_lang'
            . ' FROM `' . _DB_PREFIX_ . 'cci_blog_post` p'
            . ' INNER JOIN `' . _DB_PREFIX_ . 'cci_blog_post_shop` ps ON ps.id_post=p.id_post AND ps.id_shop=' . $shopId
            . ' INNER JOIN `' . _DB_PREFIX_ . 'cci_blog_post_lang` pl ON pl.id_post=p.id_post AND pl.id_shop=' . $shopId . ' AND pl.id_lang=' . $langId
            . ' WHERE p.active=1 AND p.date_published >= "' . pSQL($start->format('Y-m-d H:i:s')) . '"'
            . ' AND p.date_published < "' . pSQL($end->format('Y-m-d H:i:s')) . '"'
            . ' ORDER BY p.date_published ASC, p.id_post ASC'
        );
        if ($posts === false) {
            throw new RuntimeException('The publication calendar could not be loaded.');
        }

        return ['month' => $month, 'posts' => $posts];
    }
}
