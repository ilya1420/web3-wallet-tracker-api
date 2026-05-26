<?php

declare(strict_types=1);

namespace App\UI\Web\Controller;

use App\Domain\Entity\User;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\Form\FormInterface;
use Symfony\Component\HttpFoundation\Response;

abstract class AbstractWebController extends AbstractController
{
    protected function currentWebUser(): User
    {
        $this->denyAccessUnlessGranted('ROLE_USER');
        $user = $this->getUser();

        if (!$user instanceof User) {
            throw $this->createAccessDeniedException('Authentication required.');
        }

        return $user;
    }

    /**
     * @param array<string, mixed> $parameters
     */
    protected function renderWithStatus(string $view, array $parameters, int $status = Response::HTTP_OK): Response
    {
        return $this->render($view, $parameters, new Response(status: $status));
    }

    /**
     * @param FormInterface<mixed> $form
     */
    protected function addFormErrors(FormInterface $form): void
    {
        foreach ($form->getErrors(true) as $error) {
            $this->addFlash('error', $error->getMessage());
        }
    }
}
