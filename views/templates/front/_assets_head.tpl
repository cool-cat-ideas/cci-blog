{if $ccb_feed_enabled|default:false}
  <link rel="alternate"
        type="application/rss+xml"
        title="{l s='Blog RSS feed' mod='cci_blog'}"
        href="{$link->getModuleLink('cci_blog','feed',[])|escape:'html'}">
{/if}
