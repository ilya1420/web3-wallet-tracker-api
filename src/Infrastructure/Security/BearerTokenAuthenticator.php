<?php

declare(strict_types=1);

namespace App\Infrastructure\Security;

use App\Domain\Repository\AccessTokenRepositoryInterface;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Security\Core\Authentication\Token\TokenInterface;
use Symfony\Component\Security\Core\Exception\AuthenticationException;
use Symfony\Component\Security\Http\Authenticator\AbstractAuthenticator;
use Symfony\Component\Security\Http\Authenticator\Passport\Badge\UserBadge;
use Symfony\Component\Security\Http\Authenticator\Passport\SelfValidatingPassport;

class BearerTokenAuthenticator extends AbstractAuthenticator
{
    public function __construct(private AccessTokenRepositoryInterface $accessTokenRepository)
    {
    }

    public function supports(Request $request): ?bool
    {
        return str_starts_with((string) $request->headers->get('Authorization'), 'Bearer ');
    }

    public function authenticate(Request $request): SelfValidatingPassport
    {
        $rawToken = trim((string) str_replace('Bearer ', '', (string) $request->headers->get('Authorization')));
        if ($rawToken === '') {
            throw new AuthenticationException('Missing bearer token.');
        }

        $hashedToken = hash('sha256', $rawToken);
        $accessToken = $this->accessTokenRepository->findValidByHash($hashedToken);

        if ($accessToken === null) {
            throw new AuthenticationException('Invalid or expired access token.');
        }

        return new SelfValidatingPassport(new UserBadge($accessToken->user()->getUserIdentifier(), static fn () => $accessToken->user()));
    }

    public function onAuthenticationSuccess(Request $request, TokenInterface $token, string $firewallName): ?Response
    {
        return null;
    }

    public function onAuthenticationFailure(Request $request, AuthenticationException $exception): ?Response
    {
        return new JsonResponse([
            'message' => $exception->getMessageKey(),
        ], Response::HTTP_UNAUTHORIZED);
    }
}
