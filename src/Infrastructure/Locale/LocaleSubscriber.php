<?php

declare(strict_types=1);

namespace App\Infrastructure\Locale;

use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\HttpKernel\Event\RequestEvent;
use Symfony\Component\HttpKernel\KernelEvents;

final class LocaleSubscriber implements EventSubscriberInterface
{
    private const array SUPPORTED_LOCALES = ['en', 'fr', 'de', 'es', 'pt', 'nl'];
    private const string DEFAULT_LOCALE = 'en';

    public static function getSubscribedEvents(): array
    {
        return [
            KernelEvents::REQUEST => [['onKernelRequest', 20]],
        ];
    }

    public function onKernelRequest(RequestEvent $event): void
    {
        $request = $event->getRequest();

        $locale = $request->attributes->get('_locale');

        if ($locale && in_array($locale, self::SUPPORTED_LOCALES, true)) {
            $request->setLocale($locale);
        } else {
            $request->setLocale(self::DEFAULT_LOCALE);
        }
    }
}
