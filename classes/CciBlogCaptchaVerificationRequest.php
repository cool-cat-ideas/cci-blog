<?php

declare(strict_types=1);

namespace CCI\Blog\Integrations;

final class VerificationRequest
{
    public static function post(string $url, array $body): array
    {
        if (!in_array($url, ['https://challenges.cloudflare.com/turnstile/v0/siteverify', 'https://www.google.com/recaptcha/api/siteverify'], true)) {
            throw new \RuntimeException('Unsupported verification destination.');
        }
        if (function_exists('wp_remote_post')) {
            $response = wp_remote_post($url, ['timeout' => 10, 'redirection' => 0, 'sslverify' => true, 'limit_response_size' => 16384, 'body' => $body]);
            if (is_wp_error($response)) {
                throw new \RuntimeException('Verification service unavailable.');
            }
            $status = wp_remote_retrieve_response_code($response);
            $raw = wp_remote_retrieve_body($response);
        } else {
            if (!function_exists('curl_init')) {
                throw new \RuntimeException('Form protection requires PHP cURL.');
            }
            $curl = curl_init($url);
            $raw = '';
            curl_setopt_array($curl, [CURLOPT_POST => true, CURLOPT_POSTFIELDS => http_build_query($body), CURLOPT_CONNECTTIMEOUT => 3, CURLOPT_TIMEOUT => 10, CURLOPT_FOLLOWLOCATION => false, CURLOPT_SSL_VERIFYPEER => true, CURLOPT_SSL_VERIFYHOST => 2,
                CURLOPT_HTTPHEADER => ['Accept: application/json', 'Content-Type: application/x-www-form-urlencoded'],
                CURLOPT_WRITEFUNCTION => static function ($handle, string $chunk) use (&$raw): int {
                    if (strlen($raw) + strlen($chunk) > 16384) {
                        return 0;
                    } $raw .= $chunk;

                    return strlen($chunk);
                },
            ]);

            try {
                $ok = curl_exec($curl);
                $status = (int) curl_getinfo($curl, CURLINFO_HTTP_CODE);
            } finally {
                curl_close($curl);
            }
            if ($ok === false) {
                throw new \RuntimeException('Verification service unavailable.');
            }
        }
        $result = json_decode($raw, true, 16);
        if ($status !== 200 || !is_array($result)) {
            throw new \RuntimeException('Verification service unavailable.');
        }

        return $result;
    }
}
