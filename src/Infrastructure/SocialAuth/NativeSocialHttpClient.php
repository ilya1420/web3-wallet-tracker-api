<?php

declare(strict_types=1);

namespace App\Infrastructure\SocialAuth;

use App\Application\Exception\SocialAuthException;

final readonly class NativeSocialHttpClient implements SocialHttpClientInterface
{
    public function getJson(string $url): array
    {
        return $this->requestJson($url, null);
    }

    public function postFormJson(string $url, array $fields): array
    {
        return $this->requestJson($url, http_build_query($fields, '', '&', PHP_QUERY_RFC3986));
    }

    /**
     * @return array<string, mixed>
     */
    private function requestJson(string $url, ?string $body): array
    {
        $headers = ['Accept: application/json'];
        $context = [
            'http' => [
                'method' => $body === null ? 'GET' : 'POST',
                'header' => implode("\r\n", $body === null ? $headers : [...$headers, 'Content-Type: application/x-www-form-urlencoded']),
                'timeout' => 5,
                'ignore_errors' => true,
            ],
        ];

        if ($body !== null) {
            $context['http']['content'] = $body;
        }

        $response = @file_get_contents($url, false, stream_context_create($context));
        if ($response === false) {
            throw new SocialAuthException('Social provider request failed.');
        }

        try {
            $decoded = json_decode($response, true, 512, JSON_THROW_ON_ERROR);
        } catch (\JsonException $e) {
            throw new SocialAuthException('Social provider returned invalid JSON.', previous: $e);
        }

        if (!is_array($decoded)) {
            throw new SocialAuthException('Social provider returned unsupported response.');
        }

        /** @var array<string, mixed> $decoded */
        return $decoded;
    }
}
