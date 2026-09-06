{*
 * CCI Blog - React admin shell
 *}

<style>
{literal}
body.admincciblog #content.bootstrap,
body.admincciblog #content.bootstrap.with-tabs,
body.admincciblogconfiguration #content.bootstrap,
body.admincciblogconfiguration #content.bootstrap.with-tabs {
  width: calc(100% - var(--cdk-sidebar-width, 0px)) !important;
  margin-left: var(--cdk-sidebar-width, 0px) !important;
  padding: 0 !important;
}

body.page-sidebar-closed:not(.mobile).admincciblog #content.bootstrap,
body.page-sidebar-closed:not(.mobile).admincciblogconfiguration #content.bootstrap {
  width: calc(100% - var(--cdk-sidebar-width-collapse, 0px)) !important;
  margin-left: var(--cdk-sidebar-width-collapse, 0px) !important;
}

body.mobile.admincciblog #content.bootstrap,
body.mobile.admincciblogconfiguration #content.bootstrap {
  width: 100% !important;
  margin-left: 0 !important;
}

.cci-blog-admin-bootstrap {
  display: flow-root;
  min-height: calc(100vh - 32px);
  background: #f4f6f8;
}

#cci-blog-admin-root.cci-blog-admin-root {
  min-height: calc(100vh - 32px);
  margin-top: 94px;
  background: #f4f6f8;
}

.cci-blog-admin-loader {
  display: flex;
  flex-direction: column;
  align-items: center;
  justify-content: center;
  min-height: 320px;
  gap: 12px;
  padding: 24px;
  text-align: center;
  color: #526173;
}

.cci-blog-admin-loader-icon {
  display: inline-flex;
  width: 40px;
  height: 40px;
  align-items: center;
  justify-content: center;
  border: 1px solid #c9c0ff;
  border-radius: 6px;
  background: #f3f0ff;
}

.cci-blog-admin-loader-spinner {
  width: 20px;
  height: 20px;
  border: 2px solid rgba(96, 65, 223, 0.24);
  border-top-color: #6041df;
  border-radius: 999px;
  animation: cci-blog-admin-loader-spin 0.75s linear infinite;
}

.cci-blog-admin-loader-label {
  font-size: 14px;
  font-weight: 600;
  line-height: 20px;
}

@keyframes cci-blog-admin-loader-spin {
  to { transform: rotate(360deg); }
}

@media (max-width: 960px) {
  #cci-blog-admin-root.cci-blog-admin-root {
    margin-top: 68px;
  }
}
{/literal}
</style>

<div class="cci-blog-admin-bootstrap">
  <div id="cci-blog-admin-root" class="cci-blog-admin-root">
    <div class="cci-blog-admin-loader" role="status" aria-live="polite">
      <span class="cci-blog-admin-loader-icon" aria-hidden="true">
        <span class="cci-blog-admin-loader-spinner"></span>
      </span>
      <span class="cci-blog-admin-loader-label">{$adminLoadingLabel|escape:'html':'UTF-8'}</span>
    </div>
  </div>

  <script type="application/json" id="cci-blog-initial-state">
    {$initialPayload nofilter}
  </script>
  <script>
    const ccibInitialPayload = {$initialPayload nofilter};
    const ccibLicense = ccibInitialPayload.license || {};
    window.cciBlogBlockExtensions = window.cciBlogBlockExtensions || [];
    window.CCIBlogBlocks = window.CCIBlogBlocks || {
      register: function(block) {
        window.cciBlogBlockExtensions.push(block);
        window.dispatchEvent(new CustomEvent('cci-blog:block-extension-registered', { detail: block }));
      },
      all: function() {
        return window.cciBlogBlockExtensions.slice();
      }
    };
    window.CCIBlogProEditorFields = window.CCIBlogProEditorFields || (function() {
      const renderers = {};
      return {
        renderers: renderers,
        register: function(kind, renderer) {
          const normalizedKind = String(kind || '').trim();
          if (!normalizedKind || typeof renderer !== 'function') {
            return;
          }
          renderers[normalizedKind] = renderer;
          window.dispatchEvent(new CustomEvent('cci-blog:pro-editor-fields-ready', { detail: { kind: normalizedKind } }));
        },
        get: function(kind) {
          return renderers[String(kind || '').trim()] || null;
        },
        all: function() {
          return Object.assign({}, renderers);
        }
      };
    }());
    window.CCIBlogConfig = {
      apiBase: {$adminAjaxEndpoint nofilter},
      modulePath: '{$modulePath|escape:'javascript':'UTF-8'}',
      moduleVersion: {$moduleVersion nofilter},
      initialPayload: ccibInitialPayload,
    };
  </script>
  {foreach from=$adminExtensionScripts item=adminExtensionScript}
    <script type="module" src="{$adminExtensionScript.src|escape:'html':'UTF-8'}?v={$adminExtensionScript.version|escape:'html':'UTF-8'}"></script>
  {/foreach}
  <script type="module" src="{$modulePath|escape:'html':'UTF-8'}views/js/cci-blog-admin.js?v={$adminAssetVersion|escape:'html':'UTF-8'}"></script>
</div>
