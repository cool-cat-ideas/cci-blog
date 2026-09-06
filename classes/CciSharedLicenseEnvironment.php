<?php

declare(strict_types=1);

if (!class_exists('CciSharedLicenseEnvironment')) {
    final class CciSharedLicenseEnvironment
    {
        public const ENVIRONMENT_PRODUCTION = 'production';
        public const ENVIRONMENT_STAGING = 'staging';

        public static function normalizeDomain(string $url): string
        {
            $url = trim($url);
            if ($url === '') {
                return '';
            }

            $candidate = preg_match('#^[a-z][a-z0-9+.-]*://#i', $url) ? $url : '//' . ltrim($url, '/');
            $host = parse_url($candidate, PHP_URL_HOST);

            if (!$host) {
                $host = $url;
            }

            $host = trim((string) $host);
            if ($host === '') {
                return '';
            }

            $host = preg_replace('~[/?#].*$~', '', $host);
            if (!is_string($host) || $host === '') {
                return '';
            }

            $host = trim($host, " \t\n\r\0\x0B[]");
            if (substr_count($host, ':') <= 1) {
                $host = (string) preg_replace('/:\d+$/', '', $host);
            }

            $host = strtolower(rtrim($host, '.'));
            $host = (string) preg_replace('/^www\./', '', $host);

            return $host;
        }

        public static function normalizeEnvironment(string $environment): string
        {
            $environment = strtolower(trim($environment));
            $environment = (string) preg_replace('/[^a-z0-9_-]/', '', $environment);

            return $environment === self::ENVIRONMENT_PRODUCTION
                ? self::ENVIRONMENT_PRODUCTION
                : self::ENVIRONMENT_STAGING;
        }

        public static function resolveSiteEnvironment(string $siteUrl, string $platformEnvironment = self::ENVIRONMENT_PRODUCTION, bool $forceStaging = false): string
        {
            $environment = self::normalizeEnvironment($platformEnvironment);

            if ($forceStaging || $environment !== self::ENVIRONMENT_PRODUCTION || self::isLocalOrStagingDomain($siteUrl)) {
                return self::ENVIRONMENT_STAGING;
            }

            return self::ENVIRONMENT_PRODUCTION;
        }

        public static function isLocalOrStagingDomain(string $url): bool
        {
            $host = self::normalizeDomain($url);
            if ($host === '') {
                return false;
            }

            if (in_array($host, ['localhost', '127.0.0.1', '::1'], true)) {
                return true;
            }

            if (preg_match('/\.(local|localhost|test|invalid)$/', $host)) {
                return true;
            }

            if (filter_var($host, FILTER_VALIDATE_IP)) {
                return filter_var($host, FILTER_VALIDATE_IP, FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE) === false;
            }

            return false;
        }

        public static function siteIdentity(string $siteUrl, string $platformEnvironment = self::ENVIRONMENT_PRODUCTION, bool $forceStaging = false): array
        {
            return [
                'site_url' => $siteUrl,
                'domain' => self::normalizeDomain($siteUrl),
                'environment' => self::resolveSiteEnvironment($siteUrl, $platformEnvironment, $forceStaging),
            ];
        }
    }
}
