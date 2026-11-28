<?php

declare(strict_types=1);

namespace App\OpenCode\Tests\Infra\BackgroundJob;

use App\BackgroundJob\Infra\Service\BackgroundJobGroup;
use App\OpenCode\Business\Model\HodOpenCode;
use App\OpenCode\Business\Model\PrivyazkaKUnitu;
use App\OpenCode\Business\Model\RezultatHodaOpenCode;
use App\OpenCode\Business\Model\SessiyaOpenCode;
use App\OpenCode\Business\Port\HodOpenCodeRepository;
use App\OpenCode\Business\Port\SessiyaOpenCodeRepository;
use App\OpenCode\Business\Port\UmeetBronirovatUniti;
use App\OpenCode\Business\Port\UmeetPeredavatUvedomleniyaOpenCode;
use App\OpenCode\Business\Port\UmeetVypolnyatHodOpenCode;
use App\OpenCode\Business\Port\UvedomleniyaOpenCodeRepository;
use App\OpenCode\Business\Port\UuidGenerator;
use App\OpenCode\Business\Utils\ZavershenieHodaOpenCode;
use App\OpenCode\Infra\BackgroundJob\ZavershenieHodovOpenCode\ZavershenieHodovOpenCodeBackgroundJob;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Psr\Log\LoggerInterface;

/**
 * Watchdog завершения ходов: обрабатывает пачку и продолжает после ошибки
 * одного хода. Наружу всегда возвращает true.
 */
final class ZavershenieHodovOpenCodeBackgroundJobTest extends TestCase
{
    #[Test]
    public function parametri_zadachi_sootvetstvuyut_kontraktu(): void
    {
        $job = $this->job(
            $this->createMock(HodOpenCodeRepository::class),
            $this->pustoeZavershenie(),
            $this->createMock(LoggerInterface::class),
        );

        self::assertSame('opencode_turn_watchdog', $job->getName());
        self::assertSame(BackgroundJobGroup::timers, $job->getGroup());
        self::assertSame(5, $job->getDelay());
        self::assertSame(1, $job->getProcessNum());
    }

    #[Test]
    public function obrabativaet_pachku_i_prodolzhaet_posle_oshibki_odnogo_hoda(): void
    {
        $plohoi = $this->hod('turn-bad', 'workflow-bad');
        $horoshii = $this->hod('turn-good', 'workflow-good');

        $turnRepository = $this->createMock(HodOpenCodeRepository::class);
        $turnRepository->expects($this->once())
            ->method('findVProzesse')
            ->with(10)
            ->willReturn([$plohoi, $horoshii]);
        $turnRepository->expects($this->once())->method('update');

        $sessionRepository = $this->createMock(SessiyaOpenCodeRepository::class);
        $sessionRepository->method('getById')->willReturn($this->sessiya());

        $bronirovanie = $this->createMock(UmeetBronirovatUniti::class);
        $bronirovanie->expects($this->once())->method('osvobodit');

        $notifications = $this->createMock(UvedomleniyaOpenCodeRepository::class);
        $notifications->expects($this->once())->method('save')->willReturn(true);

        $publisher = $this->createMock(UmeetPeredavatUvedomleniyaOpenCode::class);
        $publisher->expects($this->once())->method('publish');

        $runner = $this->createMock(UmeetVypolnyatHodOpenCode::class);
        $runner->method('poluchitResultatHoda')
            ->willReturnCallback(
                static function (string $workflowId): ?RezultatHodaOpenCode {
                    if ($workflowId === 'workflow-bad') {
                        throw new \RuntimeException('runner unavailable');
                    }

                    return new RezultatHodaOpenCode(
                        true,
                        RezultatHodaOpenCode::KIND_ANSWER,
                        [],
                        'Готово',
                        null,
                        null,
                    );
                }
            );

        $uuid = $this->createMock(UuidGenerator::class);
        $uuid->method('makeGuid')->willReturn('notification-1');

        $logger = $this->createMock(LoggerInterface::class);
        $logger->expects($this->once())
            ->method('error')
            ->with('opencode.turn_watchdog.turn_failed', $this->callback(
                static fn (array $context): bool => ($context['turnId'] ?? null) === 'turn-bad'
            ));

        $zavershenie = new ZavershenieHodaOpenCode(
            $turnRepository,
            $sessionRepository,
            $runner,
            $bronirovanie,
            $notifications,
            $publisher,
            $uuid,
            $this->createMock(LoggerInterface::class),
            $this->createMock(\App\OpenCode\Business\Port\UchastnikiSessiiRepository::class),
        );

        self::assertTrue($this->job($turnRepository, $zavershenie, $logger)->run());
    }

    #[Test]
    public function oshibka_vyborki_ne_prevrashhaet_zadachu_v_upavshuyu(): void
    {
        $turnRepository = $this->createMock(HodOpenCodeRepository::class);
        $turnRepository->expects($this->once())
            ->method('findVProzesse')
            ->willThrowException(new \RuntimeException('db unavailable'));

        $logger = $this->createMock(LoggerInterface::class);
        $logger->expects($this->once())
            ->method('error')
            ->with('opencode.turn_watchdog.batch_load_failed');

        $job = $this->job(
            $turnRepository,
            $this->pustoeZavershenie(),
            $logger,
        );

        self::assertTrue($job->run());
    }

    private function job(
        HodOpenCodeRepository $turnRepository,
        ZavershenieHodaOpenCode $zavershenie,
        LoggerInterface $logger,
    ): ZavershenieHodovOpenCodeBackgroundJob {
        return new ZavershenieHodovOpenCodeBackgroundJob($turnRepository, $zavershenie, $logger);
    }

    private function pustoeZavershenie(): ZavershenieHodaOpenCode
    {
        return new ZavershenieHodaOpenCode(
            $this->createMock(HodOpenCodeRepository::class),
            $this->createMock(SessiyaOpenCodeRepository::class),
            $this->createMock(UmeetVypolnyatHodOpenCode::class),
            $this->createMock(UmeetBronirovatUniti::class),
            $this->createMock(UvedomleniyaOpenCodeRepository::class),
            $this->createMock(UmeetPeredavatUvedomleniyaOpenCode::class),
            $this->createMock(UuidGenerator::class),
            $this->createMock(LoggerInterface::class),
            $this->createMock(\App\OpenCode\Business\Port\UchastnikiSessiiRepository::class),
        );
    }

    private function hod(string $turnId, string $workflowId): HodOpenCode
    {
        return HodOpenCode::create($turnId, 'session-1', 1, $workflowId, 'Привет', [], 1000);
    }

    private function sessiya(): SessiyaOpenCode
    {
        return SessiyaOpenCode::create(
            'session-1',
            'account-1',
            'credential-1',
            'Сессия',
            [PrivyazkaKUnitu::izZnacheniy('unit-1', 'project-1')],
            1000,
        );
    }
}
