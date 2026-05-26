<?php

declare(strict_types=1);

namespace App\UI\Web\Form\EventSubscriber;

use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\Form\FormEvent;
use Symfony\Component\Form\FormEvents;

final class TrimSubmittedStringsSubscriber implements EventSubscriberInterface
{
    private const array SENSITIVE_FIELDS = [
        'password' => true,
    ];

    /**
     * @return array<string, string>
     */
    public static function getSubscribedEvents(): array
    {
        return [
            FormEvents::PRE_SUBMIT => 'trim',
        ];
    }

    public function trim(FormEvent $event): void
    {
        $data = $event->getData();
        if (!is_array($data)) {
            return;
        }

        $event->setData($this->trimData($data));
    }

    /**
     * @param mixed $data
     *
     * @return mixed
     */
    private function trimData(mixed $data): mixed
    {
        if (is_string($data)) {
            return trim($data);
        }

        if (!is_array($data)) {
            return $data;
        }

        foreach ($data as $key => $value) {
            if (is_string($key) && isset(self::SENSITIVE_FIELDS[$key])) {
                continue;
            }

            $data[$key] = $this->trimData($value);
        }

        return $data;
    }
}
