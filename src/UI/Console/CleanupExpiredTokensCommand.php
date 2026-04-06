<?php

declare(strict_types=1);

namespace App\UI\Console;

use App\Application\Service\AuthTokenCleanupService;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

#[AsCommand(
    name: 'app:auth:cleanup-expired-tokens',
    description: 'Deletes expired access tokens and obsolete login tokens.',
)]
final class CleanupExpiredTokensCommand extends Command
{
    public function __construct(private readonly AuthTokenCleanupService $cleanupService)
    {
        parent::__construct();
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);
        $now = new \DateTimeImmutable();
        $result = $this->cleanupService->cleanup($now);

        $io->success(sprintf(
            'Deleted %d token records (%d login tokens, %d access tokens) at %s.',
            $result['total'],
            $result['loginTokens'],
            $result['accessTokens'],
            $now->format(DATE_ATOM),
        ));

        return Command::SUCCESS;
    }
}
