<ol class="cci-blog-table-of-contents-list">
  {foreach from=$items item=item}
    <li class="cci-blog-table-of-contents-item cci-blog-table-of-contents-level-{$item.level|intval}">
      <a class="cci-blog-table-of-contents-link" href="#{$item.id|escape:'html'}">
        {$item.title|escape:'html'}
      </a>
      {if $item.children}
        {include file='module:cci_blog/views/templates/front/_table_of_contents_items.tpl' items=$item.children}
      {/if}
    </li>
  {/foreach}
</ol>
