<?php

declare(strict_types=1);

namespace App\Tests\Unit\Application\Service;

use App\Application\Exception\SocialAuthException;
use App\Application\Service\SocialAuthProvider;
use App\Application\Service\SocialAuthService;
use App\Domain\ValueObject\SocialAuthProfile;
use PHPUnit\Framework\TestCase;

final class SocialAuthServiceTest extends TestCase
{
    public function testDelegatesAuthenticationToRegisteredProvider(): void
    {
        $provider = new ServiceTestSocialAuthProvider();
        $service = new SocialAuthService([$provider]);

        $profile = $service->authenticate('test_provider', ['credential' => 'value']);

        self::assertSame('test_provider', $profile->provider);
        self::assertSame(['credential' => 'value'], $provider->lastPayload);
    }

    public function testRejectsUnsupportedProvider(): void
    {
        $service = new SocialAuthService([]);

        $this->expectException(SocialAuthException::class);
        $this->expectExceptionMessage('Unsupported social auth provider.');

        $service->authenticate('missing', []);
    }
}

final class ServiceTestSocialAuthProvider implements SocialAuthProvider
{
    /** @var array<string, mixed>|null */
    public ?array $lastPayload = null;

    public function name(): string
    {
        return 'test_provider';
    }

    public function supportsRedirect(): bool
    {
        return true;
    }

    public function redirectUrl(string $state): string
    {
        return 'https://example.com?state=' . $state;
    }

    public function authenticate(array $payload): SocialAuthProfile
    {
        $this->lastPayload = $payload;

        return new SocialAuthProfile('test_provider', 'subject', 'user@example.com');
    }
}
