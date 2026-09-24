<?php

declare(strict_types=1);

namespace App\UI\Controller;

use App\Application\DTO\ApiDataResponse;
use App\Application\Exception\SocialAuthException;
use App\Application\Service\SocialAuthService;
use App\Application\UseCase\LinkSocialProfileUseCase;
use App\Application\UseCase\SignInWithSocialProfileUseCase;
use App\Domain\Entity\User;
use App\UI\Web\Service\WebSessionUserResolver;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

final class SocialAuthController extends AbstractController
{
    private const SESSION_OAUTH_STATE = 'app.social_auth.oauth_state';

    public function __construct(
        private readonly SocialAuthService $socialAuthService,
        private readonly SignInWithSocialProfileUseCase $signInWithSocialProfileUseCase,
        private readonly LinkSocialProfileUseCase $linkSocialProfileUseCase,
        private readonly WebSessionUserResolver $webSessionUserResolver,
    ) {
    }

    #[Route(path: '/app/auth/social/{provider}/redirect', name: 'app_social_auth_redirect', methods: ['GET'])]
    public function webRedirect(string $provider, Request $request): RedirectResponse
    {
        $state = bin2hex(random_bytes(32));
        $request->getSession()->set(self::SESSION_OAUTH_STATE, $state);

        return new RedirectResponse($this->socialAuthService->redirectUrl($provider, $state));
    }

    #[Route(path: '/app/auth/social/{provider}/callback', name: 'app_social_auth_callback', methods: ['GET'])]
    public function webCallback(string $provider, Request $request): RedirectResponse
    {
        $expectedState = (string) $request->getSession()->get(self::SESSION_OAUTH_STATE, '');
        $request->getSession()->remove(self::SESSION_OAUTH_STATE);

        if ($expectedState === '' || !hash_equals($expectedState, (string) $request->query->get('state', ''))) {
            $this->addFlash('error', 'Social sign in failed. Please try again.');

            return $this->redirectToRoute('app_web_login');
        }

        try {
            $profile = $this->socialAuthService->authenticate($provider, [
                'code' => (string) $request->query->get('code', ''),
            ]);
            $auth = $this->signInWithSocialProfileUseCase->execute($profile);
            $this->webSessionUserResolver->login($auth->accessToken);

            return $this->redirectToRoute('app_dashboard');
        } catch (\Throwable) {
            $this->addFlash('error', 'Social sign in failed. Please try again.');

            return $this->redirectToRoute('app_web_login');
        }
    }

    #[Route(path: '/api/auth/social/{provider}', name: 'api_social_auth', methods: ['POST'])]
    public function apiAuthenticate(string $provider, Request $request): JsonResponse
    {
        try {
            $payload = json_decode($request->getContent(), true, 512, JSON_THROW_ON_ERROR);
        } catch (\JsonException $e) {
            throw new SocialAuthException('Invalid social auth request payload.', previous: $e);
        }

        if (!is_array($payload)) {
            throw new SocialAuthException('Invalid social auth request payload.');
        }

        /** @var array<string, mixed> $payload */
        $profile = $this->socialAuthService->authenticate($provider, $payload);
        $auth = $this->signInWithSocialProfileUseCase->execute($profile);

        return $this->json(new ApiDataResponse($auth), Response::HTTP_OK);
    }

    #[Route(path: '/api/auth/social/{provider}/link', name: 'api_social_auth_link', methods: ['POST'])]
    public function apiLink(string $provider, Request $request): JsonResponse
    {
        $user = $this->getUser();
        if (!$user instanceof User) {
            throw $this->createAccessDeniedException('Authentication required.');
        }

        try {
            $payload = json_decode($request->getContent(), true, 512, JSON_THROW_ON_ERROR);
        } catch (\JsonException $e) {
            throw new SocialAuthException('Invalid social auth request payload.', previous: $e);
        }

        if (!is_array($payload)) {
            throw new SocialAuthException('Invalid social auth request payload.');
        }

        /** @var array<string, mixed> $payload */
        $profile = $this->socialAuthService->authenticate($provider, $payload);
        $this->linkSocialProfileUseCase->execute($user, $profile);

        return $this->json(new ApiDataResponse(['status' => 'linked']), Response::HTTP_OK);
    }
}
