<?php

declare(strict_types=1);

use Symfony\Component\Dotenv\Dotenv;

require dirname(__DIR__).'/vendor/autoload.php';

if (class_exists(Dotenv::class)) {
    (new Dotenv())->usePutenv()->bootEnv(dirname(__DIR__).'/.env');
}

foreach (['APP_ENV', 'APP_DEBUG', 'DATABASE_URL', 'MESSENGER_TRANSPORT_DSN', 'MAILER_DSN'] as $name) {
    if (!isset($_SERVER[$name])) {
        continue;
    }

    $_ENV[$name] = $_SERVER[$name];
    putenv(sprintf('%s=%s', $name, (string) $_SERVER[$name]));
}
