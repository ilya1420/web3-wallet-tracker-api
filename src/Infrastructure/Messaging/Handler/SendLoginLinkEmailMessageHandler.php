<?php

declare(strict_types=1);

namespace App\Infrastructure\Messaging\Handler;

use App\Infrastructure\Mail\LoginLinkMailer;
use App\Infrastructure\Messaging\Message\SendLoginLinkEmailMessage;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;

#[AsMessageHandler]
final readonly class SendLoginLinkEmailMessageHandler
{
    public function __construct(private LoginLinkMailer $mailer)
    {
    }

    public function __invoke(SendLoginLinkEmailMessage $message): void
    {
        $this->mailer->send($message->email, $message->token);
    }
}
