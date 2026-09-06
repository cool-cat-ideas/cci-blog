{**
 * Responsive sidebar column. Its children become grid items on mobile, which
 * keeps the single search form above posts and category widgets below them.
 **}
<aside class="cci-blog-listing-aside">
  {if !isset($ccb_sidebar_search) || $ccb_sidebar_search}
    <div class="cci-blog-listing-search">
      {include file='module:cci_blog/views/templates/front/_search_widget.tpl' ccb_search_input_id='cci-blog-blog-search'}
    </div>
  {/if}
  <div class="cci-blog-sidebar cci-blog-sidebar-widgets">
    {include file='module:cci_blog/views/templates/front/_sidebar.tpl' ccb_show_sidebar_search=false}
  </div>
</aside>
