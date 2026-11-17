<?php

declare(strict_types=1);

namespace App\Unitman\Tests\UseCase\Unit;

use App\Unitman\Business\Command\Unit\SozdatUnit;
use App\Unitman\Business\Model\Unit;
use App\Unitman\Business\Model\Unit\Event\DobavlenProzesVUnit;
use App\Unitman\Business\Model\Unit\Event\KonfigUnitaUstanovlen;
use App\Unitman\Business\Model\Unit\Event\UnitSozdan;
use App\Unitman\Business\Model\Unit\Event\UspehPodgotovkiUnitaUstanovlen;
use App\Unitman\Business\Model\Unit\Event\UspehSborkiUnitaUstanovlen;
use App\Unitman\Business\Model\Unit\UnitId;
use App\Unitman\Business\Port\Unit\UnitRepository;
use App\Unitman\Business\Port\Unit\ZanyatostUnitaRepository;
use App\Unitman\Business\Port\UnitmanSecurityService;
use App\Unitman\Business\UseCase\Unit\DobavitProzesPodgotovkiUseCase;
use App\Unitman\Business\UseCase\Unit\DobavitProzesZapuskaUseCase;
use App\Unitman\Business\UseCase\Unit\ProdvinutCiklUnitaUseCase;
use App\Unitman\Business\Utils\ProverkaSvobodyUnita;
use App\Unitman\Tests\Support\SequenceGuidGenerator;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;

/**
 * Продвижение конвейера юнита из интеграции (сессии OpenCode).
 *
 * Шаг идемпотентный: он продвигает цикл только из состояний SOBRAN и
 * PODGOTOVLEN, только когда предыдущие процессы завершены, и никогда не
 * роняет вызывающего — гонки гасятся внутри домена и считаются нормой.
 */
final class ProdvinutCiklUnitaUseCaseTest extends TestCase
{
    private const UNIT_ID = 'unit-1';

    private const AUTHOR_ID = 'author-1';

    private const MINIMAL_CONFIG = [
        'variables' => [],
        'prepare' => ['echo prepare'],
        'reset_prepare' => ['echo reset'],
        'up' => ['echo up'],
        'down' => ['echo down'],
        'services' => ['web' => []],
    ];

    #[Test]
    public function pustoie_unit_id_nichego_ne_delaet(): void
    {
        $repository = $this->repositoriy();
        $repository->expects($this->never())->method('getById');
        $repository->expects($this->never())->method('save');

        $this->useCase($repository)->handle('   ', self::AUTHOR_ID);
    }

    #[Test]
    public function pustoi_account_id_nichego_ne_delaet(): void
    {
        $repository = $this->repositoriy();
        $repository->expects($this->never())->method('getById');
        $repository->expects($this->never())->method('save');

        $this->useCase($repository)->handle(self::UNIT_ID, '  ');
    }

    #[Test]
    public function neizvestnii_unit_ne_brosaet_ischercheniya(): void
    {
        $repository = $this->repositoriy();
        $repository->expects($this->once())
            ->method('getById')
            ->willThrowException(new \DomainException('unit.not_found'));
        $repository->expects($this->never())->method('save');

        $this->useCase($repository)->handle(self::UNIT_ID, self::AUTHOR_ID);
    }

    #[Test]
    public function sozdannii_unit_ne_prodvigaetsya(): void
    {
        $unit = Unit::sozdatUnit(
            self::UNIT_ID,
            self::AUTHOR_ID,
            'prozes-1',
            new SozdatUnit('project-1', 'unit-name', 'feature/1', [], 3000),
        );
        // События создания (включая стартовый процесс) не должны попасть в проверку.
        $unit->releaseEvents();

        $repository = $this->repositoriy();
        $repository->expects($this->once())->method('getById')->willReturn($unit);
        $repository->expects($this->never())->method('save');

        $this->useCase($repository)->handle(self::UNIT_ID, self::AUTHOR_ID);

        $this->assertSame('SOZDAN', $unit->poluchitKodSostoyaniya());
        $this->assertEmpty($this->tipiSozdannihProzesov($unit));
    }

    #[Test]
    public function nezavershenii_prozes_blokiruet_prodvizhenie(): void
    {
        $unit = $this->sobranSProzesom('ZADACHI_DOBAVLENI');

        $repository = $this->repositoriy();
        $repository->expects($this->once())->method('getById')->willReturn($unit);
        $repository->expects($this->never())->method('save');

        $this->useCase($repository)->handle(self::UNIT_ID, self::AUTHOR_ID);

        $this->assertSame('USPESHNO_SOBRAN', $unit->poluchitKodSostoyaniya());
        $this->assertEmpty($this->tipiSozdannihProzesov($unit));
    }

    #[Test]
    public function sobranii_unit_zapuskaet_podgotovku(): void
    {
        $unit = $this->sobranSProzesom('SUCCESS', 'SUCCESS');

        $repository = $this->repositoriySUnitom($unit);

        $this->useCase($repository)->handle(self::UNIT_ID, self::AUTHOR_ID);

        $this->assertSame(['PODGOTOVKA'], $this->tipiSozdannihProzesov($unit));
    }

    #[Test]
    public function podgotovlennii_unit_zapuskaet_zapusk(): void
    {
        $unit = $this->podgotovlenniiUnit();

        $repository = $this->repositoriySUnitom($unit);

        $this->useCase($repository)->handle(self::UNIT_ID, self::AUTHOR_ID);

        $this->assertSame(['ZAPUSK'], $this->tipiSozdannihProzesov($unit));
    }

    #[Test]
    public function gonka_na_zapisi_gasitsya_domain_ischercheniem(): void
    {
        $unit = $this->sobranSProzesom('SUCCESS', 'SUCCESS');

        $repository = $this->createMock(UnitRepository::class);
        $repository->expects($this->exactly(2))
            ->method('getById')
            ->willReturnCallback(
                function (string $id) use ($unit): Unit {
                    static $vizalov = 0;
                    ++$vizalov;

                    if ($vizalov > 1) {
                        throw new \DomainException('unit.not_found');
                    }

                    return $unit;
                },
            );
        $repository->expects($this->never())->method('save');

        // Исключение из вложенного сценария не должно выходить наружу.
        $this->useCase($repository)->handle(self::UNIT_ID, self::AUTHOR_ID);

        $this->assertEmpty($this->tipiSozdannihProzesov($unit));
    }

    #[Test]
    public function ispolzovatel_peredannyi_v_integracii_popadaet_v_prozes(): void
    {
        $unit = $this->sobranSProzesom('SUCCESS', 'SUCCESS');

        $repository = $this->repositoriySUnitom($unit);

        $this->useCase($repository)->handle(self::UNIT_ID, 'integration-user');

        $events = $unit->releaseEvents();
        $dobavlenie = null;

        foreach ($events as $event) {
            if ($event instanceof DobavlenProzesVUnit) {
                $dobavlenie = $event;
            }
        }

        $this->assertInstanceOf(DobavlenProzesVUnit::class, $dobavlenie);
        $this->assertSame('integration-user', $dobavlenie->prozes['userId']);
        $this->assertSame('type-1', $dobavlenie->prozes['id']);
    }

    private function useCase(UnitRepository $repository): ProdvinutCiklUnitaUseCase
    {
        $generator = new SequenceGuidGenerator('type-');
        $proverkaSvobody = $this->proverkaSvobody();

        return new ProdvinutCiklUnitaUseCase(
            $repository,
            new DobavitProzesPodgotovkiUseCase($repository, $this->securityService(), $generator, $proverkaSvobody),
            new DobavitProzesZapuskaUseCase($repository, $this->securityService(), $generator, $proverkaSvobody),
        );
    }

    /**
     * Шаг конвейера вызывается самим OpenCode и гейт свободы не проходит,
     * поэтому реестр захватов здесь всегда отвечает «свободен».
     */
    private function proverkaSvobody(): ProverkaSvobodyUnita
    {
        $repository = $this->createMock(ZanyatostUnitaRepository::class);
        $repository->method('poluchitAktivnuyu')->willReturn(null);

        return new ProverkaSvobodyUnita($repository);
    }

    private function securityService(): UnitmanSecurityService
    {
        $service = $this->createMock(UnitmanSecurityService::class);
        $service->method('getCurrentUserId')->willReturn('current-user');

        return $service;
    }

    /**
     * Репозиторий, который отдаёт один и тот же агрегат на любой `getById`
     * и разрешает единственное сохранение.
     *
     * @return UnitRepository&MockObject
     */
    private function repositoriySUnitom(Unit $unit): UnitRepository
    {
        $repository = $this->repositoriy();
        $repository->expects($this->atLeastOnce())->method('getById')->willReturn($unit);
        $repository->expects($this->once())->method('save')->with($unit);

        return $repository;
    }

    /**
     * @return UnitRepository&MockObject
     */
    private function repositoriy(): UnitRepository
    {
        return $this->createMock(UnitRepository::class);
    }

    /**
     * Юнит в состоянии SOBRAN с завершённой сборкой, конфигом и одним
     * процессом в заданном состоянии — так проверяется ожидание конца цикла.
     */
    private function sobranSProzesom(string $sostoyanieProzesa, string $sostoyanieZadachi = 'NEW'): Unit
    {
        $sborka = $this->prozess('prozes-1', 'SBORKA', 'SUCCESS', 'job-1', 'SUCCESS');

        return $this->rekonstruirovat([
            $this->sozdanie(),
            new DobavlenProzesVUnit(self::UNIT_ID, $sborka),
            new UspehSborkiUnitaUstanovlen(
                self::UNIT_ID,
                'job-1',
                ['code' => 'USPESHNO_SOBRAN', 'commands' => []],
                $sborka,
            ),
            new KonfigUnitaUstanovlen(self::UNIT_ID, self::MINIMAL_CONFIG),
            new DobavlenProzesVUnit(
                self::UNIT_ID,
                $this->prozess('prozes-2', 'OBNOVLENIE', $sostoyanieProzesa, 'job-2', $sostoyanieZadachi),
            ),
        ]);
    }

    /**
     * Полный путь до PODGOTOVLEN: сборка, конфиг и успешная подготовка.
     */
    private function podgotovlenniiUnit(): Unit
    {
        $sborka = $this->prozess('prozes-1', 'SBORKA', 'SUCCESS', 'job-1', 'SUCCESS');
        $podgotovka = $this->prozess('prozes-2', 'PODGOTOVKA', 'SUCCESS', 'job-2', 'SUCCESS');

        return $this->rekonstruirovat([
            $this->sozdanie(),
            new DobavlenProzesVUnit(self::UNIT_ID, $sborka),
            new UspehSborkiUnitaUstanovlen(
                self::UNIT_ID,
                'job-1',
                ['code' => 'USPESHNO_SOBRAN', 'commands' => []],
                $sborka,
            ),
            new KonfigUnitaUstanovlen(self::UNIT_ID, self::MINIMAL_CONFIG),
            new DobavlenProzesVUnit(self::UNIT_ID, $podgotovka),
            new UspehPodgotovkiUnitaUstanovlen(
                self::UNIT_ID,
                'job-2',
                ['code' => 'USPESHNO_PODGOTOVLEN_K_ZAPUSKU', 'commands' => []],
                $podgotovka,
            ),
        ]);
    }

    private function sozdanie(): UnitSozdan
    {
        return new UnitSozdan(
            self::UNIT_ID,
            self::AUTHOR_ID,
            'project-1',
            'unit-name',
            'feature/1',
            ['code' => 'SOZDAN', 'commands' => []],
            [],
            3000,
        );
    }

    /**
     * @return array<string, mixed>
     */
    private function prozess(
        string $id,
        string $type,
        string $state,
        string $jobId,
        string $jobState,
    ): array {
        return [
            'id' => $id,
            'userId' => self::AUTHOR_ID,
            'type' => $type,
            'state' => $state,
            'jobs' => [
                [
                    'id' => $jobId,
                    'type' => $type,
                    'state' => $jobState,
                    'steps' => [],
                ],
            ],
        ];
    }

    /**
     * @param list<object> $events
     */
    private function rekonstruirovat(array $events): Unit
    {
        return Unit::reconstituteFromEvents(UnitId::fromString(self::UNIT_ID), $this->potok($events));
    }

    /**
     * @param list<object> $events
     *
     * @return \Generator<int, object, mixed, int>
     */
    private function potok(array $events): \Generator
    {
        foreach ($events as $event) {
            yield $event;
        }

        return count($events);
    }

    /**
     * @return list<string>
     */
    private function tipiSozdannihProzesov(Unit $unit): array
    {
        $tipi = [];

        foreach ($unit->releaseEvents() as $event) {
            if ($event instanceof DobavlenProzesVUnit) {
                $tipi[] = (string) $event->prozes['type'];
            }
        }

        return $tipi;
    }
}