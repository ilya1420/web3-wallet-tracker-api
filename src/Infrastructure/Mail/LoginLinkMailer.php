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
        $link = sprintf('%s/auth/confirm-token?email=%s&token=%s', rtrim($this->appUrl, '/'), urlencode($recipientEmail), urlencode($token));

        $email = (new TemplatedEmail())
            ->to($recipientEmail)
            ->subject('Your secure login link')
            ->htmlTemplate('emails/login_link.html.twig')
            ->context([
                'loginLink' => $link,
                'token' => $token,
            ]);

        $this->mailer->send($email);
    }
}
