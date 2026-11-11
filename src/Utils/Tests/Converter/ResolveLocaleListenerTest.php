<?php

declare(strict_types=1);

namespace App\Utils\Tests\Converter;

use App\Utils\Converter\ResolveLocaleListener;
use App\Utils\Model\Locale\SupportedLocale;
use PHPUnit\Framework\TestCase;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpKernel\Event\RequestEvent;
use Symfony\Component\HttpKernel\HttpKernelInterface;
use Symfony\Component\Security\Core\Authentication\Token\Storage\TokenStorage;
use Symfony\Component\Security\Core\Authentication\Token\UsernamePasswordToken;
use Symfony\Component\Security\Core\User\UserInterface;

/**
 * Covers the request-locale resolution order:
 * authenticated account locale → Accept-Language → canonical (en).
 */
final class ResolveLocaleListenerTest extends TestCase
{
    private function makeEvent(Request $request): RequestEvent
    {
        return new RequestEvent(
            $this->createMock(HttpKernelInterface::class),
            $request,
            HttpKernelInterface::MAIN_REQUEST,
        );
    }

    private function listenerFor(?string $accountLocale): ResolveLocaleListener
    {
        $tokenStorage = new TokenStorage();

        if ($accountLocale !== null) {
            $tokenStorage->setToken(new UsernamePasswordToken(new LocaleAwareUser($accountLocale), 'main'));
        }

        return new ResolveLocaleListener($tokenStorage);
    }

    public function testAccountLocaleWinsOverAcceptLanguage(): void
    {
        $request = Request::create('/api/unit');
        $request->headers->set('Accept-Language', 'en-US,en;q=0.9');

        $this->listenerFor('ru')->onKernelRequest($this->makeEvent($request));

        self::assertSame('ru', $request->getLocale());
    }

    public function testAccountLocaleIsNormalized(): void
    {
        $request = Request::create('/api/unit');

        $this->listenerFor('ru_RU')->onKernelRequest($this->makeEvent($request));

        self::assertSame('ru', $request->getLocale());
    }

    public function testUnsupportedAccountLocaleFallsBackToCanon(): void
    {
        $request = Request::create('/api/unit');

        $this->listenerFor('de')->onKernelRequest($this->makeEvent($request));

        self::assertSame(SupportedLocale::canon(), $request->getLocale());
    }

    public function testAcceptLanguageIsUsedWithoutToken(): void
    {
        $request = Request::create('/api/unit');
        $request->headers->set('Accept-Language', 'de-DE,de;q=0.9,ru;q=0.8');

        $this->listenerFor(null)->onKernelRequest($this->makeEvent($request));

        self::assertSame('ru', $request->getLocale());
    }

    public function testCanonIsUsedWithoutTokenAndHeader(): void
    {
        $request = Request::create('/api/unit');

        $this->listenerFor(null)->onKernelRequest($this->makeEvent($request));

        self::assertSame(SupportedLocale::canon(), $request->getLocale());
    }

    public function testSubRequestIsIgnored(): void
    {
        $request = Request::create('/api/unit');
        $request->headers->set('Accept-Language', 'ru');

        $event = new RequestEvent(
            $this->createMock(HttpKernelInterface::class),
            $request,
            HttpKernelInterface::SUB_REQUEST,
        );

        $this->listenerFor(null)->onKernelRequest($event);

        self::assertNotSame('ru', $request->getLocale());
    }

    public function testUserWithoutLocaleMethodIsIgnored(): void
    {
        $tokenStorage = new TokenStorage();
        $tokenStorage->setToken(new UsernamePasswordToken(new PlainUser(), 'main'));

        $request = Request::create('/api/unit');
        $request->headers->set('Accept-Language', 'ru');

        (new ResolveLocaleListener($tokenStorage))->onKernelRequest($this->makeEvent($request));

        self::assertSame('ru', $request->getLocale());
    }
}

/**
 * Minimal user exposing the same contract as the real JWT user.
 */
final class LocaleAwareUser implements UserInterface
{
    public function __construct(private string $locale)
    {
    }

    public function getLocale(): string
    {
        return $this->locale;
    }

    public function getRoles(): array
    {
        return ['ROLE_USER'];
    }

    public function eraseCredentials(): void
    {
    }

    public function getUserIdentifier(): string
    {
        return 'locale-aware';
    }
}

/**
 * User without a locale, as a foreign module could provide.
 */
final class PlainUser implements UserInterface
{
    public function getRoles(): array
    {
        return ['ROLE_USER'];
    }

    public function eraseCredentials(): void
    {
    }

    public function getUserIdentifier(): string
    {
        return 'plain';
    }
}
