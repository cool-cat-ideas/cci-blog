<div class="cci-blog-widget cci-blog-widget-search">
  <h3 class="cci-blog-widget-title">{l s='Search the blog' mod='cci_blog'}</h3>
  <form class="cci-blog-search-form" method="GET"
        action="{$link->getModuleLink('cci_blog','search',[])|escape:'html'}">
    <label class="sr-only" for="{$ccb_search_input_id|default:'cci-blog-sidebar-search'|escape:'html'}">
      {l s='Search posts' mod='cci_blog'}
    </label>
    <input id="{$ccb_search_input_id|default:'cci-blog-sidebar-search'|escape:'html'}"
           type="search"
           name="q"
           value="{$ccb_search|default:''|escape:'html'}"
           placeholder="{l s='Enter a keyword…' mod='cci_blog'}"
           class="cci-blog-search-form-input">
    <button type="submit" class="cci-blog-search-form-btn" aria-label="{l s='Search' mod='cci_blog'}">
      <svg width="18" height="18" viewBox="0 0 24 24" fill="none"
           stroke="currentColor" stroke-width="2" aria-hidden="true">
        <circle cx="11" cy="11" r="8"/>
        <path d="m21 21-4.35-4.35"/>
      </svg>
    </button>
  </form>
</div>
