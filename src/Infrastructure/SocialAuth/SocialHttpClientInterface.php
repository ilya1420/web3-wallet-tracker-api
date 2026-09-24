<?php

declare(strict_types=1);

namespace App\Infrastructure\SocialAuth;

interface SocialHttpClientInterface
{
    /**
     * @return array<string, mixed>
     */
    public function getJson(string $url): array;

    /**
     * @param array<string, string> $fields
     *
     * @return array<string, mixed>
     */
    public function postFormJson(string $url, array $fields): array;
}
