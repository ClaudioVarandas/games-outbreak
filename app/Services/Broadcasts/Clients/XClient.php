<?php

declare(strict_types=1);

namespace App\Services\Broadcasts\Clients;

use Illuminate\Support\Facades\Http;

class XClient
{
    private const string ENDPOINT = 'https://api.x.com/2/tweets';

    /**
     * @param  array{api_key: string, api_secret: string, access_token: string, access_token_secret: string}  $credentials
     */
    public function postTweet(array $credentials, string $text): void
    {
        $body = json_encode(['text' => $text], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        $authHeader = $this->buildAuthorizationHeader('POST', self::ENDPOINT, $credentials);

        Http::withHeaders([
            'Authorization' => $authHeader,
            'Content-Type' => 'application/json',
        ])
            ->throw()
            ->withBody($body, 'application/json')
            ->post(self::ENDPOINT);
    }

    /**
     * Build an OAuth 1.0a user-context Authorization header for a JSON-body POST.
     *
     * JSON bodies are NOT included in the signature base string — only the query params (none here)
     * and the OAuth parameters contribute.
     *
     * @param  array{api_key: string, api_secret: string, access_token: string, access_token_secret: string}  $credentials
     */
    private function buildAuthorizationHeader(string $method, string $url, array $credentials): string
    {
        $oauth = [
            'oauth_consumer_key' => $credentials['api_key'],
            'oauth_nonce' => bin2hex(random_bytes(16)),
            'oauth_signature_method' => 'HMAC-SHA1',
            'oauth_timestamp' => (string) time(),
            'oauth_token' => $credentials['access_token'],
            'oauth_version' => '1.0',
        ];

        $oauth['oauth_signature'] = $this->signature(
            $method,
            $url,
            $oauth,
            $credentials['api_secret'],
            $credentials['access_token_secret'],
        );

        ksort($oauth);

        $parts = [];
        foreach ($oauth as $k => $v) {
            $parts[] = rawurlencode($k).'="'.rawurlencode($v).'"';
        }

        return 'OAuth '.implode(', ', $parts);
    }

    /**
     * @param  array<string, string>  $oauth
     */
    private function signature(string $method, string $url, array $oauth, string $apiSecret, string $accessTokenSecret): string
    {
        ksort($oauth);

        $pairs = [];
        foreach ($oauth as $k => $v) {
            $pairs[] = rawurlencode($k).'='.rawurlencode($v);
        }

        $base = strtoupper($method).'&'.rawurlencode($url).'&'.rawurlencode(implode('&', $pairs));
        $key = rawurlencode($apiSecret).'&'.rawurlencode($accessTokenSecret);

        return base64_encode(hash_hmac('sha1', $base, $key, true));
    }
}
