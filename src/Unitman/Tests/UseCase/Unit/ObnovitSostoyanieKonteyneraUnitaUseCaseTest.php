<?php

declare(strict_types=1);

namespace App\Unitman\Tests\UseCase\Unit;

use App\Unitman\Business\Command\Unit\ObnovitSostoyanieKonteyneraUnita;
use App\Unitman\Business\Port\Unit\UmeetObnovlyatSostoyanieKonteyneraUnita;
use App\Unitman\Business\Port\Unit\UmeetOpoveshatObIzmeneniiUnita;
use App\Unitman\Business\UseCase\Unit\ObnovitSostoyanieKonteyneraUnitaUseCase;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

/**
 * Детерминированный unit-тест use case без БД.
 *
 * Фейк репозитория воспроизводит merge-семантику реального SQL
 * (`payload = payload || cast(:patch as jsonb)`): патч накладывается поверх
 * существующего payload, поэтому остальные ключи (в том числе
 * `statistikaKonteinera`) не теряются.
 */
final class ObnovitSostoyanieKonteyneraUnitaUseCaseTest extends TestCase
{
    /**
     * @var array<string, mixed>
     */
    private const STATISTIKA_KONTEINERA = [
        'cpu' => '1.5',
        'memory' => '256MiB',
        'services' => [
            ['name' => 'web', 'state' => 'running'],
        ],
    ];

    #[Test]
    public function mismatch_sets_all_fields_in_payload(): void
    {
        $repository = new FakeUmeetObnovlyatSostoyanieKonteyneraUnita($this->bazoviyPayload(), true);
        $notifier = new FakeUmeetOpoveshatObIzmeneniiUnita();

        $this->useCase($repository, $notifier)->handle(
            new ObnovitSostoyanieKonteyneraUnita('unit-1', true, 'running', 'absent', 1730000000)
        );

        $this->assertSame(1, $repository->vyzovov);
        $this->assertSame('unit-1', $repository->posledniyUnitId);
        $this->assertTrue($repository->posledniyMismatch);
        $this->assertSame('running', $repository->posledniyExpectedState);
        $this->assertSame('absent', $repository->posledniyActualState);
        $this->assertSame(1730000000, $repository->posledniyDetectedAt);

        $this->assertTrue($repository->payload['containerStateMismatch']);
        $this->assertSame('running', $repository->payload['containerStateMismatchExpected']);
        $this->assertSame('absent', $repository->payload['containerStateMismatchActual']);
        $this->assertSame(1730000000, $repository->payload['containerStateMismatchDetectedAt']);
    }

    #[Test]
    public function resolved_clears_all_mismatch_fields(): void
    {
        $payload = $this->bazoviyPayload();
        $payload['containerStateMismatch'] = true;
        $payload['containerStateMismatchExpected'] = 'running';
        $payload['containerStateMismatchActual'] = 'absent';
        $payload['containerStateMismatchDetectedAt'] = 1729000000;

        $repository = new FakeUmeetObnovlyatSostoyanieKonteyneraUnita($payload, true);
        $notifier = new FakeUmeetOpoveshatObIzmeneniiUnita();

        $this->useCase($repository, $notifier)->handle(
            new ObnovitSostoyanieKonteyneraUnita('unit-1', false)
        );

        $this->assertFalse($repository->payload['containerStateMismatch']);
        $this->assertNull($repository->payload['containerStateMismatchExpected']);
        $this->assertNull($repository->payload['containerStateMismatchActual']);
        $this->assertNull($repository->payload['containerStateMismatchDetectedAt']);
    }

    #[Test]
    public function statistika_konteinera_is_not_lost_after_both_updates(): void
    {
        $repository = new FakeUmeetObnovlyatSostoyanieKonteyneraUnita($this->bazoviyPayload(), true);
        $notifier = new FakeUmeetOpoveshatObIzmeneniiUnita();
        $useCase = $this->useCase($repository, $notifier);

        $useCase->handle(new ObnovitSostoyanieKonteyneraUnita('unit-1', true, 'running', 'absent', 1730000000));

        $this->assertArrayHasKey('statistikaKonteinera', $repository->payload);
        $this->assertSame(self::STATISTIKA_KONTEINERA, $repository->payload['statistikaKonteinera']);

        $useCase->handle(new ObnovitSostoyanieKonteyneraUnita('unit-1', false));

        $this->assertArrayHasKey('statistikaKonteinera', $repository->payload);
        $this->assertSame(self::STATISTIKA_KONTEINERA, $repository->payload['statistikaKonteinera']);
        $this->assertSame('unit-name', $repository->payload['name']);
    }

    #[Test]
    public function notifier_is_called_once_when_repository_reports_update(): void
    {
        $repository = new FakeUmeetObnovlyatSostoyanieKonteyneraUnita($this->bazoviyPayload(), true);
        $notifier = new FakeUmeetOpoveshatObIzmeneniiUnita();

        $this->useCase($repository, $notifier)->handle(
            new ObnovitSostoyanieKonteyneraUnita('unit-1', true, 'running', 'absent', 1730000000)
        );

        $this->assertSame(1, $notifier->vyzovov);
        $this->assertSame(['unit-1'], $notifier->unitIds);
    }

    #[Test]
    public function notifier_is_not_called_when_repository_reports_no_update(): void
    {
        $repository = new FakeUmeetObnovlyatSostoyanieKonteyneraUnita($this->bazoviyPayload(), false);
        $notifier = new FakeUmeetOpoveshatObIzmeneniiUnita();

        $this->useCase($repository, $notifier)->handle(
            new ObnovitSostoyanieKonteyneraUnita('unit-1', true, 'running', 'absent', 1730000000)
        );

        $this->assertSame(0, $notifier->vyzovov);
        $this->assertSame([], $notifier->unitIds);
    }

    #[Test]
    public function resolved_passes_null_fields_to_repository(): void
    {
        $repository = new FakeUmeetObnovlyatSostoyanieKonteyneraUnita($this->bazoviyPayload(), true);
        $notifier = new FakeUmeetOpoveshatObIzmeneniiUnita();

        $this->useCase($repository, $notifier)->handle(
            new ObnovitSostoyanieKonteyneraUnita('unit-1', false)
        );

        $this->assertSame(1, $repository->vyzovov);
        $this->assertFalse($repository->posledniyMismatch);
        $this->assertNull($repository->posledniyExpectedState);
        $this->assertNull($repository->posledniyActualState);
        $this->assertNull($repository->posledniyDetectedAt);
    }

    private function useCase(
        UmeetObnovlyatSostoyanieKonteyneraUnita $repository,
        UmeetOpoveshatObIzmeneniiUnita $notifier,
    ): ObnovitSostoyanieKonteyneraUnitaUseCase {
        return new ObnovitSostoyanieKonteyneraUnitaUseCase($repository, $notifier);
    }

    /**
     * @return array<string, mixed>
     */
    private function bazoviyPayload(): array
    {
        return [
            'id' => 'unit-1',
            'name' => 'unit-name',
            'statistikaKonteinera' => self::STATISTIKA_KONTEINERA,
            'containerStateMismatch' => false,
            'containerStateMismatchExpected' => null,
            'containerStateMismatchActual' => null,
            'containerStateMismatchDetectedAt' => null,
        ];
    }
}

/**
 * Фейк порта обновления состояния: хранит payload и применяет к нему патч
 * так же, как `payload || cast(:patch as jsonb)` в PostgreSQL.
 */
final class FakeUmeetObnovlyatSostoyanieKonteyneraUnita implements UmeetObnovlyatSostoyanieKonteyneraUnita
{
    /**
     * @var array<string, mixed>
     */
    public array $payload;

    public int $vyzovov = 0;

    public string $posledniyUnitId = '';

    public bool $posledniyMismatch = false;

    public ?string $posledniyExpectedState = null;

    public ?string $posledniyActualState = null;

    public ?int $posledniyDetectedAt = null;

    /**
     * @param array<string, mixed> $payload
     */
    public function __construct(array $payload, private bool $result)
    {
        $this->payload = $payload;
    }

    public function obnovitSostoyanieKonteynera(
        string $unitId,
        bool $mismatch,
        ?string $expectedState,
        ?string $actualState,
        ?int $detectedAt,
    ): bool {
        ++$this->vyzovov;
        $this->posledniyUnitId = $unitId;
        $this->posledniyMismatch = $mismatch;
        $this->posledniyExpectedState = $expectedState;
        $this->posledniyActualState = $actualState;
        $this->posledniyDetectedAt = $detectedAt;

        $patch = [
            'containerStateMismatch' => $mismatch,
            'containerStateMismatchExpected' => $mismatch ? $expectedState : null,
            'containerStateMismatchActual' => $mismatch ? $actualState : null,
            'containerStateMismatchDetectedAt' => $mismatch ? $detectedAt : null,
        ];

        $this->payload = array_merge($this->payload, $patch);

        return $this->result;
    }
}

/**
 * Фейк notifier-порта: считает вызовы и запоминает unitId.
 */
final class FakeUmeetOpoveshatObIzmeneniiUnita implements UmeetOpoveshatObIzmeneniiUnita
{
    public int $vyzovov = 0;

    /**
     * @var list<string>
     */
    public array $unitIds = [];

    public function opoveshatObObnovlenii(string $unitId): void
    {
        ++$this->vyzovov;
        $this->unitIds[] = $unitId;
    }
}
