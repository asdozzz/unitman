<?php

declare(strict_types=1);

namespace App\Utils\Converter;

use App\Utils\Model\Locale\SupportedLocale;
use Symfony\Component\EventDispatcher\Attribute\AsEventListener;
use Symfony\Component\HttpKernel\Event\RequestEvent;
use Symfony\Component\HttpKernel\KernelEvents;
use Symfony\Component\Security\Core\Authentication\Token\Storage\TokenStorageInterface;

/**
 * Resolves the request locale.
 *
 * Priority 0 puts it right after the firewall (priority 8), so the security
 * token is already populated and the account locale is available.
 *
 * Order of precedence:
 *   1. the authenticated account locale (accounts.locale);
 *   2. the Accept-Language header;
 *   3. the canonical locale (en).
 */
#[AsEventListener(event: KernelEvents::REQUEST, method: 'onKernelRequest', priority: 0)]
final class ResolveLocaleListener
{
    public function __construct(private TokenStorageInterface $tokenStorage)
    {
    }

    public function onKernelRequest(RequestEvent $event): void
    {
        if (!$event->isMainRequest()) {
            return;
        }

        $request = $event->getRequest();

        $user = $this->tokenStorage->getToken()?->getUser();

        if ($user !== null && method_exists($user, 'getLocale')) {
            $request->setLocale(SupportedLocale::normalize($user->getLocale()));

            return;
        }

        $request->setLocale($this->resolveFromHeader($request));
    }

    private function resolveFromHeader(\Symfony\Component\HttpFoundation\Request $request): string
    {
        $header = $request->headers->get('Accept-Language');

        if ($header === null || $header === '') {
            return SupportedLocale::canon();
        }

        // "ru-RU,ru;q=0.9,en;q=0.8" → ["ru-RU", "ru", "en"]
        $candidates = [];

        foreach (explode(',', $header) as $part) {
            $candidate = explode(';', $part)[0];
            $candidate = trim($candidate);

            if ($candidate !== '') {
                $candidates[] = $candidate;
            }
        }

        foreach ($candidates as $candidate) {
            $normalized = SupportedLocale::normalize($candidate);

            // normalize() silently degrades to the canon, so check the raw value
            if (SupportedLocale::isSupported(explode('-', str_replace('_', '-', strtolower($candidate)))[0])) {
                return $normalized;
            }
        }

        return SupportedLocale::canon();
    }
}