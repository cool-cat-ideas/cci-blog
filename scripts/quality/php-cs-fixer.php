<?php

declare(strict_types=1);

$tools = getenv('CCI_PHP_QUALITY_TOOLS_DIR') ?: __DIR__;
require_once $tools . '/vendor/autoload.php';

$config = new class extends PrestaShop\CodingStandards\CsFixer\Config {
    public function getRules(): array
    {
        return array_merge(parent::getRules(), [
            'array_syntax' => ['syntax' => 'short'],
            'class_attributes_separation' => ['elements' => ['method' => 'one']],
            'blank_line_before_statement' => ['statements' => ['return', 'try']],
        ]);
    }
};

$list = getenv('CCI_FORMAT_PHP_FILE_LIST');
if (!$list || !is_file($list)) {
    throw new RuntimeException('Use npm run format or npm run format:check to select owned source files.');
}
$files = json_decode(file_get_contents($list), true, 512, JSON_THROW_ON_ERROR);
$config->setUsingCache(false);
$config->setFinder((new PhpCsFixer\Finder())->append($files));

return $config;
