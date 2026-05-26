<?php

declare(strict_types=1);

namespace App\UI\Web\Controller;

use App\Application\Exception\InvalidCredentialsException;
use App\Application\Exception\RateLimitExceededException;
use App\Application\UseCase\ConfirmLoginTokenUseCase;
use App\Application\UseCase\RegisterUserUseCase;
use App\Application\UseCase\SignInWithPasswordUseCase;
use App\UI\Web\Dto\ConfirmTokenWebInput;
use App\UI\Web\Dto\LoginPasswordWebInput;
use App\UI\Web\Dto\RegisterWebInput;
use App\UI\Web\Form\ConfirmTokenWebFormType;
use App\UI\Web\Form\LoginPasswordWebFormType;
use App\UI\Web\Form\RegisterWebFormType;
use App\UI\Web\Service\WebSessionUserResolver;
use Symfony\Component\Form\FormInterface;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

final class AuthController extends AbstractWebController
{
    public function __construct(
        private readonly RegisterUserUseCase $registerUserUseCase,
        private readonly SignInWithPasswordUseCase $signInWithPasswordUseCase,
        private readonly ConfirmLoginTokenUseCase $confirmLoginTokenUseCase,
        private readonly WebSessionUserResolver $webSessionUserResolver,
    ) {
    }

    #[Route(path: '/app/auth', name: 'app_web_login', methods: ['GET'])]
    public function auth(): Response
    {
        if ($this->isGranted('ROLE_USER')) {
            return $this->redirectToRoute('app_dashboard');
        }

        return $this->renderAuth($this->createLoginPasswordForm());
    }

    #[Route(path: '/app/auth/login', name: 'app_web_login_submit', methods: ['POST'])]
    public function login(Request $request): Response
    {
        $input = new LoginPasswordWebInput();
        $form = $this->createForm(LoginPasswordWebFormType::class, $input);
        $form->handleRequest($request);

        if (!$form->isSubmitted() || !$form->isValid()) {
            return $this->renderAuth($form, Response::HTTP_UNPROCESSABLE_ENTITY);
        }

        try {
            $auth = $this->signInWithPasswordUseCase->execute($input->email, $input->password);
            $this->webSessionUserResolver->login($auth->accessToken);

            return $this->redirectToRoute('app_dashboard');
        } catch (InvalidCredentialsException|RateLimitExceededException $e) {
            $this->addFlash('error', $e->getMessage());

            return $this->renderAuth($form, Response::HTTP_UNPROCESSABLE_ENTITY);
        } catch (\Throwable) {
            $this->addFlash('error', 'Sign in failed. Please try again.');

            return $this->renderAuth($form, Response::HTTP_UNPROCESSABLE_ENTITY);
        }
    }

    #[Route(path: '/app/auth/register', name: 'app_web_register_form', methods: ['GET'])]
    public function registerForm(): Response
    {
        if ($this->isGranted('ROLE_USER')) {
            return $this->redirectToRoute('app_dashboard');
        }

        return $this->renderRegister($this->createRegisterForm());
    }

    #[Route(path: '/app/auth/register', name: 'app_web_register', methods: ['POST'])]
    public function register(Request $request): Response
    {
        $input = new RegisterWebInput();
        $form = $this->createForm(RegisterWebFormType::class, $input);
        $this->backfillDeviceFingerprint($request, $form->getName());
        $form->handleRequest($request);

        if (!$form->isSubmitted() || !$form->isValid()) {
            return $this->renderRegister($form, Response::HTTP_UNPROCESSABLE_ENTITY);
        }

        try {
            $this->registerUserUseCase->execute(
                $input->email,
                $input->password,
                $input->deviceFingerprint,
                $request->getClientIp(),
            );
            $this->webSessionUserResolver->rememberPendingEmail($input->email);
            $this->addFlash('success', 'Account created. Enter the token from your email.');

            return $this->redirectToRoute('app_web_confirm');
        } catch (\Throwable $e) {
            $this->addFlash('error', $e->getMessage());

            return $this->renderRegister($form, Response::HTTP_UNPROCESSABLE_ENTITY);
        }
    }

    #[Route(path: '/app/auth/confirm', name: 'app_web_confirm', methods: ['GET'])]
    public function confirm(): Response
    {
        if ($this->isGranted('ROLE_USER')) {
            return $this->redirectToRoute('app_dashboard');
        }

        $pendingEmail = $this->webSessionUserResolver->pendingEmail();
        if ($pendingEmail === null) {
            $this->addFlash('error', 'Request a login token first.');

            return $this->redirectToRoute('app_web_login');
        }

        return $this->renderConfirm($pendingEmail, $this->createConfirmTokenForm());
    }

    #[Route(path: '/app/auth/confirm', name: 'app_web_confirm_submit', methods: ['POST'])]
    public function confirmSubmit(Request $request): Response
    {
        $pendingEmail = $this->webSessionUserResolver->pendingEmail();
        if ($pendingEmail === null) {
            $this->addFlash('error', 'Request a login token first.');

            return $this->redirectToRoute('app_web_login');
        }

        $input = new ConfirmTokenWebInput();
        $form = $this->createForm(ConfirmTokenWebFormType::class, $input);
        $form->handleRequest($request);

        if (!$form->isSubmitted() || !$form->isValid()) {
            return $this->renderConfirm($pendingEmail, $form, Response::HTTP_UNPROCESSABLE_ENTITY);
        }

        try {
            $auth = $this->confirmLoginTokenUseCase->execute($pendingEmail, $input->token);
            $this->webSessionUserResolver->login($auth->accessToken);

            return $this->redirectToRoute('app_dashboard');
        } catch (\Throwable $e) {
            $this->addFlash('error', $e->getMessage());

            return $this->renderConfirm($pendingEmail, $form, Response::HTTP_UNPROCESSABLE_ENTITY);
        }
    }

    #[Route(path: '/app/logout', name: 'app_web_logout', methods: ['POST'])]
    public function logout(Request $request): RedirectResponse
    {
        $this->denyAccessUnlessGranted('ROLE_USER');
        if (!$this->isCsrfTokenValid('web_logout', (string) $request->request->get('_token'))) {
            $this->addFlash('error', 'Invalid security token.');

            return $this->redirectToRoute('app_dashboard');
        }

        $this->webSessionUserResolver->logout();
        $this->addFlash('success', 'Signed out.');

        return $this->redirectToRoute('app_web_login');
    }

    /**
     * @param FormInterface<mixed> $loginForm
     */
    private function renderAuth(FormInterface $loginForm, int $status = Response::HTTP_OK): Response
    {
        return $this->renderWithStatus('web_app/auth.html.twig', [
            'loginForm' => $loginForm,
        ], $status);
    }

    /**
     * @param FormInterface<mixed> $registerForm
     */
    private function renderRegister(FormInterface $registerForm, int $status = Response::HTTP_OK): Response
    {
        return $this->renderWithStatus('web_app/register.html.twig', [
            'registerForm' => $registerForm,
        ], $status);
    }

    /**
     * @param FormInterface<mixed> $confirmForm
     */
    private function renderConfirm(string $pendingEmail, FormInterface $confirmForm, int $status = Response::HTTP_OK): Response
    {
        return $this->renderWithStatus('web_app/confirm.html.twig', [
            'pendingEmail' => $pendingEmail,
            'confirmForm' => $confirmForm,
        ], $status);
    }

    /**
     * @return FormInterface<mixed>
     */
    private function createRegisterForm(): FormInterface
    {
        return $this->createForm(RegisterWebFormType::class, new RegisterWebInput());
    }

    /**
     * @return FormInterface<mixed>
     */
    private function createLoginPasswordForm(): FormInterface
    {
        return $this->createForm(LoginPasswordWebFormType::class, new LoginPasswordWebInput());
    }

    /**
     * @return FormInterface<mixed>
     */
    private function createConfirmTokenForm(): FormInterface
    {
        return $this->createForm(ConfirmTokenWebFormType::class, new ConfirmTokenWebInput());
    }

    private function backfillDeviceFingerprint(Request $request, string $formName): void
    {
        if (!$request->isMethod('POST')) {
            return;
        }

        $payload = $request->request->all($formName);
        if ($payload === []) {
            return;
        }

        if (trim((string) ($payload['deviceFingerprint'] ?? '')) !== '') {
            return;
        }

        $payload['deviceFingerprint'] = $this->buildServerDeviceFingerprint($request);
        $request->request->set($formName, $payload);
    }

    private function buildServerDeviceFingerprint(Request $request): string
    {
        $sessionId = $request->hasSession() ? $request->getSession()->getId() : '';
        $material = implode('|', [
            $sessionId,
            (string) $request->headers->get('user-agent', ''),
            (string) $request->headers->get('accept-language', ''),
        ]);

        return 'websrv-' . hash('sha256', $material);
    }
}
