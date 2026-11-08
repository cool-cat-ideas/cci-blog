<?php

declare(strict_types=1);

if (!defined('_PS_VERSION_')) {
    exit;
}

class Cciblogauditexample extends Module
{
    public function __construct()
    {
        $this->name = 'cciblogauditexample';
        $this->tab = 'administration';
        $this->version = '1.0.0';
        $this->author = 'Store development team';
        $this->bootstrap = true;
        parent::__construct();
        $this->displayName = $this->l('Blog publication rule example');
        $this->description = $this->l('Adds optional store-specific guidance to CCI Blog publication checks.');
    }

    public function install(): bool
    {
        return parent::install() && $this->registerHook('displayCciBlogPublicationAuditRules');
    }

    public function hookDisplayCciBlogPublicationAuditRules(array $params): array
    {
        if (($params['auditVersion'] ?? '') !== '1') {
            return [];
        }

        return ['priority' => 100, 'rules' => [
            // Example override: description presence becomes advice for this shop.
            ['id' => 'description_present', 'weight' => 0, 'revision' => '1'],
            ['id' => 'cciblogauditexample.reader_next_step', 'label' => $this->l('Reader next step'),
                'group' => 'custom', 'weight' => 0, 'enabled' => true, 'revision' => '1',
                'evaluate' => [$this, 'checkReaderNextStep']],
        ]];
    }

    public function checkReaderNextStep(array $post, array $facts, array $context): array
    {
        if (($post['seo_content_type'] ?? '') === 'category') {
            return ['status' => 'not_applicable', 'message' => $this->l('This suggestion applies to articles.')];
        }

        // A link does not prove usefulness. Ask for a human review instead.
        return ['status' => 'info', 'message' => $this->l('Review whether the article gives readers a useful next step.'),
            'help' => $this->l('A product, related guide or practical instruction can help when it fits the subject.')];
    }
}
