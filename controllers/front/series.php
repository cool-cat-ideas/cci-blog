<?php

declare(strict_types=1);

if (!defined('_PS_VERSION_')) {
    exit;
}

class Cci_BlogSeriesModuleFrontController extends CciBlogFrontController
{
    private array $series = [];

    public function init(): void
    {
        parent::init();
        $page = max(1, min(10000, (int) Tools::getValue('page', 1)));
        $pro = Module::isEnabled('cci_blog_pro') ? Module::getInstanceByName('cci_blog_pro') : null;
        $this->series = $pro && method_exists($pro, 'getCciReaderSeries')
            ? $pro->getCciReaderSeries($this->getRequestedBlogSlug(), (int) $this->context->shop->id, (int) $this->context->language->id, $page) : [];
        if ($this->series && $this->series['items']) {
            $base = $this->context->link->getModuleLink('cci_blog', 'series', ['slug' => $this->series['slug']]);
            $this->blogCanonicalUrl = $page === 1 ? $base : $base . (str_contains($base, '?') ? '&' : '?') . 'page=' . $page;
        }
    }

    public function initContent(): void
    {
        parent::initContent();
        $page = max(1, min(10000, (int) Tools::getValue('page', 1)));
        if (!$this->series || !$this->series['items']) {
            http_response_code(404);
            $this->setTemplate('errors/404');

            return;
        }
        $base = $this->context->link->getModuleLink('cci_blog', 'series', ['slug' => $this->series['slug']]);
        $this->blogCanonicalUrl = $page === 1 ? $base : $base . (str_contains($base, '?') ? '&' : '?') . 'page=' . $page;
        $this->context->smarty->assign([
            'ccb_series' => $this->series, 'ccb_series_page' => $page, 'ccb_series_url' => $base,
            'ccb_series_previous' => $page > 1 ? $base . (str_contains($base, '?') ? '&' : '?') . 'page=' . ($page - 1) : '',
            'ccb_series_next' => $this->series['has_next'] ? $base . (str_contains($base, '?') ? '&' : '?') . 'page=' . ($page + 1) : '',
        ]);
        $this->setTemplate('module:cci_blog/views/templates/front/series.tpl');
    }

    public function getTemplateVarPage()
    {
        $page = parent::getTemplateVarPage();
        if ($this->series) {
            $page['meta']['title'] = $this->series['title'];
            $page['meta']['description'] = $this->series['description'];
        }

        return $page;
    }
}
