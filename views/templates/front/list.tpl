{**
 * CCI Blog – Post listing template
 **}

{extends file='page.tpl'}

{block name='head' append}
  {include file='module:cci_blog/views/templates/front/_assets_head.tpl'}
{/block}

{block name='javascript_bottom' append}
  {include file='module:cci_blog/views/templates/front/_pagination_script.tpl'}
{/block}

{block name='content'}
<section class="cci-blog-blog-index">
  <header class="cci-blog-blog-index-header">
    <h1 class="cci-blog-listing-title">{l s='Blog' mod='cci_blog'}</h1>
    <span class="cci-blog-blog-index-accent" aria-hidden="true"></span>
    <p class="cci-blog-blog-index-lead">
      {l s='Practical knowledge, inspiration and advice from the world of printing, advertising and large-format materials.' mod='cci_blog'}
      {l s='Proven solutions that help you choose the best materials and technologies.' mod='cci_blog'}
    </p>

  </header>

<div class="cci-blog-listing {if $ccb_sidebar != 'none'}cci-blog-listing-with-sidebar cci-blog-listing-sidebar-{$ccb_sidebar|escape:'html'}{/if}">

  <main class="cci-blog-listing-main">

    {* --- Search results heading --- *}
    {if $ccb_search}
    <div class="cci-blog-search-heading">
      <h2>{l s='Search results for:' mod='cci_blog'} <em>{$ccb_search|escape:'html'}</em></h2>
      <p>{$ccb_total} {l s='posts found' mod='cci_blog'}</p>
    </div>
    {/if}

    {if $ccb_featured_post}
      <div class="cci-blog-featured-post">
        {include file='module:cci_blog/views/templates/front/_post_card.tpl' post=$ccb_featured_post featured=true}
      </div>
    {/if}

    {* --- Posts grid/list --- *}
    {if $ccb_posts}
    <div class="cci-blog-posts-grid cci-blog-posts-grid-{$ccb_layout|escape:'html'}">
      {foreach from=$ccb_posts item=post}
        {include file='module:cci_blog/views/templates/front/_post_card.tpl' post=$post}
      {/foreach}
    </div>

    {if isset($ccb_pagination) && $ccb_pagination.should_be_displayed}
      {include file='_partials/pagination.tpl' pagination=$ccb_pagination}
    {/if}

    {else}
    <p class="cci-blog-no-posts">{l s='No posts found.' mod='cci_blog'}</p>
    {/if}

  </main>

  {* --- Sidebar --- *}
  {if $ccb_sidebar != 'none'}
    {include file='module:cci_blog/views/templates/front/_sidebar_column.tpl'}
  {/if}

</div>
</section>
{/block}
