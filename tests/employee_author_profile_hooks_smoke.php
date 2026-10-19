<?php

declare(strict_types=1);

$moduleRoot = dirname(__DIR__);
$modulePhp = (string) file_get_contents($moduleRoot . '/cci_blog.php');
$installSql = (string) file_get_contents($moduleRoot . '/sql/install.sql');

$assert = static function (bool $condition, string $message): void {
    if (!$condition) {
        fwrite(STDERR, "FAIL: {$message}\n");
        exit(1);
    }
};

foreach ([
    'actionEmployeeFormBuilderModifier',
    'actionEmployeeFormDataProviderData',
    'actionEmployeeFormDataProviderDefaultData',
    'actionAfterCreateEmployeeFormHandler',
    'actionAfterUpdateEmployeeFormHandler',
] as $hookName) {
    $assert(str_contains($modulePhp, "'{$hookName}'"), "The module must register {$hookName}.");
}

$assert(str_contains($modulePhp, "'cci_blog_author_profile'"), 'The employee form must expose a CCI Blog author profile group.');
$assert(str_contains($modulePhp, 'PrestaShopBundle\\Form\\Admin\\Type\\CardType::class'), 'The author profile must render as a separate PrestaShop card.');
$assert(str_contains($modulePhp, "->add('bio'"), 'The author profile must expose a biography.');
$assert(str_contains($modulePhp, 'canEditAuthorTranslations'), 'Biography translations must use the Pro translation entitlement.');
$assert(str_contains($modulePhp, 'TranslatableType::class'), 'Pro must expose the translatable biography field.');
$assert(str_contains($modulePhp, 'TextareaType::class'), 'Free must expose a single-language biography field.');
$assert(
    str_contains($modulePhp, '$languageIds = [$defaultLanguageId]'),
    'Free must persist only the default-language biography.'
);
$assert(
    str_contains($modulePhp, '$biographies[$this->getDefaultShopLanguageId()]'),
    'Free must load only the default-language biography into the employee form.'
);
$assert(str_contains($installSql, 'PREFIX_cci_blog_author'), 'The module must own the author profile table.');
$assert(str_contains($installSql, 'PREFIX_cci_blog_author_lang'), 'The module must own translatable author biographies.');
$assert(
    !preg_match('/ORDER BY `active` DESC, `id_author` DESC\s+LIMIT 1/', $modulePhp),
    'Db::getRow() and Db::getValue() append their own LIMIT clause.'
);

fwrite(STDOUT, "OK: employee form hooks expose and persist the CCI Blog author profile.\n");
