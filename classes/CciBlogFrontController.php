<?php

declare(strict_types=1);

if (!defined('_PS_VERSION_')) {
    exit;
}

/**
 * Shared storefront controller for CCI Blog pages.
 *
 * Blog templates own their optional sidebar, so theme columns must not wrap
 * them with unrelated catalog blocks or advertising modules.
 */
abstract class CciBlogFrontController extends ModuleFrontController
{
    protected string $blogCanonicalUrl = '';

    public function getLayout()
    {
        $layout = $this->context->shop->theme->getLayoutPath('layout-full-width');

        return $layout ?: parent::getLayout();
    }

    /**
     * Extend the native theme breadcrumb instead of rendering a second trail
     * inside module templates.
     */
    protected function getBreadcrumbLinks()
    {
        $breadcrumb = parent::getBreadcrumbLinks();
        $breadcrumb['links'][] = [
            'title' => $this->module->l('Blog'),
            'url' => $this->context->link->getModuleLink('cci_blog', 'list'),
        ];

        return $breadcrumb;
    }

    protected function getRequestedBlogSlug(): string
    {
        $slug = trim((string) Tools::getValue('slug', ''));

        return preg_replace('/\.html$/i', '', $slug) ?: '';
    }

    protected function getPaginationUrlPattern(string $baseUrl): string
    {
        $baseUrl = preg_replace('/\.html(?=$|[?#])/i', '', rtrim($baseUrl, '/')) ?: $baseUrl;
        $suffix = (bool) Configuration::get('CCB_URL_SUFFIX_HTML') ? '.html' : '';

        return $baseUrl . '/page/{page}' . $suffix;
    }

    protected function getCanonicalPaginationUrl(string $baseUrl, int $page): string
    {
        if ($page <= 1) {
            return $baseUrl;
        }

        return str_replace('{page}', (string) $page, $this->getPaginationUrlPattern($baseUrl));
    }

    public function getTemplateVarPage()
    {
        $page = parent::getTemplateVarPage();

        if ($this->blogCanonicalUrl !== '') {
            $page['canonical'] = $this->blogCanonicalUrl;
        }

        return $page;
    }

    protected function redirectPaginationToCanonical(
        string $baseUrl,
        int $page,
        array $query = []
    ): void {
        if ($page <= 1) {
            return;
        }

        $suffix = (bool) Configuration::get('CCB_URL_SUFFIX_HTML') ? '.html' : '';
        $path = rtrim((string) parse_url((string) ($_SERVER['REQUEST_URI'] ?? ''), PHP_URL_PATH), '/');
        $expectedEnding = '/page/' . $page . $suffix;

        if (substr($path, -strlen($expectedEnding)) === $expectedEnding) {
            return;
        }

        unset($query['page']);
        $url = str_replace('{page}', (string) $page, $this->getPaginationUrlPattern($baseUrl));
        if ($query) {
            $url .= (strpos($url, '?') === false ? '?' : '&') . http_build_query($query);
        }

        Tools::redirect($url, '', null, 'HTTP/1.1 301 Moved Permanently');
    }
}
