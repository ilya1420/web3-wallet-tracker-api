<?php

declare(strict_types=1);

use Symfony\Component\Dotenv\Dotenv;

$projectDir = dirname(__DIR__, 2);

require_once $projectDir.'/vendor/autoload.php';

if (is_file($projectDir.'/.env')) {
    (new Dotenv())->bootEnv($projectDir.'/.env', overrideExistingVars: false);
}

$_SERVER['APP_ENV'] ??= $_ENV['APP_ENV'] ?? 'dev';
$_SERVER['APP_DEBUG'] ??= $_ENV['APP_DEBUG'] ?? '1';
