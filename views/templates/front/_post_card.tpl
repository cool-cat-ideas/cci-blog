{**
 * CCI Blog – Post card partial
 * Usage: {include file='module:cci_blog/views/templates/front/_post_card.tpl' post=$post}
 **}

{assign var=ccb_post_url value=$link->getModuleLink('cci_blog','post',['slug'=>$post.slug|default:''])}
{assign var=ccb_featured value=$featured|default:false}
{assign var=ccb_wide value=$wide|default:false}
{assign var=cci_blog_intro_length value=$intro_length|default:140}
{assign var=cci_blog_card_heading_level value=$heading_level|default:2}

<article class="cci-blog-card{if $ccb_featured || $ccb_wide} cci-blog-card-featured{/if}{if !isset($post.cover_image) || !$post.cover_image} cci-blog-card-without-image{/if}">
  {if isset($post.cover_image) && $post.cover_image}
  <a class="cci-blog-card-image-link" href="{$ccb_post_url|escape:'html'}" tabindex="-1" aria-hidden="true">
      <img class="cci-blog-card-image"
           src="{$post.cover_image|escape:'html'}"
           {if isset($post.cover_image_srcset) && $post.cover_image_srcset}srcset="{$post.cover_image_srcset|escape:'html'}"{/if}
           {if isset($post.cover_image_sizes) && $post.cover_image_sizes}sizes="{$post.cover_image_sizes|escape:'html'}"{/if}
           alt="{$post.title|default:''|escape:'html'}"
           loading="lazy"
           width="{if isset($post.cover_image_width) && $post.cover_image_width}{$post.cover_image_width|intval}{else}600{/if}"
           height="{if isset($post.cover_image_height) && $post.cover_image_height}{$post.cover_image_height|intval}{else}400{/if}">
  </a>
  {else}
  <a class="cci-blog-card-image-link cci-blog-card-image-placeholder"
     href="{$ccb_post_url|escape:'html'}"
     tabindex="-1"
     aria-hidden="true">
  </a>
  {/if}

  <div class="cci-blog-card-body">
    {if $ccb_featured}
      <span class="cci-blog-badge cci-blog-badge-category cci-blog-card-category">{l s='Featured article' mod='cci_blog'}</span>
    {/if}
    {if $cci_blog_card_heading_level == 3}
      <h3 class="cci-blog-card-title">
        <a href="{$ccb_post_url|escape:'html'}">
          {$post.title|default:''|escape:'html'}
        </a>
      </h3>
    {else}
      <h2 class="cci-blog-card-title">
        <a href="{$ccb_post_url|escape:'html'}">
          {$post.title|default:''|escape:'html'}
        </a>
      </h2>
    {/if}

    {if isset($post.intro) && $post.intro}
    <p class="cci-blog-card-intro">{$post.intro|strip_tags|truncate:$cci_blog_intro_length:'…'|escape:'html'}</p>
    {/if}

    <div class="cci-blog-card-meta">
      {if isset($post.category_name) && $post.category_name}
        {if isset($post.category_slug) && $post.category_slug}
          <a class="cci-blog-meta-category"
             href="{$link->getModuleLink('cci_blog','category',['slug'=>$post.category_slug])|escape:'html'}">
            {$post.category_name|escape:'html'}
          </a>
        {else}
          <span class="cci-blog-meta-category">{$post.category_name|escape:'html'}</span>
        {/if}
      {/if}
      {if isset($ccb_show_author) && $ccb_show_author && isset($post.author_name) && $post.author_name}
        <span class="cci-blog-meta-author">
          {if isset($post.author_slug) && $post.author_slug}
            {assign var=post_author_url value=$link->getModuleLink('cci_blog','author',['slug'=>$post.author_slug])}
            <a class="cci-blog-meta-author-link" href="{$post_author_url|escape:'html'}">{$post.author_name|escape:'html'}</a>
          {else}
            {$post.author_name|escape:'html'}
          {/if}
        </span>
      {/if}
      {if isset($ccb_show_date) && $ccb_show_date && isset($post.date_published) && $post.date_published}
        <time class="cci-blog-meta-date" datetime="{$post.date_published|date_format:'%Y-%m-%d'}">
          {dateFormat date=$post.date_published full=0}
        </time>
      {/if}
      {if isset($ccb_show_read_time) && $ccb_show_read_time && isset($post.reading_time) && $post.reading_time}
        <span class="cci-blog-meta-read-time">{$post.reading_time} {l s='min' mod='cci_blog'}</span>
      {/if}
      {if isset($ccb_show_views) && $ccb_show_views && isset($post.views)}
        <span class="cci-blog-meta-views">{$post.views|intval} {l s='views' mod='cci_blog'}</span>
      {/if}
    </div>

    <a class="cci-blog-card-cta btn btn-primary" href="{$ccb_post_url|escape:'html'}" aria-label="{l s='Read more: %s' sprintf=[$post.title|default:''] mod='cci_blog'|escape:'html'}">
      <span>{l s='Read more' mod='cci_blog'}</span>
      <svg class="cci-blog-card-cta-icon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true" focusable="false"><path d="M5 12h14m-6-6 6 6-6 6"/></svg>
    </a>
  </div>
</article>
