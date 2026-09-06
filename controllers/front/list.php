<?php
declare(strict_types=1);
if (!defined('_PS_VERSION_')) { exit; }

class Cci_BlogListModuleFrontController extends CciBlogFrontController
{
    private array $blogMeta = [];

    public function init(): void
    {
        parent::init();

        $page = max(1, (int) Tools::getValue('page', 1));
        $title = $this->module->l('Blog', 'list');
        if ($page > 1) {
            $title = sprintf($this->module->l('Blog - page %d', 'list'), $page);
        }
        $this->blogMeta = [
            'title' => $title,
            'description' => $this->module->l(
                'Articles, guides and practical ideas for running and improving websites and online stores.',
                'list'
            ),
        ];
        $baseUrl = $this->context->link->getModuleLink('cci_blog', 'list', []);
        $this->blogCanonicalUrl = $this->getCanonicalPaginationUrl(
            $baseUrl,
            max(1, (int) Tools::getValue('page', 1))
        );
    }

    public function initContent(): void
    {
        parent::initContent();

        $langId  = (int) $this->context->language->id;
        $shopId  = (int) $this->context->shop->id;
        $page    = max(1, (int) Tools::getValue('page', 1));
        $perPage = (int) Configuration::get('CCB_POSTS_PER_PAGE') ?: 9;
        $search  = pSQL(Tools::getValue('q', ''));
        $baseUrl = $this->context->link->getModuleLink('cci_blog', 'list', []);
        $pageUrlPattern = $this->getPaginationUrlPattern($baseUrl);

        $this->redirectPaginationToCanonical($baseUrl, $page);

        $posts = CciBlogPost::getList($langId, $shopId, $page, $perPage, null, null, null, $search);
        $featuredPost = null;
        if ($page === 1 && $search === '' && $posts) {
            $featuredIndex = 0;
            foreach ($posts as $index => $post) {
                if (!empty($post['cover_image'])) {
                    $featuredIndex = $index;
                    break;
                }
            }

            $featuredPost = $posts[$featuredIndex];
            unset($posts[$featuredIndex]);
            $posts = array_values($posts);
        }
        $total = CciBlogPost::getTotal($langId, $shopId, null, null, null, $search);
        $pages = (int) ceil($total / $perPage);
        $pagination = CciBlogPagination::build(
            $page,
            $perPage,
            $total,
            $baseUrl,
            [],
            $pageUrlPattern
        );

        $categories = CciBlogCategory::getSidebarCategories($langId, $shopId);
        $tagCloud   = CciBlogTag::getCloud($langId, $shopId);

        $this->context->smarty->assign([
            'ccb_posts'      => $posts,
            'ccb_featured_post' => $featuredPost,
            'ccb_page'       => $page,
            'ccb_pages'      => $pages,
            'ccb_total'      => $total,
            'ccb_pagination' => $pagination,
            'listing'        => ['pagination' => $pagination],
            'ccb_search'     => $search,
            'ccb_categories' => $categories,
            'ccb_tag_cloud'  => $tagCloud,
            'ccb_base'       => Configuration::get('CCB_BASE_SLUG'),
            'ccb_layout'     => Configuration::get('CCB_LAYOUT'),
            'ccb_sidebar'    => Configuration::get('CCB_SIDEBAR_POSITION'),
            'ccb_show_author'    => (bool) Configuration::get('CCB_SHOW_AUTHOR'),
            'ccb_show_date'      => (bool) Configuration::get('CCB_SHOW_DATE'),
            'ccb_show_views'     => (bool) Configuration::get('CCB_SHOW_VIEWS'),
            'ccb_show_read_time' => (bool) Configuration::get('CCB_SHOW_READ_TIME'),
            'ccb_feed_enabled'   => (bool) Configuration::get('CCB_FEED_ENABLED'),
        ]);

        $this->page_name = 'module-cci_blog-list';
        $this->setTemplate('module:cci_blog/views/templates/front/list.tpl');
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
}
