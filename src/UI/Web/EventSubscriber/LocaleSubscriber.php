<?php

declare(strict_types=1);

namespace App\UI\Web\EventSubscriber;

use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\HttpKernel\Event\RequestEvent;
use Symfony\Component\HttpKernel\KernelEvents;

final class LocaleSubscriber implements EventSubscriberInterface
{
    private const DEFAULT_LOCALE = 'en';
    private const SUPPORTED_LOCALES = ['en', 'ru'];
    private const SESSION_KEY = '_locale';

    public static function getSubscribedEvents(): array
    {
        return [
            KernelEvents::REQUEST => ['onKernelRequest', 20],
        ];
    }

    public function onKernelRequest(RequestEvent $event): void
    {
        if (!$event->isMainRequest()) {
            return;
        }

        $request = $event->getRequest();
        $selected = (string) $request->query->get('_locale', '');

        if (in_array($selected, self::SUPPORTED_LOCALES, true)) {
            $request->setLocale($selected);
            if ($request->hasSession()) {
                $request->getSession()->set(self::SESSION_KEY, $selected);
            }

            return;
        }

        if ($request->hasSession()) {
            $sessionLocale = (string) $request->getSession()->get(self::SESSION_KEY, '');
            if (in_array($sessionLocale, self::SUPPORTED_LOCALES, true)) {
                $request->setLocale($sessionLocale);

                return;
            }
        }

        $preferred = $request->getPreferredLanguage(self::SUPPORTED_LOCALES) ?? self::DEFAULT_LOCALE;
        $request->setLocale($preferred);
    }
}
