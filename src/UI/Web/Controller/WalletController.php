<?php

declare(strict_types=1);

namespace App\UI\Web\Controller;

use App\Application\DTO\Web3WalletOutput;
use App\Application\UseCase\CreateWeb3WalletUseCase;
use App\Application\UseCase\DeleteWeb3WalletUseCase;
use App\Application\UseCase\GetWeb3WalletBalanceUseCase;
use App\Application\UseCase\GetWeb3WalletUseCase;
use App\Application\UseCase\UpdateWeb3WalletUseCase;
use App\Domain\Entity\User;
use App\Domain\Enum\EvmRpcPreset;
use App\UI\Web\Dto\Web3WalletActionWebInput;
use App\UI\Web\Dto\Web3WalletCreateWebInput;
use App\UI\Web\Dto\Web3WalletUpdateWebInput;
use App\UI\Web\Form\Web3WalletActionWebFormType;
use App\UI\Web\Form\Web3WalletCreateWebFormType;
use App\UI\Web\Form\Web3WalletUpdateWebFormType;
use Symfony\Component\Form\FormInterface;
use Symfony\Component\Form\FormError;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\Session\FlashBagAwareSessionInterface;
use Symfony\Component\Routing\Attribute\Route;

final class WalletController extends AbstractWebController
{
    private const CREATE_FORM_OLD_INPUT_FLASH_KEY = 'wallet_create_form.old_input';
    private const CREATE_FORM_ERRORS_FLASH_KEY = 'wallet_create_form.errors';

    public function __construct(
        private readonly GetWeb3WalletUseCase $getWeb3WalletUseCase,
        private readonly CreateWeb3WalletUseCase $createWeb3WalletUseCase,
        private readonly UpdateWeb3WalletUseCase $updateWeb3WalletUseCase,
        private readonly DeleteWeb3WalletUseCase $deleteWeb3WalletUseCase,
        private readonly GetWeb3WalletBalanceUseCase $getWeb3WalletBalanceUseCase,
        private readonly RequestStack $requestStack,
    ) {
    }

    #[Route(path: '/app/wallets/new', name: 'app_wallet_new', methods: ['GET'])]
    public function newWallet(): Response
    {
        $this->denyAccessUnlessGranted('ROLE_USER');

        $form = $this->createWalletForm();
        $this->restoreCreateWalletFormState($form);

        return $this->renderNewWallet($form);
    }

    #[Route(path: '/app/wallets', name: 'app_wallet_create', methods: ['POST'])]
    public function createWallet(Request $request): Response
    {
        $user = $this->currentWebUser();
        $input = new Web3WalletCreateWebInput();
        $form = $this->createForm(Web3WalletCreateWebFormType::class, $input);
        $form->handleRequest($request);

        if (!$form->isSubmitted() || !$form->isValid()) {
            $this->rememberCreateWalletFormState($form);

            return $this->redirectToRoute('app_wallet_new');
        }

        try {
            $wallet = $this->createWeb3WalletUseCase->execute(
                $user,
                $input->address,
                $this->resolveCustomRpcEndpoint($input),
                $input->rpcPreset,
            );
            $this->addFlash('success', 'Wallet connected.');

            return $this->redirectToRoute('app_wallet_show', ['id' => $wallet->id]);
        } catch (\Throwable $e) {
            $this->rememberCreateWalletFormState($form);
            $this->addFlash('error', $e->getMessage());

            return $this->redirectToRoute('app_wallet_new');
        }
    }

    #[Route(path: '/app/wallets/{id}', name: 'app_wallet_show', methods: ['GET'])]
    public function showWallet(string $id): Response
    {
        return $this->renderWallet($this->currentWebUser(), $id);
    }

    #[Route(path: '/app/wallets/{id}/sync', name: 'app_wallet_sync', methods: ['POST'])]
    public function syncWallet(Request $request, string $id): Response
    {
        $user = $this->currentWebUser();
        $form = $this->createActionForm();
        $form->handleRequest($request);

        /** @var Web3WalletActionWebInput $input */
        $input = $form->getData();
        if (!$form->isSubmitted() || !$form->isValid() || $input->id !== $id) {
            $this->addFormErrors($form);

            return $this->renderWallet($user, $id, null, Response::HTTP_UNPROCESSABLE_ENTITY);
        }

        try {
            $this->getWeb3WalletBalanceUseCase->execute($user, $id);
            $this->addFlash('success', 'Balance refreshed.');
        } catch (\Throwable $e) {
            $this->addFlash('error', $e->getMessage());

            return $this->renderWallet($user, $id, null, Response::HTTP_UNPROCESSABLE_ENTITY);
        }

        return $this->redirectToRoute('app_wallet_show', ['id' => $id]);
    }

    #[Route(path: '/app/wallets/{id}/update', name: 'app_wallet_update', methods: ['POST'])]
    public function updateWallet(Request $request, string $id): Response
    {
        $user = $this->currentWebUser();
        $input = new Web3WalletUpdateWebInput();
        $form = $this->createForm(Web3WalletUpdateWebFormType::class, $input);
        $form->handleRequest($request);

        if (!$form->isSubmitted() || !$form->isValid() || $input->id !== $id) {
            $this->addFlash('error', 'Invalid wallet update request.');

            return $this->renderWallet($user, $id, $form, Response::HTTP_UNPROCESSABLE_ENTITY);
        }

        try {
            $this->updateWeb3WalletUseCase->execute($user, $id, $input->rpcEndpoint);
            $this->addFlash('success', 'RPC endpoint updated.');
        } catch (\Throwable $e) {
            $this->addFlash('error', $e->getMessage());

            return $this->renderWallet($user, $id, $form, Response::HTTP_UNPROCESSABLE_ENTITY);
        }

        return $this->redirectToRoute('app_wallet_show', ['id' => $id]);
    }

    #[Route(path: '/app/wallets/{id}/delete', name: 'app_wallet_delete', methods: ['POST'])]
    public function deleteWallet(Request $request, string $id): Response
    {
        $user = $this->currentWebUser();
        $form = $this->createActionForm();
        $form->handleRequest($request);

        /** @var Web3WalletActionWebInput $input */
        $input = $form->getData();
        if (!$form->isSubmitted() || !$form->isValid() || $input->id !== $id) {
            $this->addFormErrors($form);

            return $this->renderWallet($user, $id, null, Response::HTTP_UNPROCESSABLE_ENTITY);
        }

        try {
            $this->deleteWeb3WalletUseCase->execute($user, $id);
            $this->addFlash('success', 'Wallet deleted.');
        } catch (\Throwable $e) {
            $this->addFlash('error', $e->getMessage());

            return $this->renderWallet($user, $id, null, Response::HTTP_UNPROCESSABLE_ENTITY);
        }

        return $this->redirectToRoute('app_dashboard');
    }

    /**
     * @param FormInterface<mixed> $walletCreateForm
     */
    private function renderNewWallet(FormInterface $walletCreateForm, int $status = Response::HTTP_OK): Response
    {
        return $this->renderWithStatus('web_app/wallet_new.html.twig', [
            'walletCreateForm' => $walletCreateForm,
            'presets' => EvmRpcPreset::cases(),
        ], $status);
    }

    /**
     * @param FormInterface<mixed>|null $walletUpdateForm
     */
    private function renderWallet(
        User $user,
        string $id,
        ?FormInterface $walletUpdateForm = null,
        int $status = Response::HTTP_OK,
    ): Response {
        $wallet = $this->getWeb3WalletUseCase->execute($user, $id);

        return $this->renderWithStatus('web_app/wallet_show.html.twig', [
            'wallet' => $wallet,
            'walletUpdateForm' => $walletUpdateForm ?? $this->createUpdateForm($wallet),
            'balanceRatio' => $this->balanceRatio($wallet),
        ], $status);
    }

    /**
     * @return FormInterface<mixed>
     */
    private function createWalletForm(): FormInterface
    {
        return $this->createForm(Web3WalletCreateWebFormType::class, new Web3WalletCreateWebInput());
    }

    /**
     * @return FormInterface<mixed>
     */
    private function createActionForm(): FormInterface
    {
        return $this->createForm(Web3WalletActionWebFormType::class, new Web3WalletActionWebInput());
    }

    /**
     * @return FormInterface<mixed>
     */
    private function createUpdateForm(Web3WalletOutput $wallet): FormInterface
    {
        $input = new Web3WalletUpdateWebInput();
        $input->id = $wallet->id;
        $input->rpcEndpoint = $wallet->rpcEndpoint;

        return $this->createForm(Web3WalletUpdateWebFormType::class, $input);
    }

    private function resolveCustomRpcEndpoint(Web3WalletCreateWebInput $input): ?string
    {
        $endpoint = trim((string) $input->rpcEndpoint);
        $preset = EvmRpcPreset::from($input->rpcPreset);

        return $endpoint !== '' && $endpoint !== $preset->endpoint() ? $endpoint : null;
    }

    private function balanceRatio(Web3WalletOutput $wallet): int
    {
        if ($wallet->lastKnownBalanceWei === null || $wallet->lastKnownBalanceWei === '0') {
            return 0;
        }

        return min(100, max(8, strlen($wallet->lastKnownBalanceWei) * 5));
    }

    /**
     * @param FormInterface<Web3WalletCreateWebInput> $form
     */
    private function rememberCreateWalletFormState(FormInterface $form): void
    {
        $session = $this->getFlashBagAwareSession();
        if ($session === null) {
            return;
        }

        /** @var Web3WalletCreateWebInput $data */
        $data = $form->getData();
        $session->getFlashBag()->set(self::CREATE_FORM_OLD_INPUT_FLASH_KEY, [[
            'address' => $data->address,
            'rpcPreset' => $data->rpcPreset,
            'rpcEndpoint' => $data->rpcEndpoint,
        ]]);

        $errors = [];
        foreach ($form->getErrors(true) as $error) {
            $origin = $error->getOrigin();
            $path = $origin?->getName() ?? '';
            $errors[$path][] = $error->getMessage();
        }

        if ($errors !== []) {
            $session->getFlashBag()->set(self::CREATE_FORM_ERRORS_FLASH_KEY, [$errors]);
        }
    }

    /**
     * @param FormInterface<Web3WalletCreateWebInput> $form
     */
    private function restoreCreateWalletFormState(FormInterface $form): void
    {
        $session = $this->getFlashBagAwareSession();
        if ($session === null) {
            return;
        }

        $oldInput = $session->getFlashBag()->get(self::CREATE_FORM_OLD_INPUT_FLASH_KEY);
        if ($oldInput !== [] && is_array($oldInput[0] ?? null)) {
            /** @var Web3WalletCreateWebInput $data */
            $data = $form->getData();
            $payload = $oldInput[0];
            $data->address = (string) ($payload['address'] ?? '');
            $data->rpcPreset = (string) ($payload['rpcPreset'] ?? EvmRpcPreset::ETHEREUM->value);
            $rpcEndpoint = $payload['rpcEndpoint'] ?? null;
            $data->rpcEndpoint = is_string($rpcEndpoint) ? $rpcEndpoint : null;
        }

        $flashErrors = $session->getFlashBag()->get(self::CREATE_FORM_ERRORS_FLASH_KEY);
        if ($flashErrors === [] || !is_array($flashErrors[0] ?? null)) {
            return;
        }

        /** @var array<string, list<string>> $errors */
        $errors = $flashErrors[0];
        foreach ($errors as $field => $messages) {
            $target = $field !== '' && $form->has($field) ? $form->get($field) : $form;
            foreach ($messages as $message) {
                $target->addError(new FormError($message));
            }
        }
    }

    private function getFlashBagAwareSession(): ?FlashBagAwareSessionInterface
    {
        $request = $this->requestStack->getCurrentRequest();
        if ($request === null || !$request->hasSession()) {
            return null;
        }

        $session = $request->getSession();

        return $session instanceof FlashBagAwareSessionInterface ? $session : null;
    }
}
