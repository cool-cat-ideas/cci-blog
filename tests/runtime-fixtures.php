<?php

declare(strict_types=1);

if (PHP_SAPI !== 'cli') {
    http_response_code(404);
    exit;
}

require_once getenv('CCI_BLOG_TEST_BOOTSTRAP') ?: dirname(__DIR__, 3) . '/config/config.inc.php';
$file = sys_get_temp_dir() . '/cci-blog-editorial-qa.json';
$action = $argv[1] ?? '';
if ($action === 'setup') {
    if (is_file($file)) {
        throw new RuntimeException('Clean up the previous QA employee first.');
    }
    $password = bin2hex(random_bytes(12)) . 'Aa1!';
    $employee = new Employee();
    $employee->firstname = getenv('CCI_BLOG_QA_FIRSTNAME') ?: 'Blog';
    $employee->lastname = getenv('CCI_BLOG_QA_LASTNAME') ?: 'QA';
    $employee->email = 'cci-blog-qa-' . bin2hex(random_bytes(5)) . '@example.invalid';
    $employee->passwd = password_hash($password, PASSWORD_BCRYPT);
    $employee->id_profile = 1;
    $employee->id_lang = (int) Configuration::get('PS_LANG_DEFAULT');
    $employee->default_tab = 1;
    $employee->active = 1;
    if (!$employee->add()) {
        throw new RuntimeException('Could not create the temporary QA employee.');
    }
    $data = ['employeeId' => (int) $employee->id, 'email' => $employee->email, 'password' => $password];
    file_put_contents($file, json_encode($data));
    chmod($file, 0600);
    echo json_encode($data) . "\n";
} elseif ($action === 'cleanup') {
    $data = json_decode((string) file_get_contents($file), true);
    $employee = new Employee((int) $data['employeeId']);
    if ($employee->email !== $data['email'] || !str_starts_with($employee->email, 'cci-blog-qa-')) {
        throw new RuntimeException('Unexpected fixture employee.');
    }
    $posts = Db::getInstance()->executeS('SELECT id_post FROM `' . _DB_PREFIX_ . 'cci_blog_post` WHERE id_author = ' . (int) $employee->id) ?: [];
    foreach ($posts as $post) {
        $id = (int) $post['id_post'];
        foreach (['cci_blog_post_revision', 'cci_blog_post_lang', 'cci_blog_post_shop', 'cci_blog_post_category', 'cci_blog_post_tag', 'cci_blog_slug_redirect', 'cci_blog_post'] as $table) {
            Db::getInstance()->delete($table, 'id_post = ' . $id);
        }
    }
    $employee->delete();
    unlink($file);
    echo "Temporary QA employee and posts removed.\n";
} else {
    throw new InvalidArgumentException('Use setup or cleanup.');
}
