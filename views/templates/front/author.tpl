{**
 * CCI Blog – Author archive template
 **}

{extends file='page.tpl'}

{block name='head' append}
  {include file='module:cci_blog/views/templates/front/_assets_head.tpl'}
{/block}

{block name='javascript_bottom' append}
  {include file='module:cci_blog/views/templates/front/_pagination_script.tpl'}
{/block}

{block name='content'}
<section class="cci-blog-blog-index cci-blog-blog-archive cci-blog-blog-archive-author">
<div class="cci-blog-listing {if $ccb_sidebar != 'none'}cci-blog-listing-with-sidebar cci-blog-listing-sidebar-{$ccb_sidebar|escape:'html'}{/if}">

  <main class="cci-blog-listing-main">

    <section class="cci-blog-author-profile" aria-labelledby="cci-blog-author-name">
      {if $ccb_author.avatar}
        <img class="cci-blog-author-profile-avatar"
             src="{$ccb_author.avatar|escape:'html'}"
             alt="{$ccb_author.display_name|escape:'html'}"
             width="96" height="96">
      {else}
        <div class="cci-blog-author-profile-avatar cci-blog-author-profile-avatar-initials" aria-hidden="true">
          {$ccb_author.initials|default:'?'|escape:'html'}
        </div>
      {/if}
      <div class="cci-blog-author-profile-info">
        <p class="cci-blog-author-profile-label">{l s='Author' mod='cci_blog'}</p>
        <h1 class="cci-blog-author-profile-name" id="cci-blog-author-name">{$ccb_author.display_name|escape:'html'}</h1>
        <span class="cci-blog-blog-index-accent" aria-hidden="true"></span>
        {if $ccb_author.bio}
          <p class="cci-blog-author-profile-bio">{$ccb_author.bio|escape:'html'}</p>
        {/if}

        <dl class="cci-blog-author-profile-stats" aria-label="{l s='Author statistics' mod='cci_blog'}">
          <div class="cci-blog-author-profile-stat">
            <dt>{l s='Published posts' mod='cci_blog'}</dt>
            <dd>{$ccb_total|intval}</dd>
          </div>
          <div class="cci-blog-author-profile-stat">
            <dt>{l s='Total views' mod='cci_blog'}</dt>
            <dd>{$ccb_author_stats.total_views|default:0|intval}</dd>
          </div>
          {if isset($ccb_author_stats.latest_published) && $ccb_author_stats.latest_published && $ccb_author_stats.latest_published != '0000-00-00 00:00:00'}
            <div class="cci-blog-author-profile-stat">
              <dt>{l s='Latest post' mod='cci_blog'}</dt>
              <dd>{dateFormat date=$ccb_author_stats.latest_published full=0}</dd>
            </div>
          {/if}
        </dl>

        <div class="cci-blog-author-profile-links">
          {if $ccb_author.twitter}
            <a href="https://twitter.com/{$ccb_author.twitter|escape:'html'}" target="_blank" rel="noopener" class="cci-blog-author-social">
              X / Twitter
            </a>
          {/if}
          {if $ccb_author.linkedin}
            <a href="{$ccb_author.linkedin|escape:'html'}" target="_blank" rel="noopener" class="cci-blog-author-social">
              LinkedIn
            </a>
          {/if}
        </div>
      </div>
    </section>

    {if $ccb_posts}
    <div class="cci-blog-author-posts-header">
      <h2>{l s='Published articles' mod='cci_blog'}</h2>
      <p>{$ccb_total|intval} {l s='posts found' mod='cci_blog'}</p>
    </div>

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
