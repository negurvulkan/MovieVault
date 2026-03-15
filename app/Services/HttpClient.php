<?php

declare(strict_types=1);

namespace App\Services;

use App\Core\Config;
use RuntimeException;

final class HttpClient
{
    public function __construct(private readonly Config $config)
    {
    }

    public function getJson(string $url, array $headers = []): array
    {
        $response = $this->get($url, $headers);
        $decoded = json_decode($response, true);

        if (!is_array($decoded)) {
            throw new RuntimeException('Ungueltige JSON-Antwort von externer Quelle.');
        }

        return $decoded;
    }

    public function getBinary(string $url, array $headers = []): string
    {
        return $this->get($url, $headers);
    }

    private function get(string $url, array $headers = []): string
    {
        if (function_exists('curl_init')) {
            $ch = curl_init($url);
            curl_setopt_array($ch, [
                CURLOPT_RETURNTRANSFER => true,
                CURLOPT_FOLLOWLOCATION => true,
                CURLOPT_TIMEOUT => (int) $this->config->get('metadata.http_timeout', 15),
                CURLOPT_HTTPHEADER => array_map(
                    static fn (string $name, string $value): string => $name . ': ' . $value,
                    array_keys($headers),
                    array_values($headers)
                ),
                CURLOPT_USERAGENT => (string) $this->config->get('metadata.user_agent', 'MovieVault/1.0'),
            ]);
            $body = curl_exec($ch);
            $status = (int) curl_getinfo($ch, CURLINFO_RESPONSE_CODE);
            $error = curl_error($ch);
            curl_close($ch);

            if ($body === false || $status >= 400) {
                throw new RuntimeException($error !== '' ? $error : 'HTTP-Anfrage fehlgeschlagen.');
            }

            return (string) $body;
        }

        if (!ini_get('allow_url_fopen')) {
            throw new RuntimeException('Weder cURL noch allow_url_fopen stehen fuer HTTP-Zugriffe bereit.');
        }

        $headerLines = '';
        foreach ($headers as $name => $value) {
            $headerLines .= $name . ': ' . $value . "\r\n";
        }
        $headerLines .= 'User-Agent: ' . $this->config->get('metadata.user_agent', 'MovieVault/1.0');

        $context = stream_context_create([
            'http' => [
                'method' => 'GET',
                'timeout' => (int) $this->config->get('metadata.http_timeout', 15),
                'header' => $headerLines,
                'ignore_errors' => true,
            ],
        ]);
        $body = @file_get_contents($url, false, $context);
        if ($body === false) {
            throw new RuntimeException('HTTP-Anfrage konnte nicht ausgefuehrt werden.');
        }

        return $body;
    }
}
