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
        private string $mailFrom,
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
            ->from($this->mailFrom)
            ->to($recipientEmail)
            ->subject('Your secure login link')
            ->htmlTemplate('emails/login_link.html.twig')
            ->context([
                'recipientEmail' => $recipientEmail,
                'confirmEndpoint' => $confirmEndpoint,
                'requestPayload' => $requestPayload,
                'token' => $token,
                'apiRoot' => sprintf('%s/api', rtrim($this->appUrl, '/')),
                'expiresIn' => '15 minutes',
            ]);

        $this->mailer->send($email);
    }
}
