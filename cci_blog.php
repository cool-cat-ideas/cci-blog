<?php
/**
 * CCI Blog – Modern Blog Module for PrestaShop 9.1
 *
 * @author    Cool Cat Ideas <hello@coolcatideas.com>
 * @copyright 2025-2026 Cool Cat Ideas
 * @license   https://opensource.org/license/afl-3-0-php Academic Free License 3.0 (AFL-3.0)
 */

declare(strict_types=1);

if (!defined('_PS_VERSION_')) {
    exit;
}

require_once __DIR__ . '/classes/CciBlogPost.php';
require_once __DIR__ . '/classes/CciBlogCategory.php';
require_once __DIR__ . '/classes/CciBlogTag.php';
require_once __DIR__ . '/classes/CciBlogComment.php';
require_once __DIR__ . '/classes/CciBlogSeo.php';
require_once __DIR__ . '/classes/CciBlogPagination.php';
require_once __DIR__ . '/classes/CciBlogFrontController.php';
require_once __DIR__ . '/classes/CciBlogTableOfContents.php';
require_once __DIR__ . '/classes/CciSharedLicenseEnvironment.php';
require_once __DIR__ . '/classes/CciBlogFeatureRegistry.php';

class Cci_Blog extends Module
{
    public const VERSION = '1.0.0';
    private const ADMIN_PARENT_TAB_CLASS = 'AdminCciParent';
    private const ADMIN_TABS = [
        'AdminCciBlog',
        'AdminCciBlogConfiguration',
    ];

    public function __construct()
    {
        $this->name          = 'cci_blog';
        $this->tab           = 'front_office_features';
        $this->version       = self::VERSION;
        $this->author        = 'Cool Cat Ideas';
        $this->need_instance = 0;
        $this->bootstrap     = true;
        $this->ps_versions_compliancy = ['min' => '9.0.0', 'max' => '9.1.5'];

        parent::__construct();

        $this->displayName = $this->l('CCI Blog');
        $this->description = $this->l('Publish articles and buying guides directly in PrestaShop. Add author profiles, tables of contents, comments, search and structured article data without running a separate blog.');
        $this->confirmUninstall = $this->l('Are you sure you want to uninstall CCI Blog? All blog data will be deleted.');
    }

    // -------------------------------------------------------------------------
    // Install / Uninstall
    // -------------------------------------------------------------------------

    public function install(): bool
    {
        return parent::install()
            && $this->installDb()
            && $this->installTabs()
            && $this->installHooks()
            && $this->installConfiguration();
    }

    public function uninstall(): bool
    {
        return $this->uninstallDb()
            && $this->uninstallTabs()
            && $this->uninstallConfiguration()
            && parent::uninstall();
    }

    // -------------------------------------------------------------------------
    // Database
    // -------------------------------------------------------------------------

    private function installDb(): bool
    {
        $sql = file_get_contents(__DIR__ . '/sql/install.sql');
        // Replace prefix placeholder
        $sql = str_replace('PREFIX_', _DB_PREFIX_, $sql);
        foreach (array_filter(array_map('trim', explode(';', $sql))) as $query) {
            if (!Db::getInstance()->execute($query)) {
                return false;
            }
        }
        return true;
    }

    private function uninstallDb(): bool
    {
        $sql = file_get_contents(__DIR__ . '/sql/uninstall.sql');
        $sql = str_replace('PREFIX_', _DB_PREFIX_, $sql);
        foreach (array_filter(array_map('trim', explode(';', $sql))) as $query) {
            Db::getInstance()->execute($query);
        }
        return true;
    }

    // -------------------------------------------------------------------------
    // Admin Tabs
    // -------------------------------------------------------------------------

    private function installTabs(): bool
    {
        if ($this->ensureCciParentTab() <= 0) {
            return false;
        }

        $tabs = [
            [
                'class_name' => 'AdminCciBlog',
                'name'       => 'CCI Blog',
                'parent'     => self::ADMIN_PARENT_TAB_CLASS,
                'icon'       => 'edit_note',
                'position'   => 1,
            ],
            [
                'class_name' => 'AdminCciBlogConfiguration',
                'name'       => 'CCI Blog',
                'parent'     => 'AdminCciBlog',
                'icon'       => 'settings',
                'active'     => 0,
            ],
        ];

        foreach ($tabs as $tabData) {
            if (!$this->upsertTab($tabData)) {
                return false;
            }
        }

        return $this->normalizeCciMenuPositions((int) Tab::getIdFromClassName(self::ADMIN_PARENT_TAB_CLASS));
    }

    private function uninstallTabs(): bool
    {
        $this->deleteTabs(array_reverse(self::ADMIN_TABS));

        return $this->removeCciParentTabIfEmpty();
    }

    private function upsertTab(array $tabData): bool
    {
        $id = (int) Tab::getIdFromClassName($tabData['class_name']);
        $tab = $id > 0 ? new Tab($id) : new Tab();

        $tab->active = (int) ($tabData['active'] ?? 1);
        $tab->class_name = $tabData['class_name'];
        $tab->icon = $tabData['icon'] ?? '';
        $tab->id_parent = (int) Tab::getIdFromClassName($tabData['parent']);
        $tab->module = $this->name;

        foreach (Language::getLanguages(false) as $lang) {
            $tab->name[(int) $lang['id_lang']] = (string) $tabData['name'];
        }

        $saved = $id > 0 ? (bool) $tab->update() : (bool) $tab->add();
        if (!$saved) {
            return false;
        }

        if (isset($tabData['position'])) {
            $tab = new Tab((int) $tab->id);
            $desiredPosition = (int) $tabData['position'];
            if ((int) $tab->position !== $desiredPosition) {
                $tab->updatePosition((int) $tab->position < $desiredPosition, $desiredPosition);
            }
        }

        return true;
    }

    private function ensureCciParentTab(): int
    {
        $improveTabId = (int) Tab::getIdFromClassName('IMPROVE');
        if ($improveTabId <= 0) {
            return 0;
        }

        $tabId = (int) Tab::getIdFromClassName(self::ADMIN_PARENT_TAB_CLASS);
        $tab = $tabId > 0 ? new Tab($tabId) : new Tab();
        $tab->active = 1;
        $tab->class_name = self::ADMIN_PARENT_TAB_CLASS;
        $tab->id_parent = $improveTabId;
        $tab->icon = 'pets';
        $tab->module = '';

        foreach (Language::getLanguages(false) as $language) {
            $tab->name[(int) $language['id_lang']] = 'Cool Cat Ideas';
        }

        $saved = $tabId > 0 ? (bool) $tab->update() : (bool) $tab->add();
        if (!$saved) {
            return 0;
        }

        $modulesTabId = (int) Tab::getIdFromClassName('AdminParentModulesSf');
        if ($modulesTabId > 0) {
            $modulesTab = new Tab($modulesTabId);
            $tab = new Tab((int) $tab->id);
            if ((int) $modulesTab->id_parent === $improveTabId) {
                $desiredPosition = (int) $modulesTab->position + 1;
                if ((int) $tab->position !== $desiredPosition) {
                    $tab->updatePosition((int) $tab->position < $desiredPosition, $desiredPosition);
                }
            }
        }

        $tab->cleanPositions($improveTabId);

        return (int) $tab->id;
    }

    private function normalizeCciMenuPositions(int $parentId): bool
    {
        $tabs = Db::getInstance()->executeS(
            'SELECT `id_tab`, `class_name`, `position` FROM `' . _DB_PREFIX_ . 'tab`'
            . ' WHERE `id_parent` = ' . $parentId
        );
        if (!is_array($tabs)) {
            return false;
        }

        $priorities = [
            'AdminCciBlog' => 10,
            'AdminCciWithdrawal' => 20,
            'AdminCciConsentHistory' => 25,
            'AdminCciNiceMenu' => 30,
            'AdminCciCategoryGrid' => 40,
            'AdminCciCourierCashOnDelivery' => 50,
        ];
        usort($tabs, static function (array $left, array $right) use ($priorities): int {
            $leftPriority = $priorities[$left['class_name']] ?? 1000 + (int) $left['position'];
            $rightPriority = $priorities[$right['class_name']] ?? 1000 + (int) $right['position'];

            return $leftPriority <=> $rightPriority ?: (int) $left['id_tab'] <=> (int) $right['id_tab'];
        });

        foreach ($tabs as $index => $tabData) {
            if (!Db::getInstance()->update('tab', ['position' => $index + 1], 'id_tab = ' . (int) $tabData['id_tab'])) {
                return false;
            }
        }

        return true;
    }

    private function removeCciParentTabIfEmpty(): bool
    {
        $parentId = (int) Tab::getIdFromClassName(self::ADMIN_PARENT_TAB_CLASS);
        if ($parentId <= 0) {
            return true;
        }

        $remainingChildren = (int) Db::getInstance()->getValue(
            'SELECT COUNT(*) FROM `' . _DB_PREFIX_ . 'tab` WHERE `id_parent` = ' . $parentId
        );

        return $remainingChildren > 0 || (bool) (new Tab($parentId))->delete();
    }

    private function deleteTabs(array $tabNames): void
    {
        foreach ($tabNames as $className) {
            $id = Tab::getIdFromClassName($className);
            if ($id) {
                (new Tab($id))->delete();
            }
        }
    }

    // -------------------------------------------------------------------------
    // Hooks
    // -------------------------------------------------------------------------

    private function installHooks(): bool
    {
        $featureRegistry = new CciBlogFeatureRegistry($this);
        $hooks = [
            'displayHome',
            'actionFrontControllerSetMedia',
            'actionModifyFrontendSitemap',
            'actionEmployeeFormBuilderModifier',
            'actionEmployeeFormDataProviderData',
            'actionEmployeeFormDataProviderDefaultData',
            'actionAfterCreateEmployeeFormHandler',
            'actionAfterUpdateEmployeeFormHandler',
            'gSitemapAppendUrls',
            'moduleRoutes',
            'registerGDPRConsent',
        ];
        foreach (array_merge($hooks, $featureRegistry->integrationHooks()) as $hook) {
            if (!$this->registerHook($hook)) {
                return false;
            }
        }
        return true;
    }

    public function ensureIntegrationHooks(): void
    {
        $featureRegistry = new CciBlogFeatureRegistry($this);
        $hooks = [
            'actionModifyFrontendSitemap',
            'actionEmployeeFormBuilderModifier',
            'actionEmployeeFormDataProviderData',
            'actionEmployeeFormDataProviderDefaultData',
            'actionAfterCreateEmployeeFormHandler',
            'actionAfterUpdateEmployeeFormHandler',
            'gSitemapAppendUrls',
        ];
        foreach (array_merge($hooks, $featureRegistry->integrationHooks()) as $hook) {
            $this->registerHook($hook);
        }
    }

    public function hookRegisterGDPRConsent(array $params): string
    {
        return '';
    }

    /**
     * Adds the public CCI Blog author profile to PrestaShop's employee form.
     */
    public function hookActionEmployeeFormBuilderModifier(array $params): void
    {
        $formBuilder = $params['form_builder'] ?? null;
        if (!$formBuilder instanceof \Symfony\Component\Form\FormBuilderInterface) {
            return;
        }

        $translator = $this->getTranslator();
        $canEditBiographyTranslations = $this->canEditAuthorTranslations();
        $biographyFieldType = $canEditBiographyTranslations
            ? \PrestaShopBundle\Form\Admin\Type\TranslatableType::class
            : \Symfony\Component\Form\Extension\Core\Type\TextareaType::class;
        $biographyFieldOptions = [
            'label' => $translator->trans('Author biography', [], 'Modules.Cciblog.Admin'),
            'help' => $translator->trans(
                $canEditBiographyTranslations
                    ? 'Add a short biography for each shop language in which the author profile should appear.'
                    : 'In Free, the biography is saved in the default shop language. PRO adds the other shop languages.',
                [],
                'Modules.Cciblog.Admin'
            ),
            'required' => false,
        ];
        $biographyTextareaOptions = [
            'required' => false,
            'attr' => [
                'rows' => 5,
                'maxlength' => 2000,
            ],
            'constraints' => [
                new \Symfony\Component\Validator\Constraints\Length(['max' => 2000]),
            ],
        ];

        if ($canEditBiographyTranslations) {
            $biographyFieldOptions['type'] = \Symfony\Component\Form\Extension\Core\Type\TextareaType::class;
            $biographyFieldOptions['options'] = $biographyTextareaOptions;
        } else {
            $biographyFieldOptions = array_merge($biographyFieldOptions, $biographyTextareaOptions);
        }

        $profileBuilder = $formBuilder->create(
            'cci_blog_author_profile',
            \PrestaShopBundle\Form\Admin\Type\CardType::class,
            [
                'label' => $translator->trans('CCI Blog author profile', [], 'Modules.Cciblog.Admin'),
                'required' => false,
            ]
        );

        $profileBuilder
            ->add('active', \PrestaShopBundle\Form\Admin\Type\SwitchType::class, [
                'label' => $translator->trans('Show the public author profile', [], 'Modules.Cciblog.Admin'),
                'help' => $translator->trans('The profile can be displayed below articles assigned to this employee.', [], 'Modules.Cciblog.Admin'),
                'required' => false,
            ])
            ->add('display_name', \Symfony\Component\Form\Extension\Core\Type\TextType::class, [
                'label' => $translator->trans('Public author name', [], 'Modules.Cciblog.Admin'),
                'help' => $translator->trans('Leave empty to use the employee first and last name.', [], 'Modules.Cciblog.Admin'),
                'required' => false,
                'constraints' => [
                    new \Symfony\Component\Validator\Constraints\Length(['max' => 128]),
                ],
            ])
            ->add('bio', $biographyFieldType, $biographyFieldOptions)
            ->add('avatar', \Symfony\Component\Form\Extension\Core\Type\UrlType::class, [
                'label' => $translator->trans('Public avatar URL', [], 'Modules.Cciblog.Admin'),
                'help' => $translator->trans('Optional HTTPS image URL used only on the storefront.', [], 'Modules.Cciblog.Admin'),
                'required' => false,
                'constraints' => [
                    new \Symfony\Component\Validator\Constraints\Length(['max' => 255]),
                ],
            ])
            ->add('twitter', \Symfony\Component\Form\Extension\Core\Type\TextType::class, [
                'label' => $translator->trans('Username on X (Twitter)', [], 'Modules.Cciblog.Admin'),
                'help' => $translator->trans('Enter the username without @.', [], 'Modules.Cciblog.Admin'),
                'required' => false,
                'constraints' => [
                    new \Symfony\Component\Validator\Constraints\Regex([
                        'pattern' => '/^[A-Za-z0-9_]*$/',
                        'message' => $translator->trans('Use only letters, numbers and underscores.', [], 'Modules.Cciblog.Admin'),
                    ]),
                    new \Symfony\Component\Validator\Constraints\Length(['max' => 128]),
                ],
            ])
            ->add('linkedin', \Symfony\Component\Form\Extension\Core\Type\UrlType::class, [
                'label' => $translator->trans('LinkedIn profile URL', [], 'Modules.Cciblog.Admin'),
                'required' => false,
                'constraints' => [
                    new \Symfony\Component\Validator\Constraints\Length(['max' => 255]),
                ],
            ])
        ;

        $formBuilder->add($profileBuilder);
    }

    /**
     * Loads CCI Blog profile values into PrestaShop's employee form.
     */
    public function hookActionEmployeeFormDataProviderData(array $params): void
    {
        if (!isset($params['data']) || !is_array($params['data'])) {
            return;
        }

        $employeeId = (int) ($params['id'] ?? 0);
        $params['data']['cci_blog_author_profile'] = $this->getEmployeeAuthorProfile(
            $employeeId,
            (string) ($params['data']['firstname'] ?? ''),
            (string) ($params['data']['lastname'] ?? '')
        );
    }

    public function hookActionEmployeeFormDataProviderDefaultData(array $params): void
    {
        if (!isset($params['data']) || !is_array($params['data'])) {
            return;
        }

        $params['data']['cci_blog_author_profile'] = $this->getEmployeeAuthorProfile(0);
    }

    public function hookActionAfterCreateEmployeeFormHandler(array $params): void
    {
        $this->saveEmployeeAuthorProfileFromForm($params);
    }

    public function hookActionAfterUpdateEmployeeFormHandler(array $params): void
    {
        $this->saveEmployeeAuthorProfileFromForm($params);
    }

    private function getEmployeeAuthorProfile(int $employeeId, string $firstName = '', string $lastName = ''): array
    {
        $biographies = [];
        foreach (Language::getLanguages(false) as $language) {
            $biographies[(int) $language['id_lang']] = '';
        }

        $fallbackName = trim($firstName . ' ' . $lastName);
        if ($employeeId <= 0) {
            return [
                'active' => true,
                'display_name' => $fallbackName,
                'bio' => $this->formatEmployeeBiographyFormData($biographies),
                'avatar' => '',
                'twitter' => '',
                'linkedin' => '',
            ];
        }

        $profile = Db::getInstance()->getRow(
            'SELECT `id_author`, `display_name`, `avatar`, `twitter`, `linkedin`, `active`
             FROM `' . _DB_PREFIX_ . 'cci_blog_author`
             WHERE `id_employee` = ' . $employeeId . '
             ORDER BY `active` DESC, `id_author` DESC'
        ) ?: [];

        $authorId = (int) ($profile['id_author'] ?? 0);
        if ($authorId > 0) {
            $rows = Db::getInstance()->executeS(
                'SELECT `id_lang`, `bio`
                 FROM `' . _DB_PREFIX_ . 'cci_blog_author_lang`
                 WHERE `id_author` = ' . $authorId
            ) ?: [];
            foreach ($rows as $row) {
                $languageId = (int) ($row['id_lang'] ?? 0);
                if (array_key_exists($languageId, $biographies)) {
                    $biographies[$languageId] = (string) ($row['bio'] ?? '');
                }
            }
        }

        return [
            'active' => $authorId > 0 ? (bool) ($profile['active'] ?? false) : true,
            'display_name' => trim((string) ($profile['display_name'] ?? '')) ?: $fallbackName,
            'bio' => $this->formatEmployeeBiographyFormData($biographies),
            'avatar' => trim((string) ($profile['avatar'] ?? '')),
            'twitter' => trim((string) ($profile['twitter'] ?? '')),
            'linkedin' => trim((string) ($profile['linkedin'] ?? '')),
        ];
    }

    private function saveEmployeeAuthorProfileFromForm(array $params): void
    {
        $employeeId = (int) ($params['id'] ?? 0);
        $formData = $params['form_data']['cci_blog_author_profile'] ?? null;
        if ($employeeId <= 0 || !is_array($formData)) {
            return;
        }

        $displayName = trim(strip_tags((string) ($formData['display_name'] ?? '')));
        if ($displayName === '') {
            $employee = new Employee($employeeId);
            if (Validate::isLoadedObject($employee)) {
                $displayName = trim((string) $employee->firstname . ' ' . (string) $employee->lastname);
            }
        }

        $avatar = $this->normalizePublicProfileUrl((string) ($formData['avatar'] ?? ''));
        $linkedin = $this->normalizePublicProfileUrl((string) ($formData['linkedin'] ?? ''));
        $twitter = ltrim(trim((string) ($formData['twitter'] ?? '')), '@');
        $twitter = preg_replace('/[^A-Za-z0-9_]/', '', $twitter) ?: '';

        $db = Db::getInstance();
        $authorId = (int) $db->getValue(
            'SELECT `id_author`
             FROM `' . _DB_PREFIX_ . 'cci_blog_author`
             WHERE `id_employee` = ' . $employeeId . '
             ORDER BY `active` DESC, `id_author` DESC'
        );

        $profileData = [
            'id_employee' => $employeeId,
            'display_name' => mb_substr($displayName, 0, 128),
            'avatar' => mb_substr($avatar, 0, 255),
            'twitter' => mb_substr($twitter, 0, 128),
            'linkedin' => mb_substr($linkedin, 0, 255),
            'active' => !empty($formData['active']) ? 1 : 0,
        ];

        if ($authorId > 0) {
            $db->update('cci_blog_author', $profileData, '`id_author` = ' . $authorId);
        } else {
            $db->insert('cci_blog_author', $profileData);
            $authorId = (int) $db->Insert_ID();
        }

        if ($authorId <= 0) {
            return;
        }

        $submittedBiography = $formData['bio'] ?? '';
        if ($this->canEditAuthorTranslations()) {
            $languageIds = array_map(
                static fn(array $language): int => (int) $language['id_lang'],
                Language::getLanguages(false)
            );
            $biographies = is_array($submittedBiography) ? $submittedBiography : [];
        } else {
            $defaultLanguageId = $this->getDefaultShopLanguageId();
            $languageIds = [$defaultLanguageId];
            $biographies = [
                $defaultLanguageId => is_array($submittedBiography)
                    ? ($submittedBiography[$defaultLanguageId] ?? '')
                    : $submittedBiography,
            ];
        }

        foreach ($languageIds as $languageId) {
            $bio = trim(strip_tags((string) ($biographies[$languageId] ?? '')));
            $db->insert(
                'cci_blog_author_lang',
                [
                    'id_author' => $authorId,
                    'id_lang' => $languageId,
                    'bio' => mb_substr($bio, 0, 2000),
                ],
                false,
                true,
                Db::REPLACE
            );
        }
    }

    private function formatEmployeeBiographyFormData(array $biographies): array|string
    {
        if ($this->canEditAuthorTranslations()) {
            return $biographies;
        }

        return (string) ($biographies[$this->getDefaultShopLanguageId()] ?? '');
    }

    private function canEditAuthorTranslations(): bool
    {
        if (!Module::isInstalled('cci_blog_pro')) {
            return false;
        }

        if (method_exists(Module::class, 'isEnabled') && !Module::isEnabled('cci_blog_pro')) {
            return false;
        }

        $proModule = Module::getInstanceByName('cci_blog_pro');
        if (!$proModule instanceof Module || empty($proModule->active) || !method_exists($proModule, 'getCciLicensePayload')) {
            return false;
        }

        $license = $proModule->getCciLicensePayload((int) ($this->context->shop->id ?? 0));
        if (!is_array($license)) {
            return false;
        }

        $features = (new CciBlogFeatureRegistry($this))->getFeatures($license);

        return !empty($features['translations']['enabled']);
    }

    private function getDefaultShopLanguageId(): int
    {
        $languageId = (int) Configuration::get('PS_LANG_DEFAULT');
        if ($languageId > 0) {
            return $languageId;
        }

        return max(1, (int) ($this->context->language->id ?? 0));
    }

    private function normalizePublicProfileUrl(string $url): string
    {
        $url = trim($url);
        if ($url === '' || !filter_var($url, FILTER_VALIDATE_URL)) {
            return '';
        }

        $scheme = strtolower((string) parse_url($url, PHP_URL_SCHEME));

        return in_array($scheme, ['http', 'https'], true) ? $url : '';
    }

    // -------------------------------------------------------------------------
    // Configuration defaults
    // -------------------------------------------------------------------------

    private function installConfiguration(): bool
    {
        $defaults = [
            'CCB_POSTS_PER_PAGE'        => 9,
            'CCB_SHOW_AUTHOR'           => 1,
            'CCB_SHOW_DATE'             => 1,
            'CCB_SHOW_VIEWS'            => 1,
            'CCB_SHOW_READ_TIME'        => 1,
            'CCB_COMMENTS_ENABLED'      => 1,
            'CCB_COMMENTS_PROVIDER'     => 'disqus',
            'CCB_COMMENTS_MODERATION'   => 1,
            'CCB_TABLE_OF_CONTENTS_ENABLED' => 1,
            'CCB_TABLE_OF_CONTENTS_MIN_HEADINGS' => 2,
            'CCB_RELATED_POSTS'         => 3,
            'CCB_RELATED_PRODUCTS'      => 1,
            'CCB_BREADCRUMB'            => 1,
            'CCB_SOCIAL_SHARE'          => 1,
            'CCB_NEWSLETTER_WIDGET'     => 0,
            'CCB_SCHEMA_ORG'            => 1,
            'CCB_OG_TAGS'               => 1,
            'CCB_DISQUS_SHORTNAME'      => '',
            'CCB_GA_EVENT_TRACKING'     => 0,
            'CCB_HIGHLIGHT_SYNTAX'      => 0,
            'CCB_BASE_SLUG'             => 'blog',
            'CCB_URL_SUFFIX_HTML'       => 0,
            'CCB_SIDEBAR_POSITION'      => 'right',
            'CCB_LAYOUT'                => 'grid',
            'CCB_SHOW_FEATURED_WIDGET'  => 1,
            'CCB_FEED_ENABLED'          => 1,
            'CCB_FEED_ITEMS'            => 20,
            'CCB_OWL_ASSETS_SOURCE'     => 'module',
            'CCB_LOAD_OWL_LIBRARY'      => 1,
            'CCB_LOAD_OWL_STYLES'       => 1,
        ];
        foreach ($defaults as $key => $value) {
            Configuration::updateValue($key, $value);
        }
        return true;
    }

    private function uninstallConfiguration(): bool
    {
        $keys = [
            'CCB_POSTS_PER_PAGE', 'CCB_SHOW_AUTHOR', 'CCB_SHOW_DATE',
            'CCB_SHOW_VIEWS', 'CCB_SHOW_READ_TIME', 'CCB_COMMENTS_ENABLED',
            'CCB_COMMENTS_PROVIDER', 'CCB_COMMENTS_MODERATION',
            'CCB_TABLE_OF_CONTENTS_ENABLED', 'CCB_TABLE_OF_CONTENTS_MIN_HEADINGS',
            'CCB_RELATED_POSTS', 'CCB_RELATED_PRODUCTS',
            'CCB_BREADCRUMB', 'CCB_SOCIAL_SHARE', 'CCB_NEWSLETTER_WIDGET',
            'CCB_SCHEMA_ORG', 'CCB_OG_TAGS', 'CCB_DISQUS_SHORTNAME',
            'CCB_GA_EVENT_TRACKING', 'CCB_HIGHLIGHT_SYNTAX', 'CCB_BASE_SLUG',
            'CCB_URL_SUFFIX_HTML',
            'CCB_SIDEBAR_POSITION', 'CCB_LAYOUT', 'CCB_SHOW_FEATURED_WIDGET',
            'CCB_FEED_ENABLED', 'CCB_FEED_ITEMS', 'CCB_OWL_ASSETS_SOURCE', 'CCB_LOAD_OWL_LIBRARY',
            'CCB_LOAD_OWL_STYLES', 'CCI_BLOG_LICENSE_TOKEN', 'CCI_BLOG_LICENSE_CHECKED_AT',
            'CCI_BLOG_LICENSE_LAST_ERROR', 'CCI_BLOG_LICENSE_LAST_ERROR_CODE',
        ];
        foreach ($keys as $key) {
            Configuration::deleteByName($key);
        }
        return true;
    }

    // -------------------------------------------------------------------------
    // Routes
    // -------------------------------------------------------------------------

    public function hookModuleRoutes(): array
    {
        $base = Configuration::get('CCB_BASE_SLUG') ?: 'blog';
        $htmlSuffix = (bool) Configuration::get('CCB_URL_SUFFIX_HTML');
        $postRule = $base . '/{slug}' . ($htmlSuffix ? '.html' : '');
        $alternatePostRule = $base . '/{slug}' . ($htmlSuffix ? '' : '.html');
        $categoryRule = $base . '/category/{slug}' . ($htmlSuffix ? '.html' : '');
        $alternateCategoryRule = $base . '/category/{slug}' . ($htmlSuffix ? '' : '.html');
        $pageSuffix = $htmlSuffix ? '.html' : '';
        $alternatePageSuffix = $htmlSuffix ? '' : '.html';
        $pageKeyword = ['page' => ['regexp' => '[1-9][0-9]*', 'param' => 'page']];
        $slugKeyword = ['slug' => ['regexp' => '[_a-zA-Z0-9-]+', 'param' => 'slug']];
        $slugPageKeywords = $slugKeyword + $pageKeyword;

        return [
            'module-cci_blog-list' => [
                'controller' => 'list',
                'rule'       => $base,
                'keywords'   => [],
                'params'     => ['fc' => 'module', 'module' => 'cci_blog'],
            ],
            'module-cci_blog-list-page' => [
                'controller' => 'list',
                'rule'       => $base . '/page/{page}' . $pageSuffix,
                'keywords'   => $pageKeyword,
                'params'     => ['fc' => 'module', 'module' => 'cci_blog'],
            ],
            'module-cci_blog-list-page-alternate' => [
                'controller' => 'list',
                'rule'       => $base . '/page/{page}' . $alternatePageSuffix,
                'keywords'   => $pageKeyword,
                'params'     => ['fc' => 'module', 'module' => 'cci_blog'],
            ],
            'module-cci_blog-category' => [
                'controller' => 'category',
                'rule'       => $categoryRule,
                'keywords'   => $slugKeyword,
                'params'     => ['fc' => 'module', 'module' => 'cci_blog'],
            ],
            'module-cci_blog-category-alternate' => [
                'controller' => 'category',
                'rule'       => $alternateCategoryRule,
                'keywords'   => $slugKeyword,
                'params'     => ['fc' => 'module', 'module' => 'cci_blog'],
            ],
            'module-cci_blog-category-page' => [
                'controller' => 'category',
                'rule'       => $base . '/category/{slug}/page/{page}' . $pageSuffix,
                'keywords'   => $slugPageKeywords,
                'params'     => ['fc' => 'module', 'module' => 'cci_blog'],
            ],
            'module-cci_blog-category-page-alternate' => [
                'controller' => 'category',
                'rule'       => $base . '/category/{slug}/page/{page}' . $alternatePageSuffix,
                'keywords'   => $slugPageKeywords,
                'params'     => ['fc' => 'module', 'module' => 'cci_blog'],
            ],
            'module-cci_blog-tag' => [
                'controller' => 'tag',
                'rule'       => $base . '/tag/{slug}',
                'keywords'   => $slugKeyword,
                'params'     => ['fc' => 'module', 'module' => 'cci_blog'],
            ],
            'module-cci_blog-tag-page' => [
                'controller' => 'tag',
                'rule'       => $base . '/tag/{slug}/page/{page}' . $pageSuffix,
                'keywords'   => $slugPageKeywords,
                'params'     => ['fc' => 'module', 'module' => 'cci_blog'],
            ],
            'module-cci_blog-tag-page-alternate' => [
                'controller' => 'tag',
                'rule'       => $base . '/tag/{slug}/page/{page}' . $alternatePageSuffix,
                'keywords'   => $slugPageKeywords,
                'params'     => ['fc' => 'module', 'module' => 'cci_blog'],
            ],
            'module-cci_blog-author' => [
                'controller' => 'author',
                'rule'       => $base . '/author/{slug}',
                'keywords'   => $slugKeyword,
                'params'     => ['fc' => 'module', 'module' => 'cci_blog'],
            ],
            'module-cci_blog-author-page' => [
                'controller' => 'author',
                'rule'       => $base . '/author/{slug}/page/{page}' . $pageSuffix,
                'keywords'   => $slugPageKeywords,
                'params'     => ['fc' => 'module', 'module' => 'cci_blog'],
            ],
            'module-cci_blog-author-page-alternate' => [
                'controller' => 'author',
                'rule'       => $base . '/author/{slug}/page/{page}' . $alternatePageSuffix,
                'keywords'   => $slugPageKeywords,
                'params'     => ['fc' => 'module', 'module' => 'cci_blog'],
            ],
            'module-cci_blog-feed' => [
                'controller' => 'feed',
                'rule'       => $base . '/feed.xml',
                'keywords'   => [],
                'params'     => ['fc' => 'module', 'module' => 'cci_blog'],
            ],
            'module-cci_blog-search' => [
                'controller' => 'search',
                'rule'       => $base . '/search',
                'keywords'   => [],
                'params'     => ['fc' => 'module', 'module' => 'cci_blog'],
            ],
            'module-cci_blog-search-page' => [
                'controller' => 'search',
                'rule'       => $base . '/search/page/{page}' . $pageSuffix,
                'keywords'   => $pageKeyword,
                'params'     => ['fc' => 'module', 'module' => 'cci_blog'],
            ],
            'module-cci_blog-search-page-alternate' => [
                'controller' => 'search',
                'rule'       => $base . '/search/page/{page}' . $alternatePageSuffix,
                'keywords'   => $pageKeyword,
                'params'     => ['fc' => 'module', 'module' => 'cci_blog'],
            ],
            'module-cci_blog-post' => [
                'controller' => 'post',
                'rule'       => $postRule,
                'keywords'   => $slugKeyword,
                'params'     => ['fc' => 'module', 'module' => 'cci_blog'],
            ],
            'module-cci_blog-post-alternate' => [
                'controller' => 'post',
                'rule'       => $alternatePostRule,
                'keywords'   => $slugKeyword,
                'params'     => ['fc' => 'module', 'module' => 'cci_blog'],
            ],
        ];
    }

    // -------------------------------------------------------------------------
    // Front hooks
    // -------------------------------------------------------------------------

    public function hookDisplayHome(): string
    {
        if (!(int) Configuration::get('CCB_SHOW_FEATURED_WIDGET')) {
            return '';
        }
        $posts = CciBlogPost::getLatest(5, $this->context->language->id, $this->context->shop->id);
        $this->context->smarty->assign([
            'ccb_posts'          => $posts,
            'ccb_base'           => Configuration::get('CCB_BASE_SLUG'),
            'ccb_show_author'    => (bool) Configuration::get('CCB_SHOW_AUTHOR'),
            'ccb_show_date'      => (bool) Configuration::get('CCB_SHOW_DATE'),
            'ccb_show_views'     => (bool) Configuration::get('CCB_SHOW_VIEWS'),
            'ccb_show_read_time' => (bool) Configuration::get('CCB_SHOW_READ_TIME'),
        ]);
        return $this->display(__FILE__, 'views/templates/hook/home_widget.tpl');
    }

    /**
     * Add the blog index, active categories and published posts to the XML
     * sitemap generated by PrestaShop's gsitemap module.
     */
    public function hookGSitemapAppendUrls(array $params): array
    {
        $language = isset($params['lang']) && is_array($params['lang']) ? $params['lang'] : [];
        $langId = (int) ($language['id_lang'] ?? $this->context->language->id);
        $shopId = (int) $this->context->shop->id;

        if ($langId <= 0 || $shopId <= 0) {
            return [];
        }

        $posts = CciBlogSeo::getSitemapEntries($langId, $shopId);
        $categories = CciBlogSeo::getSitemapCategoryEntries($langId, $shopId);
        $lastModified = $this->latestSitemapUpdate($posts, $categories);
        $links = [[
            'link' => $this->context->link->getModuleLink($this->name, 'list', [], null, $langId, $shopId),
            'page' => 'cms',
            'lastmod' => $lastModified,
            'image' => false,
        ]];

        foreach ($categories as $category) {
            $links[] = [
                'link' => $this->context->link->getModuleLink(
                    $this->name,
                    'category',
                    ['slug' => (string) $category['slug']],
                    null,
                    $langId,
                    $shopId
                ),
                'page' => 'category',
                'lastmod' => (string) ($category['date_upd'] ?? ''),
                'image' => false,
            ];
        }

        foreach ($posts as $post) {
            $imageUrl = $this->absoluteSitemapImageUrl((string) ($post['cover_image'] ?? ''), $shopId);
            $links[] = [
                'link' => $this->context->link->getModuleLink(
                    $this->name,
                    'post',
                    ['slug' => (string) $post['slug']],
                    null,
                    $langId,
                    $shopId
                ),
                'page' => 'cms',
                'lastmod' => (string) ($post['date_upd'] ?? ''),
                'image' => $imageUrl !== '' ? ['link' => $imageUrl] : false,
            ];
        }

        return $links;
    }

    /**
     * Add blog links to PrestaShop's public HTML sitemap page.
     */
    public function hookActionModifyFrontendSitemap(array $params): void
    {
        if (!isset($params['urls']) || !is_array($params['urls'])) {
            return;
        }

        $langId = (int) $this->context->language->id;
        $shopId = (int) $this->context->shop->id;
        $posts = CciBlogSeo::getSitemapEntries($langId, $shopId);
        $categories = CciBlogSeo::getSitemapCategoryEntries($langId, $shopId);

        $categoryLinks = [];
        foreach ($categories as $category) {
            $categoryLinks[] = [
                'id' => 'cci-blog-category-' . (int) $category['id_category'],
                'parent_id' => (int) $category['id_parent'],
                'category_id' => (int) $category['id_category'],
                'label' => (string) $category['name'],
                'url' => $this->context->link->getModuleLink(
                    $this->name,
                    'category',
                    ['slug' => (string) $category['slug']],
                    null,
                    $langId,
                    $shopId
                ),
            ];
        }

        $links = [[
            'id' => 'cci-blog-home',
            'label' => $this->l('Blog home'),
            'url' => $this->context->link->getModuleLink($this->name, 'list', [], null, $langId, $shopId),
        ]];
        $links = array_merge($links, $this->buildSitemapCategoryTree($categoryLinks));

        foreach ($posts as $post) {
            $links[] = [
                'id' => 'cci-blog-post-' . (int) $post['id_post'],
                'label' => (string) $post['title'],
                'url' => $this->context->link->getModuleLink(
                    $this->name,
                    'post',
                    ['slug' => (string) $post['slug']],
                    null,
                    $langId,
                    $shopId
                ),
            ];
        }

        $params['urls']['cci_blog'] = [
            'name' => $this->l('Blog'),
            'links' => $links,
        ];
    }

    private function buildSitemapCategoryTree(array $categories): array
    {
        $byId = [];
        foreach ($categories as $category) {
            $category['children'] = [];
            $byId[(int) $category['category_id']] = $category;
        }

        $roots = [];
        foreach (array_keys($byId) as $categoryId) {
            $parentId = (int) $byId[$categoryId]['parent_id'];
            unset($byId[$categoryId]['parent_id'], $byId[$categoryId]['category_id']);

            if ($parentId > 0 && $parentId !== $categoryId && isset($byId[$parentId])) {
                $byId[$parentId]['children'][] = &$byId[$categoryId];
            } else {
                $roots[] = &$byId[$categoryId];
            }
        }

        return $roots;
    }

    private function latestSitemapUpdate(array $posts, array $categories): string
    {
        $dates = [];
        foreach (array_merge($posts, $categories) as $entry) {
            if (!empty($entry['date_upd'])) {
                $dates[] = (string) $entry['date_upd'];
            }
        }

        return $dates ? max($dates) : '';
    }

    private function absoluteSitemapImageUrl(string $url, int $shopId): string
    {
        $url = trim($url);
        if ($url === '') {
            return '';
        }
        if (preg_match('#^https?://#i', $url)) {
            return $url;
        }
        if (str_starts_with($url, '//')) {
            return (Configuration::get('PS_SSL_ENABLED') ? 'https:' : 'http:') . $url;
        }

        return rtrim($this->context->link->getBaseLink($shopId), '/') . '/' . ltrim($url, '/');
    }

    public function hookDisplayCciBlogFeatures(array $params): array
    {
        return [];
    }

    public function hookDisplayCciBlogAdminExtensions(array $params): array
    {
        return [];
    }

    public function hookDisplayCciBlogAdminBlockExtensions(array $params): array
    {
        return [];
    }

    public function hookDisplayCciBlogContentHookOptions(array $params): array
    {
        return [];
    }

    public function hookActionFrontControllerSetMedia(array $params = []): void
    {
        $controllerName = (string) Tools::getValue('controller');
        $frontController = $this->context->controller;
        $isBlogController = $frontController instanceof ModuleFrontController
            && isset($frontController->module)
            && $frontController->module instanceof Module
            && $frontController->module->name === $this->name;
        $isBlogRequest = (string) Tools::getValue('module') === $this->name;
        $baseSlug = trim((string) (Configuration::get('CCB_BASE_SLUG') ?: 'blog'), '/');
        $requestPath = trim((string) parse_url((string) ($_SERVER['REQUEST_URI'] ?? ''), PHP_URL_PATH), '/');
        $isBlogPath = $baseSlug !== ''
            && ($requestPath === $baseSlug || str_starts_with($requestPath, $baseSlug . '/'));
        $isBlogPage = $isBlogController
            || $isBlogRequest
            || $isBlogPath
            || in_array($controllerName, ['list', 'post', 'category', 'tag', 'author', 'search'], true);
        $frontJsVersion = (string) (@filemtime(__DIR__ . '/views/js/front.js') ?: $this->version);
        $frontCssVersion = (string) (@filemtime(__DIR__ . '/views/css/cci_blog_front.css') ?: $this->version);

        if ($isBlogPage && $this->getOwlAssetsSource() === 'module') {
            $this->context->controller->registerStylesheet(
                'cci_blog-owl-carousel',
                'modules/' . $this->name . '/views/vendor/owlcarousel/assets/owl.carousel.min.css',
                ['media' => 'all', 'priority' => 190]
            );
        }
        $this->context->controller->registerStylesheet(
            'cci-blog-front-styles',
            __PS_BASE_URI__ . 'modules/' . $this->name . '/views/css/cci_blog_front.css',
            [
                'media' => 'all',
                'priority' => 191,
                'version' => $frontCssVersion,
                'server' => 'remote',
                'needRtl' => false,
            ]
        );

        if (!$isBlogPage) {
            return;
        }

        if ($this->getOwlAssetsSource() === 'module') {
            $this->context->controller->registerJavascript(
                'cci_blog-owl-carousel',
                'modules/' . $this->name . '/views/vendor/owlcarousel/owl.carousel.min.js',
                ['position' => 'bottom', 'priority' => 190]
            );
        }
        $this->context->controller->registerJavascript(
            'cci-blog-front',
            __PS_BASE_URI__ . 'modules/' . $this->name . '/views/js/front.js',
            [
                'position' => 'bottom',
                'priority' => 200,
                'server' => 'remote',
                'version' => $frontJsVersion,
            ]
        );
    }

    private function getOwlAssetsSource(): string
    {
        $source = (string) Configuration::get('CCB_OWL_ASSETS_SOURCE');
        if (in_array($source, ['module', 'theme'], true)) {
            return $source;
        }

        return ((int) Configuration::get('CCB_LOAD_OWL_LIBRARY') || (int) Configuration::get('CCB_LOAD_OWL_STYLES'))
            ? 'module'
            : 'theme';
    }

    // -------------------------------------------------------------------------
    // Module configuration page
    // -------------------------------------------------------------------------

    public function getContent(): string
    {
        $this->installTabs();

        Tools::redirectAdmin(
            Context::getContext()->link->getAdminLink('AdminCciBlog')
        );
        return '';
    }
}
