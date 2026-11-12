{extends file='page.tpl'}
{block name='head' append}
  {include file='module:cci_blog/views/templates/front/_assets_head.tpl'}
{/block}
{block name='content'}
  <section class="cci-blog-reader-series-archive" aria-labelledby="cci-blog-series-heading">
    <header class="cci-blog-post-header">
      <h1 id="cci-blog-series-heading">{$ccb_series.title|escape:'html'}</h1>
      {if $ccb_series.description}<p>{$ccb_series.description|escape:'html'}</p>{/if}
    </header>
    <div class="cci-blog-posts-grid cci-blog-posts-grid-grid">
      {foreach from=$ccb_series.items item=part}
        {include file='module:cci_blog/views/templates/front/_post_card.tpl' post=$part}
      {/foreach}
    </div>
    <nav class="cci-blog-reader-series-links" aria-label="{l s='Series pages' mod='cci_blog'}">
      {if $ccb_series_previous}<a rel="prev" href="{$ccb_series_previous|escape:'html'}">{l s='Previous page' mod='cci_blog'}</a>{/if}
      {if $ccb_series_next}<a rel="next" href="{$ccb_series_next|escape:'html'}">{l s='Next page' mod='cci_blog'}</a>{/if}
    </nav>
  </section>
{/block}
