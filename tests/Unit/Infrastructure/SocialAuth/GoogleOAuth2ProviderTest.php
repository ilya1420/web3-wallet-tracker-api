<?php

declare(strict_types=1);

namespace App\Tests\Unit\Infrastructure\SocialAuth;

use App\Infrastructure\SocialAuth\GoogleOAuth2Provider;
use App\Infrastructure\SocialAuth\SocialHttpClientInterface;
use PHPUnit\Framework\Assert;
use PHPUnit\Framework\TestCase;

final class GoogleOAuth2ProviderTest extends TestCase
{
    public function testBuildsGoogleRedirectUrl(): void
    {
        $provider = new GoogleOAuth2Provider(new OAuth2HttpClientStub(), 'client-id', 'secret', 'https://app.test/callback');

        $url = $provider->redirectUrl('state-token');

        self::assertStringStartsWith('https://accounts.google.com/o/oauth2/v2/auth?', $url);
        self::assertStringContainsString('client_id=client-id', $url);
        self::assertStringContainsString('redirect_uri=https%3A%2F%2Fapp.test%2Fcallback', $url);
        self::assertStringContainsString('scope=openid%20email%20profile', $url);
        self::assertStringContainsString('state=state-token', $url);
    }

    public function testExchangesCodeAndVerifiesTokenInfo(): void
    {
        $httpClient = new OAuth2HttpClientStub();
        $provider = new GoogleOAuth2Provider($httpClient, 'client-id', 'secret', 'https://app.test/callback');

        $profile = $provider->authenticate(['code' => 'auth-code']);

        self::assertSame('google_oauth2', $profile->provider);
        self::assertSame('google-subject', $profile->providerUserId);
        self::assertSame('alice@example.com', $profile->email);
        self::assertSame('Alice', $profile->displayName);
        self::assertSame('https://oauth2.googleapis.com/token', $httpClient->lastPostUrl);
        self::assertSame('auth-code', $httpClient->lastPostFields['code']);
    }
}

final class OAuth2HttpClientStub implements SocialHttpClientInterface
{
    public ?string $lastPostUrl = null;

    /** @var array<string, string> */
    public array $lastPostFields = [];

    public function getJson(string $url): array
    {
        Assert::assertStringStartsWith('https://oauth2.googleapis.com/tokeninfo?', $url);

        return [
            'aud' => 'client-id',
            'sub' => 'google-subject',
            'email' => 'alice@example.com',
            'email_verified' => 'true',
            'name' => 'Alice',
        ];
    }

    public function postFormJson(string $url, array $fields): array
    {
        $this->lastPostUrl = $url;
        $this->lastPostFields = $fields;

        return ['id_token' => 'id-token'];
    }
}
