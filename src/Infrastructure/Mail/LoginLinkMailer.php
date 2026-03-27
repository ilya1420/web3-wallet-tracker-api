<?php

declare(strict_types=1);

namespace App\Infrastructure\Mail;

use Symfony\Bridge\Twig\Mime\TemplatedEmail;
use Symfony\Component\Mailer\MailerInterface;

final readonly class LoginLinkMailer
{
    public function __construct(
        private MailerInterface $mailer,
        private string $appUrl,
    ) {
    }

    public function send(string $recipientEmail, string $token): void
    {
        $confirmEndpoint = sprintf('%s/api/auth/confirm-token', rtrim($this->appUrl, '/'));
        $requestPayload = json_encode([
            'email' => $recipientEmail,
            'token' => $token,
        ], JSON_THROW_ON_ERROR);

        $email = (new TemplatedEmail())
            ->to($recipientEmail)
            ->subject('Your secure login link')
            ->htmlTemplate('emails/login_link.html.twig')
            ->context([
                'confirmEndpoint' => $confirmEndpoint,
                'requestPayload' => $requestPayload,
                'token' => $token,
            ]);

        $this->mailer->send($email);
    }
}
