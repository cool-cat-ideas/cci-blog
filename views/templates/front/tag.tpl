{**
 * CCI Blog – Tag archive template
 **}

{extends file='page.tpl'}

{block name='head' append}
  {include file='module:cci_blog/views/templates/front/_assets_head.tpl'}
{/block}

{block name='javascript_bottom' append}
  {include file='module:cci_blog/views/templates/front/_pagination_script.tpl'}
{/block}

{block name='content'}
<section class="cci-blog-blog-index cci-blog-blog-archive cci-blog-blog-archive-tag">
  <header class="cci-blog-blog-index-header cci-blog-blog-archive-header">
    <h1 class="cci-blog-listing-title cci-blog-blog-archive-title">
      <span class="cci-blog-blog-archive-tag-mark" aria-hidden="true">#</span>
      <span>{$ccb_tag.name|escape:'html'}</span>
    </h1>
    <span class="cci-blog-blog-index-accent" aria-hidden="true"></span>
    <p class="cci-blog-listing-count cci-blog-blog-archive-count">
      {l s='Posts:' mod='cci_blog'} <strong>{$ccb_total|intval}</strong>
    </p>
  </header>

<div class="cci-blog-listing {if $ccb_sidebar != 'none'}cci-blog-listing-with-sidebar cci-blog-listing-sidebar-{$ccb_sidebar|escape:'html'}{/if}">

  <main class="cci-blog-listing-main">
    {if $ccb_posts}
      {if $ccb_posts|count == 1}
        <div class="cci-blog-featured-post cci-blog-blog-archive-single-post">
          {foreach from=$ccb_posts item=post}
            {include file='module:cci_blog/views/templates/front/_post_card.tpl' post=$post wide=true}
          {/foreach}
        </div>
      {else}
        <div class="cci-blog-posts-grid cci-blog-posts-grid-{$ccb_layout|escape:'html'}">
          {foreach from=$ccb_posts item=post}
            {include file='module:cci_blog/views/templates/front/_post_card.tpl' post=$post}
          {/foreach}
        </div>
      {/if}

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
