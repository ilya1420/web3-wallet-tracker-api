<?php

declare(strict_types=1);

use App\Kernel;
use Doctrine\ORM\EntityManagerInterface;

require_once __DIR__.'/bootstrap.php';

$kernel = new Kernel($_SERVER['APP_ENV'], (bool) $_SERVER['APP_DEBUG']);
$kernel->boot();

$container = $kernel->getContainer();
$entityManager = $container->get('doctrine')->getManager();

if (!$entityManager instanceof EntityManagerInterface) {
    throw new RuntimeException('Doctrine entity manager is not available for PHPStan.');
}

return $entityManager;
