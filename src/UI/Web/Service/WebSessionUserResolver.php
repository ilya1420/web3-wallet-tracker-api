<?php

declare(strict_types=1);

namespace App\UI\Web\Service;

use Symfony\Component\HttpFoundation\RequestStack;

final readonly class WebSessionUserResolver
{
    public const SESSION_ACCESS_TOKEN = 'app.web.access_token';
    public const SESSION_PENDING_EMAIL = 'app.web.pending_email';

    public function __construct(
        private RequestStack $requestStack,
    ) {
    }

    public function login(string $rawToken): void
    {
        $this->requestStack->getSession()->set(self::SESSION_ACCESS_TOKEN, $rawToken);
        $this->requestStack->getSession()->remove(self::SESSION_PENDING_EMAIL);
    }

    public function logout(): void
    {
        $this->requestStack->getSession()->remove(self::SESSION_ACCESS_TOKEN);
        $this->requestStack->getSession()->remove(self::SESSION_PENDING_EMAIL);
    }

    public function rememberPendingEmail(string $email): void
    {
        $this->requestStack->getSession()->set(self::SESSION_PENDING_EMAIL, mb_strtolower(trim($email)));
    }

    public function pendingEmail(): ?string
    {
        $email = (string) $this->requestStack->getSession()->get(self::SESSION_PENDING_EMAIL, '');

        return $email !== '' ? $email : null;
    }
}
