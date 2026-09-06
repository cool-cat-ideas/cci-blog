{**
 * Chronological navigation between published blog posts.
 **}
<nav class="cci-blog-post-navigation" aria-label="{l s='Post navigation' mod='cci_blog'}">
  {if $previous}
    <a class="cci-blog-post-navigation-item cci-blog-post-navigation-item-previous"
       href="{$link->getModuleLink('cci_blog', 'post', ['slug' => $previous.slug])|escape:'html'}"
       rel="prev">
      <span class="cci-blog-post-navigation-label">
        <svg class="cci-blog-post-navigation-icon" viewBox="0 0 20 20" width="20" height="20" aria-hidden="true" focusable="false">
          <path d="M12.75 4.75 7.5 10l5.25 5.25" fill="none" stroke="currentColor" stroke-width="1.7" stroke-linecap="round" stroke-linejoin="round"/>
        </svg>
        {l s='Previous post' mod='cci_blog'}
      </span>
      <span class="cci-blog-post-navigation-title">{$previous.title|escape:'html'}</span>
    </a>
  {/if}

  {if $next}
    <a class="cci-blog-post-navigation-item cci-blog-post-navigation-item-next"
       href="{$link->getModuleLink('cci_blog', 'post', ['slug' => $next.slug])|escape:'html'}"
       rel="next">
      <span class="cci-blog-post-navigation-label">
        {l s='Next post' mod='cci_blog'}
        <svg class="cci-blog-post-navigation-icon" viewBox="0 0 20 20" width="20" height="20" aria-hidden="true" focusable="false">
          <path d="m7.25 4.75 5.25 5.25-5.25 5.25" fill="none" stroke="currentColor" stroke-width="1.7" stroke-linecap="round" stroke-linejoin="round"/>
        </svg>
      </span>
      <span class="cci-blog-post-navigation-title">{$next.title|escape:'html'}</span>
    </a>
  {/if}
</nav>
