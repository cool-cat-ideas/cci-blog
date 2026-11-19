<section class="cci-blog-reader-feedback" id="cci-blog-reader-feedback" aria-labelledby="cci-blog-feedback-title">
  <h2 id="cci-blog-feedback-title">{l s='Was this article helpful?' mod='cci_blog'}</h2>
  <p>{l s='Your feedback helps us improve our guides.' mod='cci_blog'}</p>
  <form method="post" action="{$link->getModuleLink('cci_blog','feedback')|escape:'html'}" data-cci-reader-feedback{if $ccb_reader_feedback_vote !== null} data-feedback-complete{/if}>
    {if $ccb_reader_feedback_vote === null}
      <input type="hidden" name="id_post" value="{$ccb_post.id_post|intval}">
      <input type="hidden" name="token" value="{$ccb_reader_feedback_token|escape:'html'}">
      {if $ccb_reader_feedback_message}
        <details class="cci-blog-reader-feedback-details">
          <summary>{l s='Tell us what was missing (optional)' mod='cci_blog'}</summary>
          <label for="cci-blog-feedback-message">{l s='What could we explain better?' mod='cci_blog'}</label>
          <textarea class="form-control" id="cci-blog-feedback-message" name="message" rows="3" maxlength="1000" aria-describedby="cci-blog-feedback-privacy"></textarea>
          <p id="cci-blog-feedback-privacy" class="cci-blog-reader-feedback-privacy">{l s='Only the shop team can read this message. Please leave out personal information and order details.' mod='cci_blog'}</p>
        </details>
      {/if}
      {include file='module:cci_blog/views/templates/front/_captcha.tpl' captcha_operation='feedback'}
    {/if}
    <div class="cci-blog-reader-feedback-actions">
      <button class="btn btn-outline-primary" type="submit" name="helpful" value="1"{if $ccb_reader_feedback_vote !== null} disabled aria-pressed="{if $ccb_reader_feedback_vote == 1}true{else}false{/if}"{/if}>{l s='Yes, it helped' mod='cci_blog'}</button>
      <button class="btn btn-outline-primary" type="submit" name="helpful" value="0"{if $ccb_reader_feedback_vote !== null} disabled aria-pressed="{if $ccb_reader_feedback_vote == 0}true{else}false{/if}"{/if}>{l s='Not yet' mod='cci_blog'}</button>
    </div>
    <p role="status" aria-live="polite" data-feedback-status data-recorded="{l s='Your vote is already saved.' mod='cci_blog'}" data-success="{l s='Thank you. Your feedback has been saved.' mod='cci_blog'}" data-error="{l s='We could not save your feedback. Please try again.' mod='cci_blog'}" data-sending="{l s='Saving feedback...' mod='cci_blog'}">{if $ccb_reader_feedback_status == 'saved' && $ccb_reader_feedback_vote !== null}{l s='Thank you. Your feedback has been saved.' mod='cci_blog'}{elseif $ccb_reader_feedback_vote !== null}{l s='Your vote is already saved.' mod='cci_blog'}{elseif $ccb_reader_feedback_status == 'captcha'}{l s='Complete the security check again. If it does not load, refresh the page.' mod='cci_blog'}{elseif $ccb_reader_feedback_status == 'error'}{l s='We could not save your feedback. Please try again.' mod='cci_blog'}{/if}</p>
  </form>
</section>
