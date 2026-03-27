<?php

declare(strict_types=1);

namespace App\Infrastructure\Messaging\Transport;

use App\Infrastructure\Messaging\Message\SendLoginLinkEmailMessage;
use Symfony\Component\Messenger\Envelope;
use Symfony\Component\Messenger\Exception\MessageDecodingFailedException;
use Symfony\Component\Messenger\Exception\MessageEncodingFailedException;
use Symfony\Component\Messenger\Transport\Serialization\SerializerInterface;
use Symfony\Component\Serializer\SerializerInterface as SymfonySerializerInterface;

final readonly class JsonMessengerSerializer implements SerializerInterface
{
    /**
     * @var array<string, class-string>
     */
    private array $classByType;

    /**
     * @var array<class-string, string>
     */
    private array $typeByClass;

    public function __construct(private SymfonySerializerInterface $serializer)
    {
        $this->classByType = [
            'send_login_link_email' => SendLoginLinkEmailMessage::class,
        ];
        $this->typeByClass = array_flip($this->classByType);
    }

    public function decode(array $encodedEnvelope): Envelope
    {
        $body = (string) ($encodedEnvelope['body'] ?? '');
        if ($body === '') {
            throw new MessageDecodingFailedException('Message body is empty.');
        }

        try {
            /** @var array{type?:string,payload?:array<string,mixed>} $decoded */
            $decoded = json_decode($body, true, 512, JSON_THROW_ON_ERROR);
        } catch (\JsonException $e) {
            throw new MessageDecodingFailedException('Invalid JSON message body.', 0, $e);
        }

        $type = (string) ($decoded['type'] ?? '');
        if ($type === '') {
            throw new MessageDecodingFailedException('Message type is missing.');
        }

        $messageClass = $this->classByType[$type] ?? null;
        if ($messageClass === null) {
            throw new MessageDecodingFailedException(sprintf('Unsupported message type "%s".', $type));
        }

        $payload = $decoded['payload'] ?? null;
        if (!\is_array($payload)) {
            throw new MessageDecodingFailedException('Message payload must be an object.');
        }

        try {
            $message = $this->serializer->deserialize(
                json_encode($payload, JSON_THROW_ON_ERROR),
                $messageClass,
                'json',
            );
        } catch (\Throwable $e) {
            throw new MessageDecodingFailedException('Unable to deserialize message payload.', 0, $e);
        }

        return new Envelope($message);
    }

    public function encode(Envelope $envelope): array
    {
        $message = $envelope->getMessage();
        $messageClass = $message::class;
        $type = $this->typeByClass[$messageClass] ?? null;

        if ($type === null) {
            throw new MessageEncodingFailedException(sprintf('Unsupported message class "%s".', $messageClass));
        }

        try {
            /** @var array<string,mixed> $payload */
            $payload = json_decode($this->serializer->serialize($message, 'json'), true, 512, JSON_THROW_ON_ERROR);
            $body = json_encode([
                'type' => $type,
                'payload' => $payload,
            ], JSON_THROW_ON_ERROR);
        } catch (\Throwable $e) {
            throw new MessageEncodingFailedException('Unable to encode message as JSON.', 0, $e);
        }

        return [
            'body' => $body,
            'headers' => [
                'content_type' => 'application/json',
            ],
        ];
    }
}
