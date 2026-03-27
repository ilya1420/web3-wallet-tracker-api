<?php

declare(strict_types=1);

namespace App\Application\Service;

use App\Application\Exception\RateLimitExceededException;
use Symfony\Component\RateLimiter\RateLimiterFactory;

final readonly class LoginRateLimiterService
{
    public function __construct(private RateLimiterFactory $loginRequestLimiter)
    {
    }

    public function assertCanAttempt(string $email): void
    {
        $limiter = $this->loginRequestLimiter->create(mb_strtolower(trim($email)));
        $limit = $limiter->consume(1);

        if (!$limit->isAccepted()) {
            throw new RateLimitExceededException('Too many login attempts. Please try again later.');
        }
    }
}
