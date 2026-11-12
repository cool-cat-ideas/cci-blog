<nav class="cci-blog-reader-series" aria-labelledby="cci-blog-series-title">
  <h2 id="cci-blog-series-title"><a href="{$series.url|escape:'html'}">{$series.title|escape:'html'}</a></h2>
  {if $series.items}
    <details>
      <summary>{l s='Articles in this series' mod='cci_blog'}</summary>
      <ol>
        {foreach from=$series.items item=part}
          <li>{if $part.id_post == $ccb_post.id_post}<span aria-current="page">{$part.title|escape:'html'}</span>{else}<a href="{$part.url|escape:'html'}">{$part.title|escape:'html'}</a>{/if}</li>
        {/foreach}
      </ol>
    </details>
  {/if}
  <div class="cci-blog-reader-series-links">
    {if $series.previous}<a href="{$series.previous.url|escape:'html'}" rel="prev">{l s='Previous part:' mod='cci_blog'} {$series.previous.title|escape:'html'}</a>{/if}
    {if $series.next}<a href="{$series.next.url|escape:'html'}" rel="next">{l s='Next part:' mod='cci_blog'} {$series.next.title|escape:'html'}</a>{/if}
  </div>
</nav>
