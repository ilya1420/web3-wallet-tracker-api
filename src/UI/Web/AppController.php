<?php

declare(strict_types=1);

namespace App\UI\Web;

use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

final class AppController extends AbstractController
{
    #[Route(path: '/', name: 'app_ui_root', methods: ['GET'])]
    #[Route(path: '/app', name: 'app_ui', methods: ['GET'])]
    public function index(): Response
    {
        return $this->render('app/dashboard.html.twig');
    }
}
