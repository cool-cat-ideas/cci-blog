<?php
declare(strict_types=1);
if (!defined('_PS_VERSION_')) { exit; }

class Cci_BlogTagModuleFrontController extends CciBlogFrontController
{
    private array $blogMeta = [];

    public function init(): void
    {
        parent::init();

        $slug = $this->getRouteSlug();
        $langId = (int) $this->context->language->id;
        $tag = Db::getInstance()->getRow(
            "SELECT * FROM `" . _DB_PREFIX_ . "cci_blog_tag`
             WHERE slug = '" . pSQL($slug) . "' AND id_lang = $langId"
        );
        if ($tag) {
            $this->blogMeta = [
                'title' => sprintf('%s – %s', $tag['name'], Configuration::get('PS_SHOP_NAME')),
                'description' => sprintf($this->module->l('Posts tagged: %s', 'tag'), $tag['name']),
            ];
            $baseUrl = $this->context->link->getModuleLink('cci_blog', 'tag', ['slug' => $tag['slug']]);
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

        // Resolve tag
        $tag = Db::getInstance()->getRow(
            "SELECT * FROM `" . _DB_PREFIX_ . "cci_blog_tag`
             WHERE slug = '" . pSQL($slug) . "' AND id_lang = $langId"
        );

        if (!$tag) {
            $this->renderNotFound();
            return;
        }

        $tagId = (int) $tag['id_tag'];
        $posts = CciBlogPost::getList($langId, $shopId, $page, $perPage, null, $tagId);
        $total = CciBlogPost::getTotal($langId, $shopId, null, $tagId);
        $pages = (int) ceil($total / $perPage);
        $baseUrl = $this->context->link->getModuleLink('cci_blog', 'tag', ['slug' => $tag['slug']]);
        $pageUrlPattern = $this->getPaginationUrlPattern($baseUrl);
        $this->redirectPaginationToCanonical($baseUrl, $page);
        $pagination = CciBlogPagination::build(
            $page,
            $perPage,
            $total,
            $baseUrl,
            [],
            $pageUrlPattern
        );

        $allCategories = CciBlogCategory::getSidebarCategories($langId, $shopId);
        $tagCloud      = CciBlogTag::getCloud($langId, $shopId);

        $this->context->smarty->assign([
            'ccb_tag'         => $tag,
            'ccb_posts'       => $posts,
            'ccb_page'        => $page,
            'ccb_pages'       => $pages,
            'ccb_total'       => $total,
            'ccb_pagination'  => $pagination,
            'listing'         => ['pagination' => $pagination],
            'ccb_categories'  => $allCategories,
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

        $this->page_name = 'module-cci_blog-tag';
        $this->blogMeta = [
            'title'       => sprintf('%s – %s', $tag['name'], Configuration::get('PS_SHOP_NAME')),
            'description' => sprintf('Posts tagged: %s', $tag['name']),
        ];

        $this->setTemplate('module:cci_blog/views/templates/front/tag.tpl');
    }

    public function getTemplateVarPage()
    {
        $page = parent::getTemplateVarPage();

        if ($this->blogMeta) {
            $page['meta']['title'] = $this->blogMeta['title'];
            $page['meta']['description'] = $this->blogMeta['description'];
        }

        return $page;
    }

    private function getRouteSlug(): string
    {
        $slug = trim((string) Tools::getValue('slug', ''));
        if ($slug !== '') {
            return $slug;
        }

        $path = trim((string) parse_url((string) ($_SERVER['REQUEST_URI'] ?? ''), PHP_URL_PATH), '/');
        if ($path === '') {
            return '';
        }

        $segments = explode('/', $path);
        $position = array_search('tag', $segments, true);
        $candidate = $position === false ? '' : trim((string) ($segments[$position + 1] ?? ''));

        return $candidate !== '' ? (preg_replace('/[^_a-zA-Z0-9-]/', '', $candidate) ?: '') : '';
    }

    private function renderNotFound(): void
    {
        header('HTTP/1.1 404 Not Found');
        header('Status: 404 Not Found');
        $this->notFound = true;
        $this->setTemplate('errors/404');
    }
}
