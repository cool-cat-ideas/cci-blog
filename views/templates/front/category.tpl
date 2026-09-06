{**
 * CCI Blog – Category archive template
 **}

{extends file='page.tpl'}

{block name='head' append}
  {include file='module:cci_blog/views/templates/front/_assets_head.tpl'}
{/block}

{block name='javascript_bottom' append}
  {include file='module:cci_blog/views/templates/front/_pagination_script.tpl'}
{/block}

{block name='content'}
<section class="cci-blog-blog-index cci-blog-blog-archive cci-blog-blog-archive-category">
<div class="cci-blog-listing {if $ccb_sidebar != 'none'}cci-blog-listing-with-sidebar cci-blog-listing-sidebar-{$ccb_sidebar|escape:'html'}{/if}">

  <main class="cci-blog-listing-main">

    <header class="cci-blog-listing-header">
      <h1 class="cci-blog-listing-title">{$ccb_category.name|escape:'html'}</h1>
      <span class="cci-blog-blog-index-accent" aria-hidden="true"></span>
      {if $ccb_category.description}
        <div class="cci-blog-listing-desc">{$ccb_category.description nofilter}</div>
      {/if}
      <p class="cci-blog-listing-count">
        {$ccb_total} {l s='posts found' mod='cci_blog'}
      </p>
    </header>

    {* Subcategories *}
    {if $ccb_subcategories}
    <div class="cci-blog-subcategory-list">
      {foreach from=$ccb_subcategories item=sub}
        <a class="cci-blog-badge cci-blog-badge-sub"
           href="{$link->getModuleLink('cci_blog','category',['slug'=>$sub.slug])|escape:'html'}">
          {$sub.name|escape:'html'} ({$sub.post_count})
        </a>
      {/foreach}
    </div>
    {/if}

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

  {if $ccb_sidebar != 'none'}
    {include file='module:cci_blog/views/templates/front/_sidebar_column.tpl'}
  {/if}

</div>
</section>
{/block}
