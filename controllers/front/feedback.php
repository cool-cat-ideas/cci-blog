<?php

declare(strict_types=1);

if (!defined('_PS_VERSION_')) {
    exit;
}

class Cci_BlogFeedbackModuleFrontController extends ModuleFrontController
{
    public $ssl = true;

    public function postProcess(): void
    {
        header('Cache-Control: no-store');
        header('X-Robots-Tag: noindex, nofollow');
        $status = 'error';
        $json = (string) ($_SERVER['HTTP_X_REQUESTED_WITH'] ?? '') === 'XMLHttpRequest';
        $post = [];

        try {
            if (($_SERVER['REQUEST_METHOD'] ?? '') !== 'POST') {
                throw new DomainException('method');
            }
            if (Configuration::get('CCB_READER_FEEDBACK_ENABLED') !== false && !Configuration::get('CCB_READER_FEEDBACK_ENABLED')) {
                throw new DomainException('disabled');
            }
            $postId = (int) Tools::getValue('id_post', 0);
            if (!CciBlogReaderFeedback::isPublished($postId, (int) $this->context->shop->id, (int) $this->context->language->id)) {
                throw new DomainException('unavailable');
            }
            $post = Db::getInstance()->getRow('SELECT slug FROM `' . _DB_PREFIX_ . 'cci_blog_post_lang` WHERE id_post='
                . $postId . ' AND id_shop=' . (int) $this->context->shop->id . ' AND id_lang=' . (int) $this->context->language->id) ?: [];
            $pro = Module::isEnabled('cci_blog_pro') ? Module::getInstanceByName('cci_blog_pro') : null;
            $messageAllowed = $pro && method_exists($pro, 'canUseCciReaderEngagement') && $pro->canUseCciReaderEngagement((int) $this->context->shop->id);
            CciBlogReaderFeedback::submit($postId, $this->context, [
                'token' => Tools::getValue('token'), 'helpful' => Tools::getValue('helpful'), 'message' => Tools::getValue('message', ''),
            ], (bool) $messageAllowed);
            $status = 'saved';
        } catch (DomainException $error) {
            if ($json) {
                http_response_code($error->getMessage() === 'rate_limited' ? 429 : 400);
            }
        } catch (Throwable $error) {
            PrestaShopLogger::addLog('CCI Blog reader feedback could not be saved.', 3);
            if ($json) {
                http_response_code(503);
            }
        }
        if ($json) {
            header('Content-Type: application/json; charset=utf-8');
            exit(json_encode(['success' => $status === 'saved']));
        }
        $url = $post ? $this->context->link->getModuleLink('cci_blog', 'post', ['slug' => $post['slug'], 'feedback' => $status])
            : $this->context->link->getModuleLink('cci_blog', 'list');
        Tools::redirect($url . '#cci-blog-reader-feedback');
    }
}
