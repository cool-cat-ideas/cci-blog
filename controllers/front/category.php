<?php
declare(strict_types=1);
if (!defined('_PS_VERSION_')) { exit; }

class Cci_BlogCategoryModuleFrontController extends CciBlogFrontController
{
    private array $blogMeta = [];

    public function init(): void
    {
        parent::init();

        $slug = preg_replace('/\.html$/i', '', $this->getRouteSlug()) ?: '';
        $category = CciBlogCategory::getBySlug(
            $slug,
            (int) $this->context->language->id,
            (int) $this->context->shop->id
        );
        if ($category) {
            $this->blogMeta = [
                'title' => $category['meta_title'] ?: $category['name'],
                'description' => $category['meta_description'] ?: $category['description'],
                'keywords' => $category['meta_keywords'] ?? '',
            ];
            $baseUrl = $this->context->link->getModuleLink(
                'cci_blog',
                'category',
                ['slug' => $category['slug']]
            );
            $this->blogCanonicalUrl = $this->getCanonicalPaginationUrl(
                $baseUrl,
                max(1, (int) Tools::getValue('page', 1))
            );
        }
    }

    public function initContent(): void
    {
        parent::initContent();

        $slug    = $this->getRouteSlug();
        $langId  = (int) $this->context->language->id;
        $shopId  = (int) $this->context->shop->id;
        $page    = max(1, (int) Tools::getValue('page', 1));
        $perPage = (int) Configuration::get('CCB_POSTS_PER_PAGE') ?: 9;

        $this->redirectLegacyCategory($slug, $langId, $shopId);
        $slug = preg_replace('/\.html$/i', '', $slug) ?: '';
        $category = CciBlogCategory::getBySlug($slug, $langId, $shopId);
        if (!$category) {
            $this->renderNotFound();
            return;
        }

        $baseUrl = $this->context->link->getModuleLink(
            'cci_blog',
            'category',
            ['slug' => $category['slug']]
        );
        $pageUrlPattern = $this->getPaginationUrlPattern($baseUrl);
        if ($page > 1) {
            $this->redirectPaginationToCanonical($baseUrl, $page);
        } elseif ($this->isHtmlRequest() !== (bool) Configuration::get('CCB_URL_SUFFIX_HTML')) {
            Tools::redirect($baseUrl, '', null, 'HTTP/1.1 301 Moved Permanently');
        }

        $catId = (int) $category['id_category'];
        $posts = CciBlogPost::getList($langId, $shopId, $page, $perPage, $catId);
        $total = CciBlogPost::getTotal($langId, $shopId, $catId);
        $pages = (int) ceil($total / $perPage);
        $pagination = CciBlogPagination::build(
            $page,
            $perPage,
            $total,
            $baseUrl,
            [],
            $pageUrlPattern
        );

        $allCategories = CciBlogCategory::getAllWithCount($langId, $shopId);
        $tagCloud      = CciBlogTag::getCloud($langId, $shopId);

        // Build subcategory tree for sidebar
        $tree = CciBlogCategory::buildTree($allCategories, $catId);

        $this->context->smarty->assign([
            'ccb_category'    => $category,
            'ccb_posts'       => $posts,
            'ccb_page'        => $page,
            'ccb_pages'       => $pages,
            'ccb_total'       => $total,
            'ccb_pagination'  => $pagination,
            'listing'         => ['pagination' => $pagination],
            'ccb_categories'  => CciBlogCategory::getSidebarCategories($langId, $shopId),
            'ccb_subcategories' => $tree,
            'ccb_tag_cloud'   => $tagCloud,
            'ccb_base'        => Configuration::get('CCB_BASE_SLUG'),
            'ccb_layout'      => Configuration::get('CCB_LAYOUT'),
            'ccb_sidebar'     => Configuration::get('CCB_SIDEBAR_POSITION'),
            'ccb_show_author'    => (bool) Configuration::get('CCB_SHOW_AUTHOR'),
            'ccb_show_date'      => (bool) Configuration::get('CCB_SHOW_DATE'),
            'ccb_show_views'     => (bool) Configuration::get('CCB_SHOW_VIEWS'),
            'ccb_show_read_time' => (bool) Configuration::get('CCB_SHOW_READ_TIME'),
            'ccb_breadcrumb'  => (bool) Configuration::get('CCB_BREADCRUMB'),
        ]);

        $this->page_name = 'module-cci_blog-category';
        $this->blogMeta = [
            'title'       => $category['meta_title']       ?: $category['name'],
            'description' => $category['meta_description'] ?: $category['description'],
            'keywords'    => $category['meta_keywords']    ?? '',
        ];

        $this->setTemplate('module:cci_blog/views/templates/front/category.tpl');
    }

    public function getTemplateVarPage()
    {
        $page = parent::getTemplateVarPage();

        if ($this->blogMeta) {
            $page['meta']['title'] = $this->blogMeta['title'];
            $page['meta']['description'] = strip_tags((string) $this->blogMeta['description']);
            if (!empty($this->blogMeta['keywords'])) {
                $page['meta']['keywords'] = $this->blogMeta['keywords'];
            }
        }

        return $page;
    }

    protected function getBreadcrumbLinks()
    {
        $breadcrumb = parent::getBreadcrumbLinks();
        $category = CciBlogCategory::getBySlug(
            preg_replace('/\.html$/i', '', $this->getRouteSlug()) ?: '',
            (int) $this->context->language->id,
            (int) $this->context->shop->id
        );
        if ($category) {
            $breadcrumb['links'][] = [
                'title' => (string) $category['name'],
                'url' => $this->context->link->getModuleLink('cci_blog', 'category', ['slug' => $category['slug']]),
            ];
        }

        return $breadcrumb;
    }

    protected function getRouteSlug(): string
    {
        $slug = trim((string) Tools::getValue('slug', ''));
        if ($slug !== '') {
            return $slug;
        }

        return $this->extractSlugAfterSegment('category');
    }

    private function extractSlugAfterSegment(string $segment): string
    {
        $path = trim((string) parse_url((string) ($_SERVER['REQUEST_URI'] ?? ''), PHP_URL_PATH), '/');
        if ($path === '') {
            return '';
        }

        $segments = explode('/', $path);
        $position = array_search($segment, $segments, true);
        $candidate = $position === false ? '' : trim((string) ($segments[$position + 1] ?? ''));
        $candidate = preg_replace('/\.html$/i', '', $candidate) ?: '';

        return $candidate !== '' ? (preg_replace('/[^_a-zA-Z0-9-]/', '', $candidate) ?: '') : '';
    }

    private function redirectLegacyCategory(string $slug, int $langId, int $shopId): void
    {
        if (!$this->supportsCategoryRedirects()) {
            return;
        }

        $legacySlug = preg_replace('/\.html$/i', '', trim($slug)) ?: '';
        if ($legacySlug === '') {
            return;
        }

        $redirect = Db::getInstance()->getRow(
            'SELECT id_category FROM `' . _DB_PREFIX_ . 'cci_blog_slug_redirect`'
            . ' WHERE entity_type = "category" AND old_slug = "' . pSQL($legacySlug) . '"'
            . ' AND id_lang = ' . $langId . ' ORDER BY id_redirect ASC'
        );
        if (!$redirect) {
            return;
        }

        $newSlug = Db::getInstance()->getValue(
            'SELECT slug FROM `' . _DB_PREFIX_ . 'cci_blog_category_lang`'
            . ' WHERE id_category = ' . (int) $redirect['id_category']
            . ' AND id_lang = ' . $langId . ' AND id_shop = ' . $shopId
        );
        if ($newSlug && $newSlug !== $legacySlug) {
            $params = ['slug' => $newSlug];
            $page = max(1, (int) Tools::getValue('page', 1));
            if ($page > 1) {
                $params['page'] = $page;
            }
            $url = $this->context->link->getModuleLink('cci_blog', 'category', $params);
            Tools::redirect($url, '', null, 'HTTP/1.1 301 Moved Permanently');
        }
    }

    private function supportsCategoryRedirects(): bool
    {
        return (bool) Db::getInstance()->getValue(
            'SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS'
            . ' WHERE TABLE_SCHEMA = DATABASE()'
            . ' AND TABLE_NAME = "' . _DB_PREFIX_ . 'cci_blog_slug_redirect"'
            . ' AND COLUMN_NAME = "entity_type"'
        );
    }

    private function isHtmlRequest(): bool
    {
        $path = (string) parse_url((string) ($_SERVER['REQUEST_URI'] ?? ''), PHP_URL_PATH);

        return (bool) preg_match('/\.html$/i', rtrim($path, '/'));
    }

    private function renderNotFound(): void
    {
        header('HTTP/1.1 404 Not Found');
        header('Status: 404 Not Found');
        $this->notFound = true;
        $this->setTemplate('errors/404');
    }
}
