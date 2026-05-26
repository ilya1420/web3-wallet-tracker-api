<?php

declare(strict_types=1);

namespace App\Infrastructure\Security;

use App\UI\Web\Service\WebSessionUserResolver;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;
use Symfony\Component\Security\Core\Authentication\Token\TokenInterface;
use Symfony\Component\Security\Core\Exception\AuthenticationException;
use Symfony\Component\Security\Core\Exception\CustomUserMessageAuthenticationException;
use Symfony\Component\Security\Http\Authenticator\AbstractAuthenticator;
use Symfony\Component\Security\Http\Authenticator\Passport\Badge\UserBadge;
use Symfony\Component\Security\Http\Authenticator\Passport\SelfValidatingPassport;
use Symfony\Component\Security\Http\EntryPoint\AuthenticationEntryPointInterface;

final class WebSessionAccessTokenAuthenticator extends AbstractAuthenticator implements AuthenticationEntryPointInterface
{
    public function __construct(
        private AccessTokenUserResolver $accessTokenUserResolver,
        private UrlGeneratorInterface $urlGenerator,
    ) {
    }

    public function supports(Request $request): bool
    {
        return $request->hasSession()
            && $request->getSession()->has(WebSessionUserResolver::SESSION_ACCESS_TOKEN);
    }

    public function authenticate(Request $request): SelfValidatingPassport
    {
        $rawToken = (string) $request->getSession()->get(WebSessionUserResolver::SESSION_ACCESS_TOKEN, '');
        $user = $this->accessTokenUserResolver->resolve($rawToken);

        if ($user === null) {
            $request->getSession()->remove(WebSessionUserResolver::SESSION_ACCESS_TOKEN);

            throw new CustomUserMessageAuthenticationException('Session expired.');
        }

        return new SelfValidatingPassport(new UserBadge($user->getUserIdentifier()));
    }

    public function onAuthenticationSuccess(Request $request, TokenInterface $token, string $firewallName): ?Response
    {
        return null;
    }

    public function onAuthenticationFailure(Request $request, AuthenticationException $exception): Response
    {
        return $this->start($request, $exception);
    }

    public function start(Request $request, ?AuthenticationException $authException = null): Response
    {
        return new RedirectResponse($this->urlGenerator->generate('app_web_login'));
    }
}
