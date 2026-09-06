{**
 * CCI Blog – Comment partial (recursive for threading)
 * Usage: {include file='module:cci_blog/views/templates/front/_comment.tpl' comment=$comment depth=0}
 **}

{if $depth < 3}{* max nesting depth *}
<div class="cci-blog-comment {if $depth > 0}cci-blog-comment-reply{/if}" id="cci-blog-comment-{$comment.id_comment}">

  {* Avatar via Gravatar *}
  <div class="cci-blog-comment-avatar" aria-hidden="true">
    <img src="https://www.gravatar.com/avatar/{$comment.author_email|md5}?s=48&d=identicon"
         alt="{$comment.author_name|escape:'html'}"
         width="48" height="48"
         loading="lazy">
  </div>

  <div class="cci-blog-comment-body">
    <div class="cci-blog-comment-meta">
      <span class="cci-blog-comment-author">
        {if $comment.author_website}
          <a href="{$comment.author_website|escape:'html'}" rel="nofollow noopener" target="_blank">
            {$comment.author_name|escape:'html'}
          </a>
        {else}
          {$comment.author_name|escape:'html'}
        {/if}
      </span>
      <time class="cci-blog-comment-date"
            datetime="{$comment.date_add|date_format:'%Y-%m-%dT%H:%M:%S'}">
        {dateFormat date=$comment.date_add full=1}
      </time>
    </div>

    <div class="cci-blog-comment-content">
      {$comment.content|escape:'html'|nl2br}
    </div>

    {* Reply button – JS sets hidden id_parent *}
    {if $ccb_comments_on|default:false}
    <button class="cci-blog-comment-reply-btn" data-id="{$comment.id_comment}">
      {l s='Reply' mod='cci_blog'}
    </button>
    {/if}
  </div>
</div>

{* Nested replies *}
{if $comment.replies}
  <div class="cci-blog-comment-replies">
    {foreach from=$comment.replies item=reply}
      {include file='module:cci_blog/views/templates/front/_comment.tpl'
               comment=$reply
               depth=$depth+1}
    {/foreach}
  </div>
{/if}

{/if}{* depth guard *}
