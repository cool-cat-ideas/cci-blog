<?php
declare(strict_types=1);
if (!defined('_PS_VERSION_')) { exit; }

class Cci_BlogFeedModuleFrontController extends ModuleFrontController
{
    public function initContent(): void
    {
        if (!(int) Configuration::get('CCB_FEED_ENABLED')) {
            header('HTTP/1.1 404 Not Found');
            exit;
        }

        $langId  = (int) $this->context->language->id;
        $shopId  = (int) $this->context->shop->id;
        $perPage = (int) Configuration::get('CCB_FEED_ITEMS') ?: 20;

        $posts     = CciBlogPost::getList($langId, $shopId, 1, $perPage);
        $shopName  = Configuration::get('PS_SHOP_NAME');
        $base      = Configuration::get('CCB_BASE_SLUG') ?: 'blog';
        $feedUrl   = $this->context->link->getModuleLink('cci_blog', 'feed', []);
        $blogUrl   = $this->context->link->getModuleLink('cci_blog', 'list', []);

        header('Content-Type: application/rss+xml; charset=UTF-8');
        header('X-Robots-Tag: noindex');

        echo '<?xml version="1.0" encoding="UTF-8"?>';
        echo '<rss version="2.0" xmlns:atom="http://www.w3.org/2005/Atom" xmlns:media="http://search.yahoo.com/mrss/">';
        echo '<channel>';
        echo '<title>' . htmlspecialchars($shopName) . ' – Blog</title>';
        echo '<link>' . htmlspecialchars($blogUrl) . '</link>';
        echo '<atom:link href="' . htmlspecialchars($feedUrl) . '" rel="self" type="application/rss+xml"/>';
        echo '<description>' . htmlspecialchars($shopName) . ' – latest blog posts</description>';
        echo '<language>' . $this->context->language->iso_code . '</language>';
        echo '<lastBuildDate>' . date(DATE_RSS) . '</lastBuildDate>';

        foreach ($posts as $post) {
            $postUrl = $this->context->link->getModuleLink('cci_blog', 'post', ['slug' => $post['slug']]);
            echo '<item>';
            echo '<title>' . htmlspecialchars($post['title']) . '</title>';
            echo '<link>' . htmlspecialchars($postUrl) . '</link>';
            echo '<guid isPermaLink="true">' . htmlspecialchars($postUrl) . '</guid>';
            echo '<pubDate>' . date(DATE_RSS, strtotime($post['date_published'] ?: $post['date_add'])) . '</pubDate>';
            echo '<description><![CDATA[' . strip_tags($post['intro'] ?? '') . ']]></description>';
            if ($post['cover_image']) {
                echo '<media:thumbnail url="' . htmlspecialchars($post['cover_image']) . '"/>';
            }
            echo '</item>';
        }

        echo '</channel></rss>';
        exit;
    }
}
