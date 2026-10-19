{**
 * CCI Blog – Sidebar partial
 * Include with: {include file='module:cci_blog/views/templates/front/_sidebar.tpl'}
 * Requires: $ccb_categories, $ccb_tag_cloud, $ccb_sidebar
 **}

{if !isset($ccb_show_sidebar_search) || $ccb_show_sidebar_search}
  {include file='module:cci_blog/views/templates/front/_search_widget.tpl'}
{/if}

{* ── Categories widget ── *}
{if $ccb_categories}
<div class="cci-blog-widget">
  <h3 class="cci-blog-widget-title">{l s='Blog categories' mod='cci_blog'}</h3>
  <ul class="cci-blog-category-list">
    {foreach from=$ccb_categories item=cat}
    {if $cat.id_parent == 0}
    <li>
      <div class="cci-blog-category-list-row">
        <a href="{$link->getModuleLink('cci_blog','category',['slug'=>$cat.slug])|escape:'html'}">{$cat.name|escape:'html'}</a>
        <span class="cci-blog-count">{$cat.post_count}</span>
      </div>
      {* Subcategories *}
      {assign var=parentId value=$cat.id_category}
      {assign var=hasSubs value=false}
      {foreach from=$ccb_categories item=sub}
        {if $sub.id_parent == $parentId}{assign var=hasSubs value=true}{/if}
      {/foreach}
      {if $hasSubs}
      <ul class="cci-blog-category-list cci-blog-category-list-sub">
        {foreach from=$ccb_categories item=sub}
        {if $sub.id_parent == $parentId}
        <li>
          <div class="cci-blog-category-list-row">
        <a href="{$link->getModuleLink('cci_blog','category',['slug'=>$sub.slug])|escape:'html'}">{$sub.name|escape:'html'}</a>
        <span class="cci-blog-count">{$sub.post_count}</span>
      </div>
        </li>
        {/if}
        {/foreach}
      </ul>
      {/if}
    </li>
    {/if}
    {/foreach}
  </ul>
</div>
{/if}

{* ── Tag cloud widget ── *}
{if $ccb_tag_cloud}
<div class="cci-blog-widget">
  <h3 class="cci-blog-widget-title">{l s='Popular tags' mod='cci_blog'}</h3>
  <div class="cci-blog-tag-cloud">
    {foreach from=$ccb_tag_cloud item=tag}
      <a class="cci-blog-tag cci-blog-tag-cloud"
         href="{$link->getModuleLink('cci_blog','tag',['slug'=>$tag.slug])|escape:'html'}"
         title="{$tag.post_count} {l s='posts' mod='cci_blog'}">
        {$tag.name|escape:'html'}
      </a>
    {/foreach}
  </div>
</div>
{/if}
