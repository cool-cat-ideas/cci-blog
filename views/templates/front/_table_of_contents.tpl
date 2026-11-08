{block name='cci_blog_table_of_contents'}
<nav id="cci-blog-reading-navigation" tabindex="-1" class="cci-blog-post-table-of-contents" data-cci-blog-toc-nav data-cci-blog-toc-mode="{$ccb_toc_layout|default:'inline'|escape:'html'}" data-cci-blog-toc-gap="{$ccb_toc_sticky_gap|default:16|intval}" aria-label="{l s='In this article' mod='cci_blog'}">
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
{/block}
