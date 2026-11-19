<?php

declare(strict_types=1);

use CCI\Blog\Integrations\VerificationRequest;

if (!defined('_PS_VERSION_')) {
    exit;
}

require_once __DIR__ . '/CciBlogCaptchaVerificationRequest.php';

final class CciBlogCaptcha
{
    public const OPTION = 'CCB_CAPTCHA_INTEGRATIONS';
    private const PROVIDERS = [
        'recaptcha' => [
            'title' => 'Google reCAPTCHA v2',
            'description' => 'Protect forms with the Google reCAPTCHA checkbox.',
            'setupUrl' => 'https://www.google.com/recaptcha/admin/create',
            'endpoint' => 'https://www.google.com/recaptcha/api/siteverify',
            'lifetime' => 120,
        ],
        'turnstile' => [
            'title' => 'Cloudflare Turnstile',
            'description' => 'Protect forms with Cloudflare Turnstile.',
            'setupUrl' => 'https://dash.cloudflare.com/?to=/:account/turnstile',
            'endpoint' => 'https://challenges.cloudflare.com/turnstile/v0/siteverify',
            'lifetime' => 300,
        ],
    ];

    private static function state(int $shopId): array
    {
        $raw = Configuration::get(self::OPTION, null, null, $shopId);
        $state = $raw === false ? [] : json_decode((string) $raw, true, 16, JSON_THROW_ON_ERROR);
        if (!is_array($state) || !is_string($state['active'] ?? '') || !is_array($state['providers'] ?? [])) {
            throw new RuntimeException('Invalid integration settings.');
        }

        return $state + ['active' => '', 'providers' => []];
    }

    public static function admin(int $shopId): array
    {
        $state = self::state($shopId);
        $items = [];
        foreach (self::PROVIDERS as $id => $provider) {
            $saved = $state['providers'][$id] ?? [];
            $items[] = [
                'id' => $id,
                'enabled' => $state['active'] === $id,
                'siteKey' => $saved['siteKey'] ?? '',
                'hasSecret' => !empty($saved['secret']),
                'title' => $provider['title'],
                'description' => $provider['description'],
                'setupUrl' => $provider['setupUrl'],
            ];
        }

        return ['items' => $items, 'revision' => self::revision($state)];
    }

    private static function revision(array $state): string
    {
        return hash('sha256', json_encode($state, JSON_THROW_ON_ERROR));
    }

    private static function encryptionKey(int $shopId): string
    {
        return hash_hmac('sha256', 'cci-blog-integrations:' . $shopId, (string) _COOKIE_KEY_, true);
    }

    private static function encrypt(string $value, int $shopId): string
    {
        $iv = random_bytes(12);
        $tag = '';
        $encrypted = openssl_encrypt($value, 'aes-256-gcm', self::encryptionKey($shopId), OPENSSL_RAW_DATA, $iv, $tag, 'CCI Blog', 16);
        if ($encrypted === false) {
            throw new RuntimeException('The integration key could not be stored.');
        }

        return base64_encode($iv . $tag . $encrypted);
    }

    private static function decrypt(string $value, int $shopId): string
    {
        $raw = base64_decode($value, true);
        $secret = $raw !== false && strlen($raw) > 28
            ? openssl_decrypt(substr($raw, 28), 'aes-256-gcm', self::encryptionKey($shopId), OPENSSL_RAW_DATA, substr($raw, 0, 12), substr($raw, 12, 16), 'CCI Blog')
            : false;
        if ($secret === false) {
            throw new RuntimeException('The integration key could not be read. Enter it again in Integrations.');
        }

        return $secret;
    }

    public static function save(int $shopId, array $input): array
    {
        $id = $input['id'] ?? null;
        if (!is_string($id) || !isset(self::PROVIDERS[$id]) || !is_bool($input['enabled'] ?? null)) {
            throw new InvalidArgumentException('Invalid integration settings.');
        }
        foreach (['siteKey', 'secret'] as $key) {
            $value = $input[$key] ?? '';
            if (!is_string($value) || strlen($value) > 512 || preg_match('/[\x00-\x20\x7f]/', $value)) {
                throw new InvalidArgumentException('Check the integration keys.');
            }
        }
        $db = Db::getInstance();
        $lock = 'cci_blog_captcha_' . $shopId;
        if ((int) $db->getValue("SELECT GET_LOCK('" . $lock . "', 5)", false) !== 1) {
            throw new RuntimeException('Could not save integration settings. Please try again.');
        }
        try {
            Configuration::loadConfiguration();
            $state = self::state($shopId);
            if (!is_string($input['revision'] ?? null) || !hash_equals(self::revision($state), $input['revision'])) {
                throw new InvalidArgumentException('Integration settings have changed. Reload them before saving.');
            }
            $saved = $state['providers'][$id] ?? [];
            $siteKey = $input['siteKey'] ?? ($saved['siteKey'] ?? '');
            $secret = !empty($input['secret']) ? self::encrypt($input['secret'], $shopId) : ($saved['secret'] ?? '');
            if ($input['enabled']) {
                if ($siteKey === '' || $secret === '') {
                    throw new InvalidArgumentException('Enter both integration keys before enabling protection.');
                }
                self::decrypt($secret, $shopId);
            }
            $state['providers'][$id] = ['siteKey' => $siteKey, 'secret' => $secret];
            if ($input['enabled']) {
                $state['active'] = $id;
            } elseif ($state['active'] === $id) {
                $state['active'] = '';
            }
            if (!Configuration::updateValue(self::OPTION, json_encode($state, JSON_THROW_ON_ERROR), false, null, $shopId)) {
                throw new RuntimeException('Could not save integration settings. Please try again.');
            }

            return self::admin($shopId);
        } finally {
            $db->getValue("SELECT RELEASE_LOCK('" . $lock . "')", false);
        }
    }

    public static function publicConfig(int $shopId): ?array
    {
        try {
            $state = self::state($shopId);
            $id = $state['active'];
            if ($id === '') {
                return null;
            }

            return ['provider' => $id, 'siteKey' => $state['providers'][$id]['siteKey'] ?? ''];
        } catch (Throwable $error) {
            return ['provider' => 'unavailable', 'siteKey' => ''];
        }
    }

    public static function verify(Context $context, $token, string $operation): bool
    {
        try {
            $shopId = (int) $context->shop->id;
            $state = self::state($shopId);
            $id = $state['active'];
            if ($id === '') {
                return true;
            }
            $provider = self::PROVIDERS[$id] ?? null;
            if (!$provider || !is_string($token) || $token === '' || strlen($token) > ($id === 'turnstile' ? 2048 : 8192)) {
                return false;
            }
            $domain = (string) (Tools::usingSecureMode() ? $context->shop->domain_ssl : $context->shop->domain);
            $host = strtolower((string) parse_url('https://' . $domain, PHP_URL_HOST));
            $response = VerificationRequest::post($provider['endpoint'], [
                'secret' => self::decrypt($state['providers'][$id]['secret'] ?? '', $shopId),
                'response' => $token,
            ]);
            $timestamp = strtotime((string) ($response['challenge_ts'] ?? ''));
            $now = time();

            return ($response['success'] ?? null) === true
                && $host !== '' && hash_equals($host, strtolower((string) ($response['hostname'] ?? '')))
                && $timestamp !== false && $timestamp <= $now + 30 && $timestamp >= $now - $provider['lifetime']
                && ($id !== 'turnstile' || ($response['action'] ?? '') === 'cci_blog_' . $operation);
        } catch (Throwable $error) {
            return false;
        }
    }
}
