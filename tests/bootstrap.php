<?php

declare(strict_types=1);

use Symfony\Component\Dotenv\Dotenv;
use Symfony\Component\Process\Process;

require dirname(__DIR__).'/vendor/autoload.php';

if (method_exists(Dotenv::class, 'bootEnv')) {
    (new Dotenv())->bootEnv(dirname(__DIR__).'/.env');
}

if ($_SERVER['APP_DEBUG']) {
    umask(0000);
}

// Réinitialisation de la base de données test avant chaque run PHPUnit
if ('test' === ($_SERVER['APP_ENV'] ?? 'dev')) {
    $consolePath = dirname(__DIR__).'/bin/console';

    $commands = [
        ['php', $consolePath, 'doctrine:database:create', '--if-not-exists', '--env=test', '--no-interaction'],
        ['php', $consolePath, 'doctrine:migrations:migrate', '--env=test', '--no-interaction', '--allow-no-migration'],
        ['php', $consolePath, 'doctrine:fixtures:load', '--env=test', '--no-interaction'],
    ];

    foreach ($commands as $cmd) {
        $process = new Process($cmd);
        $process->setTimeout(120);
        $process->run();

        if (!$process->isSuccessful()) {
            echo 'Bootstrap DB error: '.$process->getErrorOutput().PHP_EOL;
            exit(1);
        }
    }
}
