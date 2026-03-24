<?php

declare(strict_types=1);

namespace App\Infrastructure\Messaging\Message;

final readonly class SendLoginLinkEmailMessage
{
    public function __construct(
        public string $email,
        public string $token,
    ) {
    }
}
