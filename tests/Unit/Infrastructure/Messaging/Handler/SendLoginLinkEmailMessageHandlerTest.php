<?php

declare(strict_types=1);

namespace App\Tests\Unit\Infrastructure\Messaging\Handler;

use App\Infrastructure\Mail\LoginLinkMailer;
use App\Infrastructure\Messaging\Handler\SendLoginLinkEmailMessageHandler;
use App\Infrastructure\Messaging\Message\SendLoginLinkEmailMessage;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Mailer\MailerInterface;
use Symfony\Bridge\Twig\Mime\TemplatedEmail;
use Symfony\Component\Mime\RawMessage;

final class SendLoginLinkEmailMessageHandlerTest extends TestCase
{
    public function testHandlerDelegatesToMailer(): void
    {
        $transport = $this->createMock(MailerInterface::class);
        $transport
            ->expects(self::once())
            ->method('send')
            ->with(
                self::callback(static function (RawMessage $message): bool {
                    if (!$message instanceof TemplatedEmail) {
                        return false;
                    }

                    return ($message->getTo()[0]->getAddress() ?? null) === 'alice@example.com'
                        && $message->getSubject() === 'Your secure login link';
                }),
                self::anything(),
            );

        $mailer = new LoginLinkMailer($transport, 'http://localhost:8080', 'no-reply@local.test');
        $handler = new SendLoginLinkEmailMessageHandler($mailer);
        $handler(new SendLoginLinkEmailMessage('alice@example.com', 'raw-token'));
    }
}
