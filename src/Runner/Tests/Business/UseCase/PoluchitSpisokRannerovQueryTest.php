<?php

declare(strict_types=1);

namespace App\Runner\Tests\Business\UseCase;

use App\Runner\Business\Model\RunnerState;
use App\Runner\Business\Port\UmeetProveryatPravaAdmina;
use App\Runner\Business\UseCase\PoluchitSpisokRannerovQuery;
use App\Runner\Tests\Support\InMemoryRunnerRepository;
use PHPUnit\Framework\TestCase;

final class PoluchitSpisokRannerovQueryTest extends TestCase
{
    public function test_list_includes_public_address_fields_and_no_secret(): void
    {
        $repository = new InMemoryRunnerRepository();

        $registered = RunnerState::sozdatZaregistrirovanniiRunnerV2(
            'runner-with-address',
            'registry.example.com',
            'runner-with-address.example.com',
            1764000000,
        );
        $registered->ustanovitPublicAddress('https://runner-with-address.example.com');
        $repository->save($registered);

        $repository->save(RunnerState::sozdatRunner('runner-without-address', 'runner-without-address'));

        $query = new PoluchitSpisokRannerovQuery($this->security(true), $repository);

        $karty = [];
        foreach ($query->handle() as $karta) {
            $karty[$karta->id] = $karta;
        }

        self::assertArrayHasKey('runner-with-address', $karty);
        self::assertSame('https://runner-with-address.example.com', $karty['runner-with-address']->publicAddress);
        self::assertSame(1764000000, $karty['runner-with-address']->registeredAt);
        self::assertTrue($karty['runner-with-address']->hasPublicAddress);

        self::assertArrayHasKey('runner-without-address', $karty);
        self::assertNull($karty['runner-without-address']->publicAddress);
        self::assertFalse($karty['runner-without-address']->hasPublicAddress);

        $json = json_encode($karty['runner-with-address'], JSON_THROW_ON_ERROR);
        self::assertStringContainsString('"publicAddress"', $json);
        self::assertStringContainsString('"registeredAt"', $json);
        self::assertStringContainsString('"hasPublicAddress"', $json);
        self::assertStringNotContainsString('secret', $json);
        self::assertStringNotContainsString('proxyUrl', $json);
        self::assertStringNotContainsString('proxy_url', $json);
    }

    public function test_non_admin_is_rejected(): void
    {
        $query = new PoluchitSpisokRannerovQuery($this->security(false), new InMemoryRunnerRepository());

        $this->expectException(\Exception::class);
        $this->expectExceptionMessage('security.access_denied');

        $query->handle();
    }

    private function security(bool $isAdmin): UmeetProveryatPravaAdmina
    {
        $security = $this->createMock(UmeetProveryatPravaAdmina::class);
        $security->method('estAdministrator')->willReturn($isAdmin);

        return $security;
    }
}
