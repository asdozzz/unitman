<?php

declare(strict_types=1);

namespace App\OpenCode\Tests\Business\Utils;

use App\OpenCode\Business\Model\HodOpenCode;
use App\OpenCode\Business\Model\PrivyazkaKUnitu;
use App\OpenCode\Business\Model\RezultatHodaOpenCode;
use App\OpenCode\Business\Model\SessiyaOpenCode;
use App\OpenCode\Business\Model\StatusHoda;
use App\OpenCode\Business\Port\HodOpenCodeRepository;
use App\OpenCode\Business\Port\SessiyaOpenCodeRepository;
use App\OpenCode\Business\Port\UmeetBronirovatUniti;
use App\OpenCode\Business\Port\UmeetPeredavatUvedomleniyaOpenCode;
use App\OpenCode\Business\Port\UmeetVypolnyatHodOpenCode;
use App\OpenCode\Business\Port\UvedomleniyaOpenCodeRepository;
use App\OpenCode\Business\Port\UuidGenerator;
use App\OpenCode\Business\ReadModel\UvedomlenieONovomSoobsheniiOpenCode;
use App\OpenCode\Business\Utils\VladelecZanyatostiOpenCode;
use App\OpenCode\Business\Utils\ZavershenieHodaOpenCode;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Psr\Log\LoggerInterface;

/**
 * Завершение хода: фиксация результата, освобождение юнитов и уведомление.
 *
 * Уведомление создаётся только при переходе из RUNNING в терминальный статус
 * и публикуется только после успешной вставки. Ошибка публикации не отменяет
 * завершение хода.
 */
final class ZavershenieHodaOpenCodeTest extends TestCase
{
    private const TURN_ID = 'turn-1';
    private const SESSION_ID = 'session-1';
    private const WORKFLOW_ID = 'workflow-1';
    private const ACCOUNT_ID = 'account-1';
    private const SESSION_TITLE = 'Моя сессия';

    #[Test]
    public function terminalnii_otvet_sozdaet_i_publikuet_odno_uvedomlenie(): void
    {
        $turnRepository = $this->createMock(HodOpenCodeRepository::class);
        $turnRepository->expects($this->once())->method('update');

        $sessionRepository = $this->createMock(SessiyaOpenCodeRepository::class);
        $sessionRepository->method('getById')->willReturn($this->sessiya());
        $sessionRepository->expects($this->never())->method('update');

        $bronirovanie = $this->createMock(UmeetBronirovatUniti::class);
        $bronirovanie->expects($this->once())
            ->method('osvobodit')
            ->with(
                ['unit-1', 'unit-2'],
                VladelecZanyatostiOpenCode::dlyaHoda(self::TURN_ID),
            );

        $sohraneno = null;

        $notifications = $this->createMock(UvedomleniyaOpenCodeRepository::class);
        $notifications->expects($this->once())
            ->method('save')
            ->willReturnCallback(
                static function (UvedomlenieONovomSoobsheniiOpenCode $notification) use (&$sohraneno): bool {
                    $sohraneno = $notification;

                    return true;
                }
            );

        $publisher = $this->createMock(UmeetPeredavatUvedomleniyaOpenCode::class);
        $publisher->expects($this->once())
            ->method('publish')
            ->with($this->callback(
                static fn (UvedomlenieONovomSoobsheniiOpenCode $notification): bool => $notification->turnId === self::TURN_ID
            ));

        $uuid = $this->createMock(UuidGenerator::class);
        $uuid->expects($this->once())->method('makeGuid')->willReturn('notification-1');

        $logger = $this->createMock(LoggerInterface::class);
        $logger->expects($this->never())->method('error');

        $turn = $this->hod();
        $result = $this->zavershit(
            $turn,
            new RezultatHodaOpenCode(
                true,
                RezultatHodaOpenCode::KIND_ANSWER,
                [],
                'Готово',
                null,
                null,
            ),
            $turnRepository,
            $sessionRepository,
            $bronirovanie,
            $notifications,
            $publisher,
            $uuid,
            $logger,
        );

        self::assertSame(StatusHoda::ANSWERED, $result->getStatus());
        self::assertInstanceOf(UvedomlenieONovomSoobsheniiOpenCode::class, $sohraneno);
        self::assertSame('notification-1', $sohraneno->id);
        self::assertSame(self::ACCOUNT_ID, $sohraneno->accountId);
        self::assertSame(self::SESSION_ID, $sohraneno->sessionId);
        self::assertSame(self::TURN_ID, $sohraneno->turnId);
        self::assertSame(self::SESSION_TITLE, $sohraneno->sessionTitle);
        self::assertSame(StatusHoda::ANSWERED->value, $sohraneno->status);
        self::assertSame(RezultatHodaOpenCode::KIND_ANSWER, $sohraneno->kind);
        self::assertSame('unit', $sohraneno->sessionType);
    }

    #[Test]
    public function povtornii_vyzov_ne_sozdaet_dubl_i_ne_publikuet(): void
    {
        $turnRepository = $this->createMock(HodOpenCodeRepository::class);
        $turnRepository->expects($this->never())->method('update');

        $sessionRepository = $this->createMock(SessiyaOpenCodeRepository::class);
        $sessionRepository->expects($this->never())->method('getById');

        $runner = $this->createMock(UmeetVypolnyatHodOpenCode::class);
        $runner->expects($this->never())->method('poluchitResultatHoda');

        $bronirovanie = $this->createMock(UmeetBronirovatUniti::class);
        $bronirovanie->expects($this->never())->method('osvobodit');

        $notifications = $this->createMock(UvedomleniyaOpenCodeRepository::class);
        $notifications->expects($this->never())->method('save');

        $publisher = $this->createMock(UmeetPeredavatUvedomleniyaOpenCode::class);
        $publisher->expects($this->never())->method('publish');

        $turn = HodOpenCode::restore(
            self::TURN_ID,
            self::SESSION_ID,
            1,
            self::WORKFLOW_ID,
            StatusHoda::ANSWERED->value,
            'Привет',
            [],
            new RezultatHodaOpenCode(
                true,
                RezultatHodaOpenCode::KIND_ANSWER,
                [],
                'Готово',
                null,
                null,
            ),
            null,
            1000,
            1100,
        );

        $zavershenie = new ZavershenieHodaOpenCode(
            $turnRepository,
            $sessionRepository,
            $runner,
            $bronirovanie,
            $notifications,
            $publisher,
            $this->createMock(UuidGenerator::class),
            $this->createMock(LoggerInterface::class),
        );

        self::assertSame($turn, $zavershenie->zavershit($turn));
    }

    #[Test]
    public function oshibka_publikacii_ne_otmenyaet_zavershenie(): void
    {
        $turnRepository = $this->createMock(HodOpenCodeRepository::class);
        $turnRepository->expects($this->once())->method('update');

        $sessionRepository = $this->createMock(SessiyaOpenCodeRepository::class);
        $sessionRepository->method('getById')->willReturn($this->sessiya());

        $bronirovanie = $this->createMock(UmeetBronirovatUniti::class);
        $bronirovanie->expects($this->once())->method('osvobodit');

        $notifications = $this->createMock(UvedomleniyaOpenCodeRepository::class);
        $notifications->expects($this->once())->method('save')->willReturn(true);

        $publisher = $this->createMock(UmeetPeredavatUvedomleniyaOpenCode::class);
        $publisher->expects($this->once())
            ->method('publish')
            ->willThrowException(new \RuntimeException('centrifugo unavailable'));

        $logger = $this->createMock(LoggerInterface::class);
        $logger->expects($this->once())->method('error');

        $result = $this->zavershit(
            $this->hod(),
            new RezultatHodaOpenCode(
                true,
                RezultatHodaOpenCode::KIND_ANSWER,
                [],
                'Готово',
                null,
                null,
            ),
            $turnRepository,
            $sessionRepository,
            $bronirovanie,
            $notifications,
            $publisher,
            $this->createMock(UuidGenerator::class),
            $logger,
        );

        self::assertSame(StatusHoda::ANSWERED, $result->getStatus());
    }

    #[Test]
    public function otsutstvie_rezultata_ne_menyaet_hod_i_ne_sozdaet_uvedomlenie(): void
    {
        $turnRepository = $this->createMock(HodOpenCodeRepository::class);
        $turnRepository->expects($this->never())->method('update');

        $sessionRepository = $this->createMock(SessiyaOpenCodeRepository::class);
        $sessionRepository->expects($this->never())->method('getById');

        $bronirovanie = $this->createMock(UmeetBronirovatUniti::class);
        $bronirovanie->expects($this->never())->method('osvobodit');

        $notifications = $this->createMock(UvedomleniyaOpenCodeRepository::class);
        $notifications->expects($this->never())->method('save');

        $publisher = $this->createMock(UmeetPeredavatUvedomleniyaOpenCode::class);
        $publisher->expects($this->never())->method('publish');

        $turn = $this->hod();

        $result = $this->zavershit(
            $turn,
            null,
            $turnRepository,
            $sessionRepository,
            $bronirovanie,
            $notifications,
            $publisher,
            $this->createMock(UuidGenerator::class),
            $this->createMock(LoggerInterface::class),
        );

        self::assertSame(StatusHoda::RUNNING, $result->getStatus());
    }

    private function zavershit(
        HodOpenCode $turn,
        ?RezultatHodaOpenCode $rezultat,
        HodOpenCodeRepository $turnRepository,
        SessiyaOpenCodeRepository $sessionRepository,
        UmeetBronirovatUniti $bronirovanie,
        UvedomleniyaOpenCodeRepository $notifications,
        UmeetPeredavatUvedomleniyaOpenCode $publisher,
        UuidGenerator $uuid,
        LoggerInterface $logger,
    ): HodOpenCode {
        $runner = $this->createMock(UmeetVypolnyatHodOpenCode::class);
        $runner->method('poluchitResultatHoda')->willReturn($rezultat);

        $zavershenie = new ZavershenieHodaOpenCode(
            $turnRepository,
            $sessionRepository,
            $runner,
            $bronirovanie,
            $notifications,
            $publisher,
            $uuid,
            $logger,
        );

        return $zavershenie->zavershit($turn);
    }

    private function hod(): HodOpenCode
    {
        return HodOpenCode::create(
            self::TURN_ID,
            self::SESSION_ID,
            1,
            self::WORKFLOW_ID,
            'Привет',
            [],
            1000,
        );
    }

    private function sessiya(): SessiyaOpenCode
    {
        return SessiyaOpenCode::create(
            self::SESSION_ID,
            self::ACCOUNT_ID,
            'credential-1',
            self::SESSION_TITLE,
            [
                PrivyazkaKUnitu::izZnacheniy('unit-1', 'project-1'),
                PrivyazkaKUnitu::izZnacheniy('unit-2', 'project-2'),
            ],
            1000,
        );
    }
}
