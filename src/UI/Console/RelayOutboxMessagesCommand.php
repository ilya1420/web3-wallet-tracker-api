<?php

declare(strict_types=1);

namespace App\UI\Console;

use App\Infrastructure\Messaging\Transport\JsonMessengerSerializer;
use App\Infrastructure\Persistence\Doctrine\Repository\OutboxMessageRepository;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;
use Symfony\Component\Messenger\MessageBusInterface;
use Symfony\Component\Uid\Uuid;

#[AsCommand(
    name: 'app:outbox:relay',
    description: 'Relays pending outbox messages to the configured message bus.',
)]
final class RelayOutboxMessagesCommand extends Command
{
    public function __construct(
        private readonly OutboxMessageRepository $outboxMessageRepository,
        private readonly JsonMessengerSerializer $serializer,
        private readonly MessageBusInterface $messageBus,
    ) {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this
            ->addOption('batch-size', null, InputOption::VALUE_REQUIRED, 'How many messages to relay per iteration.', '100')
            ->addOption('sleep', null, InputOption::VALUE_REQUIRED, 'Sleep time in seconds when no messages are available.', '1')
            ->addOption('time-limit', null, InputOption::VALUE_REQUIRED, 'Maximum runtime in seconds.', '3600')
            ->addOption('lock-timeout', null, InputOption::VALUE_REQUIRED, 'Seconds after which an abandoned lock is considered stale.', '60');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);
        $batchSize = max(1, (int) $input->getOption('batch-size'));
        $sleepSeconds = max(0, (int) $input->getOption('sleep'));
        $timeLimitSeconds = max(1, (int) $input->getOption('time-limit'));
        $lockTimeoutSeconds = max(1, (int) $input->getOption('lock-timeout'));
        $startedAt = time();
        $relayedMessages = 0;

        while ((time() - $startedAt) < $timeLimitSeconds) {
            $now = new \DateTimeImmutable();
            $lockId = Uuid::v7()->toRfc4122();
            $staleBefore = $now->sub(new \DateInterval(sprintf('PT%dS', $lockTimeoutSeconds)));
            $messages = $this->outboxMessageRepository->claimPendingBatch($lockId, $now, $batchSize, $staleBefore);

            if ($messages === []) {
                sleep($sleepSeconds);

                continue;
            }

            foreach ($messages as $message) {
                try {
                    $envelope = $this->serializer->decode(['body' => $message['body']]);
                    $this->messageBus->dispatch($envelope->getMessage());
                    $this->outboxMessageRepository->markProcessed($message['id'], new \DateTimeImmutable());
                    ++$relayedMessages;
                } catch (\Throwable $exception) {
                    $this->outboxMessageRepository->release($message['id']);
                    $io->error(sprintf('Failed to relay outbox message %s: %s', $message['id'], $exception->getMessage()));

                    return Command::FAILURE;
                }
            }
        }

        $io->success(sprintf('Relayed %d outbox messages.', $relayedMessages));

        return Command::SUCCESS;
    }
}
