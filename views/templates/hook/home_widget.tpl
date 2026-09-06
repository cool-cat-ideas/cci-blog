{**
 * CCI Blog – Home Widget
 **}

{if $ccb_posts}
{include file='module:cci_blog/views/templates/front/_assets_head.tpl'}
<section class="cci-blog-home-widget">
  <div class="cci-blog-home-widget-inner container">
    <h2 class="cci-blog-home-widget-title">{l s='From our blog' mod='cci_blog'}</h2>

    <div class="cci-blog-home-widget-grid">
      {foreach from=$ccb_posts item=post name=cci_blog_home_posts}
        {assign var=cci_blog_home_index value=$smarty.foreach.cci_blog_home_posts.index}
        <div class="cci-blog-home-widget-item
                    {if $cci_blog_home_index == 0}cci-blog-home-widget-item-featured
                    {else}cci-blog-home-widget-item-compact{/if}">
          {include file='module:cci_blog/views/templates/front/_post_card.tpl' post=$post heading_level=3}
        </div>
      {/foreach}
    </div>

    <div class="cci-blog-home-widget-actions">
      <a class="btn btn-outline-primary" href="{$link->getModuleLink('cci_blog','list',[])|escape:'html'}">
        {l s='View all posts' mod='cci_blog'}
      </a>
    </div>
  </div>
</section>
{/if}
