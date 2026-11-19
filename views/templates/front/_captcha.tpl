{if $ccb_captcha}
  <div data-cci-blog-captcha
       data-provider="{$ccb_captcha.provider|escape:'html'}"
       data-site-key="{$ccb_captcha.siteKey|escape:'html'}"
       data-operation="{$captcha_operation|escape:'html'}"
       data-label="{l s='Security check' mod='cci_blog'}"
       data-error="{l s='The security check could not load. Check for a conflicting CAPTCHA plugin or a blocked script, then refresh the page.' mod='cci_blog'}"
       data-validation="{l s='Complete the security check again. If it does not load, refresh the page.' mod='cci_blog'}">
    <input type="hidden" name="captchaToken" value="">
    <div data-captcha-widget></div>
    <noscript>{l s='Enable JavaScript to complete the security check.' mod='cci_blog'}</noscript>
  </div>
{/if}
