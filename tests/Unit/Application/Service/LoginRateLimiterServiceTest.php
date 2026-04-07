<?php

declare(strict_types=1);

namespace App\Tests\Unit\Application\Service;

use App\Application\Exception\RateLimitExceededException;
use App\Application\Service\LoginRateLimiterService;
use PHPUnit\Framework\TestCase;
use Symfony\Component\RateLimiter\RateLimiterFactory;
use Symfony\Component\RateLimiter\Storage\InMemoryStorage;

final class LoginRateLimiterServiceTest extends TestCase
{
    public function testAllowsAttemptWithinLimit(): void
    {
        $service = $this->createService(limit: 2);

        $service->assertCanAttempt('alice@example.com');

        self::addToAssertionCount(1);
    }

    public function testThrowsWhenRateLimitIsExceeded(): void
    {
        $service = $this->createService(limit: 1);
        $service->assertCanAttempt('alice@example.com');

        $this->expectException(RateLimitExceededException::class);

        $service->assertCanAttempt(' Alice@example.com ');
    }

    private function createService(int $limit): LoginRateLimiterService
    {
        $factory = new RateLimiterFactory([
            'id' => 'login_request',
            'policy' => 'fixed_window',
            'limit' => $limit,
            'interval' => '1 minute',
        ], new InMemoryStorage());

        return new LoginRateLimiterService($factory);
    }
}
