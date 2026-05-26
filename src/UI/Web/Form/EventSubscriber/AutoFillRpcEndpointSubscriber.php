<?php

declare(strict_types=1);

namespace App\UI\Web\Form\EventSubscriber;

use App\Domain\Enum\EvmRpcPreset;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\Form\FormEvent;
use Symfony\Component\Form\FormEvents;

final class AutoFillRpcEndpointSubscriber implements EventSubscriberInterface
{
    /**
     * @return array<string, string>
     */
    public static function getSubscribedEvents(): array
    {
        return [
            FormEvents::PRE_SUBMIT => 'autoFillRpcEndpoint',
        ];
    }

    public function autoFillRpcEndpoint(FormEvent $event): void
    {
        $data = $event->getData();
        if (!is_array($data)) {
            return;
        }

        $presetValue = $data['rpcPreset'] ?? null;
        if (!is_string($presetValue)) {
            return;
        }

        $preset = EvmRpcPreset::tryFrom($presetValue);
        if ($preset === null) {
            return;
        }

        $endpoint = $data['rpcEndpoint'] ?? null;
        if (is_string($endpoint) && trim($endpoint) !== '') {
            return;
        }

        $data['rpcEndpoint'] = $preset->endpoint();
        $event->setData($data);
    }
}
