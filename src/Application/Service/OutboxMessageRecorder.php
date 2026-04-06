<?php

declare(strict_types=1);

namespace App\Application\Service;

use App\Infrastructure\Messaging\Transport\JsonMessengerSerializer;
use App\Infrastructure\Persistence\Doctrine\Repository\OutboxMessageRepository;
use Symfony\Component\Messenger\Envelope;

final readonly class OutboxMessageRecorder
{
    public function __construct(
        private JsonMessengerSerializer $serializer,
        private OutboxMessageRepository $outboxMessageRepository,
    ) {
    }

    public function record(object $message, ?\DateTimeImmutable $availableAt = null): void
    {
        $encodedMessage = $this->serializer->encode(new Envelope($message));
        $body = (string) ($encodedMessage['body'] ?? '');

        $this->outboxMessageRepository->add(
            $body,
            $availableAt ?? new \DateTimeImmutable(),
        );
    }
}
