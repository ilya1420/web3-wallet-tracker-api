<?php

declare(strict_types=1);

namespace App\Infrastructure\SocialAuth;

use App\Application\Exception\SocialAuthException;
use App\Application\Service\SocialAuthProvider;
use App\Domain\ValueObject\SocialAuthProfile;

final readonly class TelegramWidgetProvider implements SocialAuthProvider
{
    public function __construct(private string $botToken)
    {
    }

    public function name(): string
    {
        return 'telegram';
    }

    public function supportsRedirect(): bool
    {
        return false;
    }

    public function redirectUrl(string $state): string
    {
        throw new SocialAuthException('Telegram Login Widget does not support redirect flow.');
    }

    public function authenticate(array $payload): SocialAuthProfile
    {
        $hash = (string) ($payload['hash'] ?? '');
        if ($hash === '') {
            throw new SocialAuthException('Telegram auth hash is missing.');
        }

        $data = $payload;
        unset($data['hash']);

        $checkString = $this->buildCheckString($data);
        $secretKey = hash('sha256', $this->botToken, true);
        $computedHash = hash_hmac('sha256', $checkString, $secretKey);

        if (!hash_equals($computedHash, $hash)) {
            throw new SocialAuthException('Telegram auth hash is invalid.');
        }

        $authDate = (int) ($payload['auth_date'] ?? 0);
        if ($authDate <= 0 || $authDate < time() - 86400) {
            throw new SocialAuthException('Telegram auth payload has expired.');
        }

        $telegramId = trim((string) ($payload['id'] ?? ''));
        if ($telegramId === '') {
            throw new SocialAuthException('Telegram user id is missing.');
        }

        $displayName = trim(implode(' ', array_filter([
            is_string($payload['first_name'] ?? null) ? $payload['first_name'] : null,
            is_string($payload['last_name'] ?? null) ? $payload['last_name'] : null,
        ])));

        return new SocialAuthProfile(
            provider: $this->name(),
            providerUserId: $telegramId,
            displayName: $displayName !== '' ? $displayName : null,
            rawProfile: $this->normalizeRawProfile($payload),
        );
    }

    /**
     * @param array<string, mixed> $payload
     */
    private function buildCheckString(array $payload): string
    {
        $pairs = [];
        foreach ($payload as $key => $value) {
            if ($value === null || $value === '') {
                continue;
            }

            if (is_array($value) || is_object($value)) {
                continue;
            }

            $pairs[] = $key . '=' . $value;
        }

        sort($pairs, SORT_STRING);

        return implode("\n", $pairs);
    }

    /**
     * @param array<string, mixed> $payload
     *
     * @return array<string, mixed>
     */
    private function normalizeRawProfile(array $payload): array
    {
        unset($payload['hash']);

        return $payload;
    }
}
