{block name='cci_blog_article_sidebar_content'}
  {if $ccb_toc_sidebar_widgets || $ccb_toc_sidebar_hook}
    <div class="cci-blog-article-sidebar-content">
      {block name='cci_blog_article_sidebar_widgets'}
        {if $ccb_toc_sidebar_widgets}
          {include file='module:cci_blog/views/templates/front/_sidebar.tpl' ccb_search_input_id='cci-blog-article-sidebar-search'}
        {/if}
      {/block}
      {block name='cci_blog_article_sidebar_hook'}
        {if $ccb_toc_sidebar_hook}
          <div class="cci-blog-article-sidebar-hook">{$ccb_toc_sidebar_hook nofilter}</div>
        {/if}
      {/block}
    </div>
  {/if}
{/block}
