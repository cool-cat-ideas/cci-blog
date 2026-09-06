{**
 * CCI Blog – Search results template
 **}

{extends file='page.tpl'}

{block name='head' append}
  {include file='module:cci_blog/views/templates/front/_assets_head.tpl'}
{/block}

{block name='javascript_bottom' append}
  {include file='module:cci_blog/views/templates/front/_pagination_script.tpl'}
{/block}

{block name='content'}
<section class="cci-blog-blog-index cci-blog-blog-archive cci-blog-blog-archive-search">
<div class="cci-blog-listing {if $ccb_sidebar != 'none'}cci-blog-listing-with-sidebar cci-blog-listing-sidebar-{$ccb_sidebar|escape:'html'}{/if}">

  <main class="cci-blog-listing-main">

    <header class="cci-blog-listing-header">
      <h1 class="cci-blog-listing-title">
        {l s='Search results for:' mod='cci_blog'}
        <em>&ldquo;{$ccb_search|escape:'html'}&rdquo;</em>
      </h1>
      <span class="cci-blog-blog-index-accent" aria-hidden="true"></span>
      {if !$ccb_min_query}
        <p class="cci-blog-listing-count">{$ccb_total} {l s='posts found' mod='cci_blog'}</p>
      {/if}
    </header>

    {if $ccb_min_query}
      <p class="cci-blog-alert cci-blog-alert-error">
        {l s='Please enter at least 2 characters.' mod='cci_blog'}
      </p>
    {elseif $ccb_posts}
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

  {if $ccb_sidebar != 'none'}
    {include file='module:cci_blog/views/templates/front/_sidebar_column.tpl'}
  {/if}

</div>
</section>
{/block}
