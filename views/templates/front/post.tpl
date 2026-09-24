{**
 * CCI Blog – Single Post Template
 * Compatible with PrestaShop 9.1.5 / Hummingbird theme conventions
 **}

{extends file='page.tpl'}

{block name='head' append}
  {include file='module:cci_blog/views/templates/front/_assets_head.tpl'}
  {if $ccb_og}{$ccb_og nofilter}{/if}
  {if $ccb_hreflang}{$ccb_hreflang nofilter}{/if}
  {if $ccb_highlight}
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/highlight.js/11.9.0/styles/github-dark.min.css">
    <script src="https://cdnjs.cloudflare.com/ajax/libs/highlight.js/11.9.0/highlight.min.js" defer></script>
    <script>document.addEventListener('DOMContentLoaded', () => hljs.highlightAll());</script>
  {/if}
{/block}

{block name='content'}
<div class="cci-blog-post-layout {if $ccb_sidebar != 'none'}cci-blog-post-layout-with-sidebar cci-blog-post-layout-sidebar-{$ccb_sidebar|escape:'html'}{/if}">
<article class="cci-blog-post" itemscope itemtype="https://schema.org/BlogPosting">
  {assign var=ccb_author_url value=''}
  {if isset($ccb_post.author_slug) && $ccb_post.author_slug}
    {assign var=ccb_author_url value=$link->getModuleLink('cci_blog','author',['slug'=>$ccb_post.author_slug])}
  {/if}

  {* --- Header --- *}
  <header class="cci-blog-post-header">
    <h1 class="cci-blog-post-title" itemprop="headline">{$ccb_post.title|escape:'html'}</h1>
    <span class="cci-blog-blog-index-accent" aria-hidden="true"></span>

    <div class="cci-blog-post-meta">
      {if $ccb_show_author && $ccb_post.author_name}
        <span class="cci-blog-meta-author" itemprop="author" itemscope itemtype="https://schema.org/Person">
          {if $ccb_post.author_avatar}
            <img class="cci-blog-meta-avatar" src="{$ccb_post.author_avatar|escape:'html'}" alt="{$ccb_post.author_name|escape:'html'}" width="32" height="32">
          {/if}
          {if $ccb_author_url}
            <a class="cci-blog-meta-author-link" href="{$ccb_author_url|escape:'html'}" itemprop="url">
              <span itemprop="name">{$ccb_post.author_name|escape:'html'}</span>
            </a>
          {else}
            <span itemprop="name">{$ccb_post.author_name|escape:'html'}</span>
          {/if}
        </span>
      {/if}
      {if $ccb_show_date && $ccb_post.date_published}
        <time class="cci-blog-meta-date" datetime="{$ccb_post.date_published|date_format:'%Y-%m-%dT%H:%M:%S'}" itemprop="datePublished">
          {dateFormat date=$ccb_post.date_published full=0}
        </time>
      {/if}
      {if $ccb_show_read_time}
        <span class="cci-blog-meta-read-time">
          {$ccb_post.reading_time} {l s='min read' mod='cci_blog'}
        </span>
      {/if}
      {if $ccb_show_views}
        <span class="cci-blog-meta-views">
          {$ccb_post.views} {l s='views' mod='cci_blog'}
        </span>
      {/if}
      {if $ccb_post.category_name && $ccb_post.category_slug}
        <a class="cci-blog-meta-category"
           href="{$link->getModuleLink('cci_blog','category',['slug'=>$ccb_post.category_slug])|escape:'html'}"
           itemprop="articleSection">
          {$ccb_post.category_name|escape:'html'}
        </a>
      {/if}
      {if $ccb_comments_on}
        <a class="cci-blog-meta-comments" href="#cci-blog-comments">
          {l s='Comments' mod='cci_blog'}
        </a>
      {/if}
    </div>
  </header>

  {* --- Cover Image --- *}
  {if $ccb_post.cover_image}
  <figure class="cci-blog-post-cover">
    <img src="{$ccb_post.cover_image|escape:'html'}"
         {if isset($ccb_post.cover_image_srcset) && $ccb_post.cover_image_srcset}srcset="{$ccb_post.cover_image_srcset|escape:'html'}"{/if}
         {if isset($ccb_post.cover_image_sizes) && $ccb_post.cover_image_sizes}sizes="{$ccb_post.cover_image_sizes|escape:'html'}"{/if}
         {if isset($ccb_post.cover_image_width) && $ccb_post.cover_image_width}width="{$ccb_post.cover_image_width|intval}"{/if}
         {if isset($ccb_post.cover_image_height) && $ccb_post.cover_image_height}height="{$ccb_post.cover_image_height|intval}"{/if}
         alt="{$ccb_post.title|escape:'html'}"
         loading="eager"
         fetchpriority="high"
         itemprop="image">
  </figure>
  {/if}

  {* --- Table of Contents --- *}
  {if $ccb_table_of_contents}
  <nav class="cci-blog-post-table-of-contents" aria-label="{l s='In this article' mod='cci_blog'}">
    <details class="cci-blog-table-of-contents-details" data-cci-blog-table-of-contents open>
      <summary class="cci-blog-table-of-contents-summary">
        <span>{l s='In this article' mod='cci_blog'}</span>
        <span class="cci-blog-table-of-contents-toggle" aria-hidden="true"></span>
      </summary>
      <div class="cci-blog-table-of-contents-content">
        {include file='module:cci_blog/views/templates/front/_table_of_contents_items.tpl' items=$ccb_table_of_contents}
      </div>
    </details>
  </nav>
  {/if}

  {* --- Content --- *}
  <div class="cci-blog-post-body" itemprop="articleBody">
    {$ccb_post.content nofilter}
  </div>

  {if $ccb_comments_on}
  <div class="cci-blog-post-discussion-link">
    <a class="btn btn-outline-primary" href="#cci-blog-comments">
      {l s='Join the discussion' mod='cci_blog'}
    </a>
  </div>
  {/if}

  {* --- Tags --- *}
  {if $ccb_post.tags}
  <div class="cci-blog-post-tags">
    <span class="cci-blog-post-tags-label">{l s='Tags:' mod='cci_blog'}</span>
    {foreach from=$ccb_post.tags item=tag}
      <a class="cci-blog-tag"
         href="{$link->getModuleLink('cci_blog','tag',['slug'=>$tag.slug])|escape:'html'}">
        #{$tag.name|escape:'html'}
      </a>
    {/foreach}
  </div>
  {/if}

  {* --- Social Share --- *}
  {if $ccb_social_share}
  <div class="cci-blog-post-share">
    <span>{l s='Share:' mod='cci_blog'}</span>
    <a class="cci-blog-share cci-blog-share-fb"
       href="https://www.facebook.com/sharer.php?u={$ccb_post_url|urlencode}"
       target="_blank" rel="noopener" aria-label="{l s='Share on Facebook' mod='cci_blog'}">
      Facebook
    </a>
    <a class="cci-blog-share cci-blog-share-tw"
       href="https://twitter.com/intent/tweet?url={$ccb_post_url|urlencode}&text={$ccb_post.title|urlencode}"
       target="_blank" rel="noopener" aria-label="{l s='Share on X (Twitter)' mod='cci_blog'}">
      X / Twitter
    </a>
    <a class="cci-blog-share cci-blog-share-li"
       href="https://www.linkedin.com/sharing/share-offsite/?url={$ccb_post_url|urlencode}"
       target="_blank" rel="noopener" aria-label="{l s='Share on LinkedIn' mod='cci_blog'}">
      LinkedIn
    </a>
  </div>
  {/if}

  {* --- Previous / Next Post --- *}
  {if $ccb_previous_post || $ccb_next_post}
    {include file='module:cci_blog/views/templates/front/_post_navigation.tpl'
      previous=$ccb_previous_post
      next=$ccb_next_post}
  {/if}

  {* --- Author Bio --- *}
  {if $ccb_show_author && $ccb_post.author_bio}
  <div class="cci-blog-post-author-bio">
    {if $ccb_post.author_avatar}
      <img src="{$ccb_post.author_avatar|escape:'html'}" class="cci-blog-author-bio-avatar" alt="{$ccb_post.author_name|escape:'html'}" width="64" height="64">
    {/if}
    <div class="cci-blog-author-bio-content">
      <strong class="cci-blog-author-bio-name">
        {if $ccb_author_url}
          <a class="cci-blog-author-bio-name-link" href="{$ccb_author_url|escape:'html'}">{$ccb_post.author_name|escape:'html'}</a>
        {else}
          {$ccb_post.author_name|escape:'html'}
        {/if}
      </strong>
      <p>{$ccb_post.author_bio|escape:'html'}</p>
      <div class="cci-blog-author-bio-links">
        {if $ccb_author_url}<a href="{$ccb_author_url|escape:'html'}">{l s='View all posts' mod='cci_blog'}</a>{/if}
        {if $ccb_post.twitter}<a href="https://twitter.com/{$ccb_post.twitter|escape:'html'}" rel="noopener" target="_blank">Twitter</a>{/if}
        {if $ccb_post.linkedin}<a href="{$ccb_post.linkedin|escape:'html'}" rel="noopener" target="_blank">LinkedIn</a>{/if}
      </div>
    </div>
  </div>
  {/if}

  {* --- Related Products --- *}
  {if $ccb_products}
  <section class="cci-blog-post-products">
    <h2>{l s='Products mentioned in this post' mod='cci_blog'}</h2>
    <div class="cci-blog-products-grid">
      {foreach from=$ccb_products item=prod}
      {include file='catalog/_partials/miniatures/product.tpl' product=$prod}
      {/foreach}
    </div>
  </section>
  {/if}

  {* --- Related Posts --- *}
  {if $ccb_related}
  <section class="cci-blog-post-related">
    <h2>{l s='You might also like' mod='cci_blog'}</h2>
    <div class="cci-blog-grid cci-blog-grid-3">
      {foreach from=$ccb_related item=rel}
      {include file='module:cci_blog/views/templates/front/_post_card.tpl' post=$rel heading_level=3}
      {/foreach}
    </div>
  </section>
  {/if}

  {* --- Comments --- *}
  {if $ccb_comments_on}
    {if $ccb_comments_provider == 'disqus'}
      {* Disqus integration *}
      <section
        id="cci-blog-comments"
        tabindex="-1"
        aria-labelledby="cci-blog-comments-title"
        class="cci-blog-post-comments cci-blog-post-comments-disqus"
        data-cci-blog-disqus
        data-disqus-shortname="{$ccb_disqus_shortname|escape:'html'}"
        data-disqus-url="{$ccb_post_url|escape:'html'}"
        data-disqus-identifier="cci-blog-post-{$ccb_post.id_post|intval}"
      >
        <h2 id="cci-blog-comments-title">{l s='Comments' mod='cci_blog'}</h2>
        <div id="disqus_thread"></div>
        <noscript>{l s='Enable JavaScript to view comments powered by Disqus.' mod='cci_blog'}</noscript>
      </section>
    {else}
      {* Native comments *}
      <section class="cci-blog-post-comments" id="cci-blog-comments" tabindex="-1" aria-labelledby="cci-blog-comments-title">
        <h2 id="cci-blog-comments-title">{l s='Comments' mod='cci_blog'} ({$ccb_post.comments|count})</h2>

        {if $ccb_comment_success}
          <div class="cci-blog-alert cci-blog-alert-success">
            {if $ccb_moderation}
              {l s='Thank you! Your comment is awaiting moderation.' mod='cci_blog'}
            {else}
              {l s='Your comment has been posted.' mod='cci_blog'}
            {/if}
          </div>
        {/if}
        {if $ccb_comment_error}
          <div class="cci-blog-alert cci-blog-alert-error">{$ccb_comment_error|escape:'html'}</div>
        {/if}

        {foreach from=$ccb_post.comments item=comment}
          {include file='module:cci_blog/views/templates/front/_comment.tpl' comment=$comment depth=0}
        {/foreach}

        {* Comment form *}
        <div class="cci-blog-comment-form">
          <p class="cci-blog-comment-form-title">{l s='Leave a comment' mod='cci_blog'}</p>
          <form method="POST" action="{$smarty.server.REQUEST_URI|escape:'html'}">
            <input type="hidden" name="submit_comment" value="1">
            <input type="hidden" name="id_parent" value="0" id="cci-blog-reply-parent">
            <input type="hidden" name="comment_token" value="{$ccb_comment_token|escape:'html'}">
            <input type="hidden" name="comment_started_at" value="{$ccb_comment_started_at|intval}">
            <input type="hidden" name="comment_form_signature" value="{$ccb_comment_form_signature|escape:'html'}">
            {* Honeypot – hidden from humans, bots fill it *}
            <input type="text" name="website_url" style="display:none" tabindex="-1" autocomplete="off">

            <div class="cci-blog-form-row cci-blog-form-row-2">
              <div class="cci-blog-form-group">
                <label for="cci-blog-author-name">{l s='Name' mod='cci_blog'} *</label>
                <input type="text" id="cci-blog-author-name" name="author_name" required maxlength="128"
                       value="{if $customer.firstname}{$customer.firstname|escape:'html'} {$customer.lastname|escape:'html'}{/if}">
              </div>
              <div class="cci-blog-form-group">
                <label for="cci-blog-author-email">{l s='Email (not published)' mod='cci_blog'} *</label>
                <input type="email" id="cci-blog-author-email" name="author_email" required maxlength="255"
                       value="{if $customer.email}{$customer.email|escape:'html'}{/if}">
              </div>
            </div>
            <div class="cci-blog-form-group">
              <label for="cci-blog-content">{l s='Comment' mod='cci_blog'} *</label>
              <textarea id="cci-blog-content" name="comment_content" rows="5" required maxlength="3000"></textarea>
            </div>
            {if $ccb_gdpr_consent}
              <div class="cci-blog-comment-consent">
                {$ccb_gdpr_consent nofilter}
              </div>
            {/if}
            <button type="submit" class="btn btn-primary">{l s='Post comment' mod='cci_blog'}</button>
          </form>
        </div>
      </section>
    {/if}
  {/if}

  {* --- Schema.org --- *}
  {if $ccb_schema}{$ccb_schema nofilter}{/if}

</article>

  {if $ccb_sidebar != 'none'}
  <aside class="cci-blog-sidebar" aria-label="{l s='Blog sidebar' mod='cci_blog'}">
    {include file='module:cci_blog/views/templates/front/_sidebar.tpl'}
  </aside>
  {/if}
</div>
{/block}
