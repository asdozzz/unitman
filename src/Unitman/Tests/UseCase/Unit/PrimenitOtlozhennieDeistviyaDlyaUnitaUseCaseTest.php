<?php

declare(strict_types=1);

namespace App\Unitman\Tests\UseCase\Unit;

use App\Unitman\Business\Command\Unit\SozdatUnit;
use App\Unitman\Business\Model\SobitieIzHranilisha\TipSobitiya;
use App\Unitman\Business\Model\Unit;
use App\Unitman\Business\Model\Unit\OtlozhennoeDeistvie;
use App\Unitman\Business\Model\Unit\TipZanyatosti;
use App\Unitman\Business\Model\Unit\ZanyatostUnita;
use App\Unitman\Business\Port\Project\ProjectRepository;
use App\Unitman\Business\Port\Unit\OtlozhennieDeistviyaRepository;
use App\Unitman\Business\Port\Unit\UnitRepository;
use App\Unitman\Business\Port\Unit\ZanyatostUnitaRepository;
use App\Unitman\Business\UseCase\Unit\DobavitProzesObnovleniyaUseCase;
use App\Unitman\Business\UseCase\Unit\DobavitProzesUdaleniyaUseCase;
use App\Unitman\Business\UseCase\Unit\PrimenitOtlozhennieDeistviyaDlyaUnitaUseCase;
use App\Unitman\Business\Utils\ProverkaSvobodyUnita;
use App\Unitman\Tests\Support\FakeUnitmanSecurityService;
use App\Unitman\Tests\Support\SequenceGuidGenerator;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Psr\Log\LoggerInterface;

/**
 * Применение отложенных вебхук-намерений к юниту.
 *
 * Ключевые инварианты:
 * - занятый ходом OpenCode юнит не применяет намерение, а переносит попытку
 *   с растущей паузой;
 * - исчерпание бюджета попыток удаляет намерение, а не держит его вечно;
 * - исчезнувший из event store юнит уносит свои намерения с собой;
 * - успешное применение закрывает намерение.
 *
 * Ошибки наружу не пробрасываются: обработчик вебхука уже отдал событие,
 * и падение здесь означало бы его потерю.
 */
final class PrimenitOtlozhennieDeistviyaDlyaUnitaUseCaseTest extends TestCase
{
    private const UNIT_ID = 'unit-1';
    private const PROJECT_ID = 'project-1';

    #[Test]
    public function bez_namerenii_nichego_ne_delaet(): void
    {
        $otlozhennie = $this->createMock(OtlozhennieDeistviyaRepository::class);
        $otlozhennie->method('poluchitGotovieDlyaUnita')->willReturn([]);
        $otlozhennie->expects($this->never())->method('udovletvorit');
        $otlozhennie->expects($this->never())->method('otmetitPopytku');
        $otlozhennie->expects($this->never())->method('udalitPoUnituITipu');

        $unitRepository = $this->createMock(UnitRepository::class);
        $unitRepository->expects($this->never())->method('getById');

        $this->useCase($otlozhennie, $unitRepository, $this->createMock(LoggerInterface::class))
            ->handle(self::UNIT_ID);
    }

    #[Test]
    public function ischeznuvshii_unit_udaliaet_svoi_namereniya(): void
    {
        $otlozhennie = $this->createMock(OtlozhennieDeistviyaRepository::class);
        $otlozhennie->method('poluchitGotovieDlyaUnita')->willReturn([
            $this->deistvie(TipSobitiya::KOD_OBNOVLEN),
        ]);
        $otlozhennie->expects($this->once())
            ->method('udalitPoUnituITipu')
            ->with(self::UNIT_ID, TipSobitiya::KOD_OBNOVLEN);
        $otlozhennie->expects($this->never())->method('otmetitPopytku');

        $unitRepository = $this->createMock(UnitRepository::class);
        $unitRepository->method('getById')
            ->willThrowException(new \RuntimeException('aggregate not found'));

        $logger = $this->createMock(LoggerInterface::class);
        $logger->expects($this->once())->method('warning');

        $this->useCase($otlozhennie, $unitRepository, $logger)->handle(self::UNIT_ID);
    }

    #[Test]
    public function zanyatii_hodom_opencode_unit_otkladyvaet_namerenie(): void
    {
        $otlozhennie = $this->createMock(OtlozhennieDeistviyaRepository::class);
        $otlozhennie->method('poluchitGotovieDlyaUnita')->willReturn([
            $this->deistvie(TipSobitiya::KOD_OBNOVLEN),
        ]);
        $otlozhennie->expects($this->never())->method('udovletvorit');
        $otlozhennie->expects($this->never())->method('udalitPoUnituITipu');

        $popytka = $this->perehvatitPopytku($otlozhennie);

        $unitRepository = $this->createMock(UnitRepository::class);
        $unitRepository->method('getById')->willReturn($this->unit());
        $unitRepository->expects($this->never())->method('save');

        $this->useCase(
            $otlozhennie,
            $unitRepository,
            $this->createMock(LoggerInterface::class),
            $this->zanyatost(TipZanyatosti::OPENCODE_HOD),
        )->handle(self::UNIT_ID);

        self::assertSame(1, $popytka->kolichestvo);
        self::assertSame(30, $popytka->zaderzhkaSekund());
        self::assertSame('unit.zanyat_hodom_opencode', $popytka->lastError);
    }

    /**
     * @return \Generator<string, array{int, int}>
     */
    public static function zaderzhki(): \Generator
    {
        yield 'первая попытка' => [0, 30];
        yield 'четвёртая попытка' => [3, 240];
        yield 'потолок задержки' => [10, 900];
    }

    #[Test]
    #[DataProvider('zaderzhki')]
    public function pavza_mezhdu_popytkami_rastet_do_potolka(int $attempts, int $ozhidaemaya): void
    {
        $otlozhennie = $this->createMock(OtlozhennieDeistviyaRepository::class);
        $otlozhennie->method('poluchitGotovieDlyaUnita')->willReturn([
            $this->deistvie(TipSobitiya::KOD_OBNOVLEN, $attempts),
        ]);

        $popytka = $this->perehvatitPopytku($otlozhennie);

        $unitRepository = $this->createMock(UnitRepository::class);
        $unitRepository->method('getById')->willReturn($this->unit());

        $this->useCase(
            $otlozhennie,
            $unitRepository,
            $this->createMock(LoggerInterface::class),
            $this->zanyatost(TipZanyatosti::OPENCODE_HOD),
        )->handle(self::UNIT_ID);

        self::assertSame(1, $popytka->kolichestvo);
        self::assertSame($ozhidaemaya, $popytka->zaderzhkaSekund());
    }

    #[Test]
    public function ischerpanie_budzheta_udaliaet_namerenie(): void
    {
        $otlozhennie = $this->createMock(OtlozhennieDeistviyaRepository::class);
        $otlozhennie->method('poluchitGotovieDlyaUnita')->willReturn([
            $this->deistvie(
                TipSobitiya::KOD_OBNOVLEN,
                PrimenitOtlozhennieDeistviyaDlyaUnitaUseCase::MAX_ATTEMPTS - 1,
            ),
        ]);
        $otlozhennie->expects($this->once())
            ->method('udalitPoUnituITipu')
            ->with(self::UNIT_ID, TipSobitiya::KOD_OBNOVLEN);
        $otlozhennie->expects($this->never())->method('otmetitPopytku');

        $unitRepository = $this->createMock(UnitRepository::class);
        $unitRepository->method('getById')->willReturn($this->unit());

        $logger = $this->createMock(LoggerInterface::class);
        $logger->expects($this->once())->method('error');

        $this->useCase(
            $otlozhennie,
            $unitRepository,
            $logger,
            $this->zanyatost(TipZanyatosti::OPENCODE_HOD),
        )->handle(self::UNIT_ID);
    }

    #[Test]
    public function nepodderzhivaemii_tip_otkladyvaetsya_a_ne_udaliaetsya(): void
    {
        $otlozhennie = $this->createMock(OtlozhennieDeistviyaRepository::class);
        $otlozhennie->method('poluchitGotovieDlyaUnita')->willReturn([
            $this->deistvie(TipSobitiya::VETKA_SOZDANA),
        ]);
        $otlozhennie->expects($this->never())->method('udovletvorit');
        $otlozhennie->expects($this->never())->method('udalitPoUnituITipu');

        $popytka = $this->perehvatitPopytku($otlozhennie);

        $unitRepository = $this->createMock(UnitRepository::class);
        $unitRepository->method('getById')->willReturn($this->unit());
        $unitRepository->expects($this->never())->method('save');

        $this->useCase($otlozhennie, $unitRepository, $this->createMock(LoggerInterface::class))
            ->handle(self::UNIT_ID);

        self::assertSame(1, $popytka->kolichestvo);
        self::assertSame(30, $popytka->zaderzhkaSekund());
        self::assertSame('unit.otlozhennoe_deistvie.tip_ne_podderzhan', $popytka->lastError);
    }

    #[Test]
    public function svobodnii_unit_primenyaet_i_zakrivaet_namerenie(): void
    {
        $otlozhennie = $this->createMock(OtlozhennieDeistviyaRepository::class);
        $otlozhennie->method('poluchitGotovieDlyaUnita')->willReturn([
            $this->deistvie(TipSobitiya::VETKA_UDALENA),
        ]);
        $otlozhennie->expects($this->once())
            ->method('udovletvorit')
            ->with(self::UNIT_ID, TipSobitiya::VETKA_UDALENA);
        $otlozhennie->expects($this->never())->method('otmetitPopytku');
        $otlozhennie->expects($this->never())->method('udalitPoUnituITipu');

        $unitRepository = $this->createMock(UnitRepository::class);
        $unitRepository->method('getById')->willReturn($this->unit());
        $unitRepository->expects($this->once())->method('save');

        $logger = $this->createMock(LoggerInterface::class);
        $logger->expects($this->once())->method('info');

        $this->useCase($otlozhennie, $unitRepository, $logger)->handle(self::UNIT_ID);
    }

    #[Test]
    public function svobodnii_unit_primenyaet_otlozhennoe_sliyanie_kak_udalenie(): void
    {
        $otlozhennie = $this->createMock(OtlozhennieDeistviyaRepository::class);
        $otlozhennie->method('poluchitGotovieDlyaUnita')->willReturn([
            $this->deistvie(TipSobitiya::VETKA_SMERZHENA),
        ]);
        $otlozhennie->expects($this->once())
            ->method('udovletvorit')
            ->with(self::UNIT_ID, TipSobitiya::VETKA_SMERZHENA);
        $otlozhennie->expects($this->never())->method('otmetitPopytku');
        $otlozhennie->expects($this->never())->method('udalitPoUnituITipu');

        $unitRepository = $this->createMock(UnitRepository::class);
        $unitRepository->method('getById')->willReturn($this->unit());
        $unitRepository->expects($this->once())->method('save');

        $logger = $this->createMock(LoggerInterface::class);
        $logger->expects($this->once())->method('info');

        $this->useCase($otlozhennie, $unitRepository, $logger)->handle(self::UNIT_ID);
    }

    #[Test]
    public function padenie_vnutrennego_scenariya_ne_teryaet_namerenie(): void
    {
        $otlozhennie = $this->createMock(OtlozhennieDeistviyaRepository::class);
        $otlozhennie->method('poluchitGotovieDlyaUnita')->willReturn([
            $this->deistvie(TipSobitiya::VETKA_UDALENA),
        ]);
        $otlozhennie->expects($this->never())->method('udovletvorit');
        $otlozhennie->expects($this->never())->method('udalitPoUnituITipu');

        $popytka = $this->perehvatitPopytku($otlozhennie);

        $unitRepository = $this->createMock(UnitRepository::class);
        $unitRepository->method('getById')->willReturn($this->unit());
        $unitRepository->method('save')
            ->willThrowException(new \RuntimeException('event store unavailable'));

        $logger = $this->createMock(LoggerInterface::class);
        $logger->expects($this->once())->method('error');

        $this->useCase($otlozhennie, $unitRepository, $logger)->handle(self::UNIT_ID);

        self::assertSame(1, $popytka->kolichestvo);
        self::assertSame('event store unavailable', $popytka->lastError);
    }

    private function useCase(
        OtlozhennieDeistviyaRepository $otlozhennie,
        UnitRepository $unitRepository,
        LoggerInterface $logger,
        ?ZanyatostUnita $zanyatost = null,
    ): PrimenitOtlozhennieDeistviyaDlyaUnitaUseCase {
        $zanyatostRepository = $this->createMock(ZanyatostUnitaRepository::class);
        $zanyatostRepository->method('poluchitAktivnuyu')->willReturn($zanyatost);

        $proverkaSvobody = new ProverkaSvobodyUnita($zanyatostRepository);

        return new PrimenitOtlozhennieDeistviyaDlyaUnitaUseCase(
            $otlozhennie,
            $proverkaSvobody,
            $unitRepository,
            new DobavitProzesObnovleniyaUseCase(
                $unitRepository,
                new FakeUnitmanSecurityService(),
                new SequenceGuidGenerator('prozes-'),
                $this->createMock(ProjectRepository::class),
                $proverkaSvobody,
            ),
            new DobavitProzesUdaleniyaUseCase(
                $unitRepository,
                new FakeUnitmanSecurityService(),
                new SequenceGuidGenerator('prozes-'),
                $proverkaSvobody,
            ),
            $logger,
        );
    }

    /**
     * Запоминает единственный вызов `otmetitPopytku`, чтобы проверить паузу
     * и текст причины после выполнения сценария.
     */
    private function perehvatitPopytku(OtlozhennieDeistviyaRepository $otlozhennie): PopytkaSpy
    {
        $spy = new PopytkaSpy();

        $otlozhennie->expects($this->atLeastOnce())
            ->method('otmetitPopytku')
            ->willReturnCallback(
                static function (
                    string $unitId,
                    TipSobitiya $tipSobitiya,
                    \DateTimeImmutable $nextAttemptAt,
                    ?string $lastError,
                ) use ($spy): void {
                    $spy->zafiksirovat($unitId, $tipSobitiya, $nextAttemptAt, $lastError);
                },
            );

        return $spy;
    }

    private function deistvie(TipSobitiya $tipSobitiya, int $attempts = 0): OtlozhennoeDeistvie
    {
        return new OtlozhennoeDeistvie(
            self::UNIT_ID,
            self::PROJECT_ID,
            $tipSobitiya,
            ['vetka' => 'feature/1', 'unixtime' => 1000],
            $attempts,
            new \DateTimeImmutable('-1 minute'),
        );
    }

    private function zanyatost(TipZanyatosti $tip): ZanyatostUnita
    {
        $now = new \DateTimeImmutable();

        return new ZanyatostUnita(
            self::UNIT_ID,
            'opencode:turn-1',
            $tip,
            $now,
            $now->modify('+20 minutes'),
        );
    }

    private function unit(): Unit
    {
        return Unit::sozdatUnit(
            self::UNIT_ID,
            'author-1',
            'prozes-1',
            new SozdatUnit(self::PROJECT_ID, 'unit-name', 'feature/1', [], 3000),
        );
    }
}

/**
 * Минимальный носитель состояния перехваченной попытки.
 */
final class PopytkaSpy
{
    public int $kolichestvo = 0;

    public string $unitId = '';

    public ?TipSobitiya $tipSobitiya = null;

    public ?\DateTimeImmutable $nextAttemptAt = null;

    public ?string $lastError = null;

    public function zafiksirovat(
        string $unitId,
        TipSobitiya $tipSobitiya,
        \DateTimeImmutable $nextAttemptAt,
        ?string $lastError,
    ): void {
        ++$this->kolichestvo;
        $this->unitId = $unitId;
        $this->tipSobitiya = $tipSobitiya;
        $this->nextAttemptAt = $nextAttemptAt;
        $this->lastError = $lastError;
    }

    /**
     * Ожидаемая пауза в секундах относительно текущего момента.
     */
    public function zaderzhkaSekund(): int
    {
        if ($this->nextAttemptAt === null) {
            return -1;
        }

        return $this->nextAttemptAt->getTimestamp() - time();
    }
}
