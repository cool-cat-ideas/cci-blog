<?php
declare(strict_types=1);
if (!defined('_PS_VERSION_')) { exit; }

class Cci_BlogSearchModuleFrontController extends CciBlogFrontController
{
    private array $blogMeta = [];

    public function init(): void
    {
        parent::init();

        $this->blogMeta = [
            'title' => sprintf('%s: %s', $this->module->l('Search'), trim((string) Tools::getValue('q', ''))),
            'robots' => 'noindex,follow',
        ];
    }

    public function initContent(): void
    {
        parent::initContent();

        $query   = trim(Tools::getValue('q', ''));
        $langId  = (int) $this->context->language->id;
        $shopId  = (int) $this->context->shop->id;
        $page    = max(1, (int) Tools::getValue('page', 1));
        $perPage = (int) Configuration::get('CCB_POSTS_PER_PAGE') ?: 9;

        $posts = [];
        $total = 0;
        $pages = 0;

        if (mb_strlen($query) >= 2) {
            $posts = CciBlogPost::getList($langId, $shopId, $page, $perPage, null, null, null, $query);
            $total = CciBlogPost::getTotal($langId, $shopId, null, null, null, $query);
            $pages = (int) ceil($total / $perPage);
        }
        $baseUrl = $this->context->link->getModuleLink('cci_blog', 'search', []);
        $pageUrlPattern = $this->getPaginationUrlPattern($baseUrl);
        $this->redirectPaginationToCanonical($baseUrl, $page, ['q' => $query]);
        $pagination = CciBlogPagination::build(
            $page,
            $perPage,
            $total,
            $baseUrl,
            ['q' => $query],
            $pageUrlPattern
        );

        $allCategories = CciBlogCategory::getSidebarCategories($langId, $shopId);
        $tagCloud      = CciBlogTag::getCloud($langId, $shopId);

        $this->context->smarty->assign([
            'ccb_search'      => $query,
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
            'ccb_min_query'   => mb_strlen($query) < 2,
        ]);

        $this->page_name = 'module-cci_blog-search';
        $this->blogMeta = [
            'title' => sprintf('%s: %s', $this->module->l('Search'), $query),
            'robots' => 'noindex,follow',
        ];

        $this->setTemplate('module:cci_blog/views/templates/front/search.tpl');
    }

    public function getTemplateVarPage()
    {
        $page = parent::getTemplateVarPage();

        if ($this->blogMeta) {
            $page['meta']['title'] = $this->blogMeta['title'];
            $page['meta']['robots'] = $this->blogMeta['robots'];
        }

        return $page;
    }
}
