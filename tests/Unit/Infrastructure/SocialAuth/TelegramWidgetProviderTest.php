<?php

declare(strict_types=1);

namespace App\Tests\Unit\Infrastructure\SocialAuth;

use App\Application\Exception\SocialAuthException;
use App\Infrastructure\SocialAuth\TelegramWidgetProvider;
use PHPUnit\Framework\TestCase;

final class TelegramWidgetProviderTest extends TestCase
{
    public function testVerifiesTelegramPayloadHash(): void
    {
        $provider = new TelegramWidgetProvider('bot-token');
        $payload = [
            'id' => '123456',
            'first_name' => 'Alice',
            'last_name' => 'Example',
            'username' => 'alice',
            'auth_date' => (string) time(),
        ];
        $payload['hash'] = $this->telegramHash($payload, 'bot-token');

        $profile = $provider->authenticate($payload);

        self::assertSame('telegram', $profile->provider);
        self::assertSame('123456', $profile->providerUserId);
        self::assertSame('Alice Example', $profile->displayName);
        self::assertNull($profile->email);
    }

    public function testRejectsInvalidTelegramHash(): void
    {
        $provider = new TelegramWidgetProvider('bot-token');

        $this->expectException(SocialAuthException::class);
        $this->expectExceptionMessage('Telegram auth hash is invalid.');

        $provider->authenticate([
            'id' => '123456',
            'auth_date' => (string) time(),
            'hash' => 'invalid',
        ]);
    }

    /**
     * @param array<string, string> $payload
     */
    private function telegramHash(array $payload, string $botToken): string
    {
        $pairs = [];
        foreach ($payload as $key => $value) {
            $pairs[] = $key . '=' . $value;
        }
        sort($pairs, SORT_STRING);

        return hash_hmac('sha256', implode("\n", $pairs), hash('sha256', $botToken, true));
    }
}
