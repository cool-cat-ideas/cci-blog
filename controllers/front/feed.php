<?php

declare(strict_types=1);
if (!defined('_PS_VERSION_')) {
    exit;
}

class Cci_BlogFeedModuleFrontController extends ModuleFrontController
{
    public function initContent(): void
    {
        if (!(int) Configuration::get('CCB_FEED_ENABLED')) {
            http_response_code(404);
            exit;
        }

        $langId = (int) $this->context->language->id;
        $shopId = (int) $this->context->shop->id;
        $posts = CciBlogPost::getList($langId, $shopId, 1, max(1, min(100, (int) (Configuration::get('CCB_FEED_ITEMS') ?: 20))));
        foreach ($posts as &$post) {
            $post['url'] = $this->context->link->getModuleLink('cci_blog', 'post', ['slug' => $post['slug']], true, $langId, $shopId);
            if (!empty($post['cover_image']) && str_starts_with($post['cover_image'], '/')) {
                $post['cover_image'] = $this->context->link->getMediaLink($post['cover_image']);
            }
        }
        unset($post);
        $shopName = (string) Configuration::get('PS_SHOP_NAME');
        $xml = CciBlogFeed::render($posts, [
            'title' => $shopName . ' - Blog',
            'description' => $shopName . ' - ' . $this->module->l('Latest blog posts'),
            'url' => $this->context->link->getModuleLink('cci_blog', 'list', [], true, $langId, $shopId),
            'feedUrl' => $this->context->link->getModuleLink('cci_blog', 'feed', [], true, $langId, $shopId),
            'language' => (string) ($this->context->language->language_code ?: $this->context->language->iso_code),
            'identity' => substr(hash('sha256', _COOKIE_KEY_), 0, 24),
            'shopId' => $shopId,
            'langId' => $langId,
        ]);
        $validators = CciBlogFeed::validators($xml, $shopId . ':' . $langId);
        $etag = $validators['etag'];
        $modified = $validators['modified'];
        header('Content-Type: application/rss+xml; charset=UTF-8');
        header('X-Robots-Tag: noindex');
        header('Cache-Control: public, max-age=300');
        header('ETag: ' . $etag);
        if ($modified > 0) {
            header('Last-Modified: ' . gmdate('D, d M Y H:i:s', $modified) . ' GMT');
        }
        if (CciBlogFeed::notModified($etag, $modified, $_SERVER)) {
            http_response_code(304);
            exit;
        }
        echo $xml;
        exit;
    }
}
