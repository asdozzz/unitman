<?php

declare(strict_types=1);

namespace App\OpenCode\Tests\Business\UseCase;

use App\OpenCode\Business\Command\PoluchitResultatHoda;
use App\OpenCode\Business\Model\HodOpenCode;
use App\OpenCode\Business\Model\PrivyazkaKUnitu;
use App\OpenCode\Business\Model\RezultatHodaOpenCode;
use App\OpenCode\Business\Model\SessiyaOpenCode;
use App\OpenCode\Business\Model\StatusHoda;
use App\OpenCode\Business\Port\HodOpenCodeRepository;
use App\OpenCode\Business\Port\SessiyaOpenCodeRepository;
use App\OpenCode\Business\Port\UmeetBronirovatUniti;
use App\OpenCode\Business\Port\UmeetPeredavatUvedomleniyaOpenCode;
use App\OpenCode\Business\Port\UmeetProveryatRekvizityOpenCode;
use App\OpenCode\Business\Port\UmeetVypolnyatHodOpenCode;
use App\OpenCode\Business\Port\UvedomleniyaOpenCodeRepository;
use App\OpenCode\Business\Port\UuidGenerator;
use App\OpenCode\Business\UseCase\PoluchitResultatHodaQuery;
use App\OpenCode\Business\Utils\VladelecZanyatostiOpenCode;
use App\OpenCode\Business\Utils\ZavershenieHodaOpenCode;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Psr\Log\LoggerInterface;

/**
 * Опрос результата хода: фиксация результата и снятие захвата юнитов.
 *
 * Главный инвариант: захват снимается ровно один раз — когда ход стал
 * терминальным (ответ, вопрос или ошибка). Пока результата нет, юнит
 * остаётся занятым, и повторный опрос ничего не пишет в базу.
 *
 * Ошибка освобождения не должна ломать выдачу результата пользователю:
 * занятость истечёт по TTL, а результат хода важнее.
 */
final class PoluchitResultatHodaQueryTest extends TestCase
{
    private const TURN_ID = 'turn-1';
    private const SESSION_ID = 'session-1';
    private const WORKFLOW_ID = 'workflow-1';
    private const ACCOUNT_ID = 'account-1';

    #[Test]
    public function terminalnii_otvet_osvobozhdaet_uniti_i_fiksiruet_rezultat(): void
    {
        $bronirovanie = $this->createMock(UmeetBronirovatUniti::class);
        $bronirovanie->expects($this->once())
            ->method('osvobodit')
            ->with(
                ['unit-1', 'unit-2'],
                VladelecZanyatostiOpenCode::dlyaHoda(self::TURN_ID),
            );

        $turnRepository = $this->createMock(HodOpenCodeRepository::class);
        $turnRepository->method('getById')->willReturn($this->hod());
        $turnRepository->expects($this->once())->method('update');

        $sessionRepository = $this->createMock(SessiyaOpenCodeRepository::class);
        $sessionRepository->method('getById')->willReturn($this->sessiya());
        $sessionRepository->expects($this->never())->method('update');

        $query = $this->query(
            $sessionRepository,
            $turnRepository,
            $bronirovanie,
            new RezultatHodaOpenCode(
                true,
                RezultatHodaOpenCode::KIND_ANSWER,
                [],
                'Готово',
                null,
                null,
            ),
        );

        $readModel = $query->handle(new PoluchitResultatHoda(self::TURN_ID));

        self::assertSame(StatusHoda::ANSWERED->value, $readModel->status);
        self::assertSame('progress-' . self::WORKFLOW_ID, $readModel->progressChannel);
    }

    #[Test]
    public function otsutstvie_rezultata_ne_osvobozhdaet_uniti_i_ne_pishet_v_bazu(): void
    {
        $bronirovanie = $this->createMock(UmeetBronirovatUniti::class);
        $bronirovanie->expects($this->never())->method('osvobodit');

        $turnRepository = $this->createMock(HodOpenCodeRepository::class);
        $turnRepository->method('getById')->willReturn($this->hod());
        $turnRepository->expects($this->never())->method('update');

        $sessionRepository = $this->createMock(SessiyaOpenCodeRepository::class);
        $sessionRepository->method('getById')->willReturn($this->sessiya());
        $sessionRepository->expects($this->never())->method('update');

        $query = $this->query(
            $sessionRepository,
            $turnRepository,
            $bronirovanie,
            null,
        );

        $readModel = $query->handle(new PoluchitResultatHoda(self::TURN_ID));

        self::assertSame(StatusHoda::RUNNING->value, $readModel->status);
    }

    #[Test]
    public function vopros_takzhe_snimaet_zahvat_posle_fiksacii(): void
    {
        $bronirovanie = $this->createMock(UmeetBronirovatUniti::class);
        $bronirovanie->expects($this->once())
            ->method('osvobodit')
            ->with(
                ['unit-1', 'unit-2'],
                VladelecZanyatostiOpenCode::dlyaHoda(self::TURN_ID),
            );

        $turnRepository = $this->createMock(HodOpenCodeRepository::class);
        $turnRepository->method('getById')->willReturn($this->hod());
        $turnRepository->expects($this->once())->method('update');

        $sessionRepository = $this->createMock(SessiyaOpenCodeRepository::class);
        $sessionRepository->method('getById')->willReturn($this->sessiya());

        $query = $this->query(
            $sessionRepository,
            $turnRepository,
            $bronirovanie,
            new RezultatHodaOpenCode(
                true,
                RezultatHodaOpenCode::KIND_QUESTION,
                [['question' => 'Уточните сервис?', 'variants' => []]],
                null,
                null,
                null,
            ),
        );

        $readModel = $query->handle(new PoluchitResultatHoda(self::TURN_ID));

        self::assertSame(StatusHoda::AWAITING_INPUT->value, $readModel->status);
    }

    #[Test]
    public function oshibka_osvobozhdeniya_ne_lomaet_vidachu_rezultata(): void
    {
        $bronirovanie = $this->createMock(UmeetBronirovatUniti::class);
        $bronirovanie->expects($this->once())
            ->method('osvobodit')
            ->willThrowException(new \RuntimeException('unitman unavailable'));

        $turnRepository = $this->createMock(HodOpenCodeRepository::class);
        $turnRepository->method('getById')->willReturn($this->hod());
        $turnRepository->expects($this->once())->method('update');

        $sessionRepository = $this->createMock(SessiyaOpenCodeRepository::class);
        $sessionRepository->method('getById')->willReturn($this->sessiya());

        $query = $this->query(
            $sessionRepository,
            $turnRepository,
            $bronirovanie,
            new RezultatHodaOpenCode(
                true,
                RezultatHodaOpenCode::KIND_ANSWER,
                [],
                'Готово',
                null,
                null,
            ),
        );

        $readModel = $query->handle(new PoluchitResultatHoda(self::TURN_ID));

        self::assertSame(StatusHoda::ANSWERED->value, $readModel->status);
    }

    #[Test]
    public function novaya_vneshnyaya_sessiya_zapominaetsya_na_sessii(): void
    {
        $bronirovanie = $this->createMock(UmeetBronirovatUniti::class);
        $bronirovanie->expects($this->once())->method('osvobodit');

        $turnRepository = $this->createMock(HodOpenCodeRepository::class);
        $turnRepository->method('getById')->willReturn($this->hod());
        $turnRepository->expects($this->once())->method('update');

        $sessionRepository = $this->createMock(SessiyaOpenCodeRepository::class);
        $sessionRepository->method('getById')->willReturn($this->sessiya());
        $sessionRepository->expects($this->once())->method('update');

        $query = $this->query(
            $sessionRepository,
            $turnRepository,
            $bronirovanie,
            new RezultatHodaOpenCode(
                true,
                RezultatHodaOpenCode::KIND_ANSWER,
                [],
                'Готово',
                null,
                'external-session-1',
            ),
        );

        $query->handle(new PoluchitResultatHoda(self::TURN_ID));
    }

    #[Test]
    public function sessiya_bez_unitei_ne_vyzyvaet_osvobozhdenie(): void
    {
        $bronirovanie = $this->createMock(UmeetBronirovatUniti::class);
        $bronirovanie->expects($this->never())->method('osvobodit');

        $turnRepository = $this->createMock(HodOpenCodeRepository::class);
        $turnRepository->method('getById')->willReturn($this->hod());
        $turnRepository->expects($this->once())->method('update');

        $sessionRepository = $this->createMock(SessiyaOpenCodeRepository::class);
        $sessionRepository->method('getById')->willReturn(
            SessiyaOpenCode::create(self::SESSION_ID, self::ACCOUNT_ID, 'credential-1', null, [], 1000),
        );

        $query = $this->query(
            $sessionRepository,
            $turnRepository,
            $bronirovanie,
            new RezultatHodaOpenCode(
                true,
                RezultatHodaOpenCode::KIND_ANSWER,
                [],
                'Готово',
                null,
                null,
            ),
        );

        $readModel = $query->handle(new PoluchitResultatHoda(self::TURN_ID));

        self::assertSame(StatusHoda::ANSWERED->value, $readModel->status);
    }

    private function query(
        SessiyaOpenCodeRepository $sessionRepository,
        HodOpenCodeRepository $turnRepository,
        UmeetBronirovatUniti $bronirovanie,
        ?RezultatHodaOpenCode $rezultat,
    ): PoluchitResultatHodaQuery {
        $credentials = $this->createMock(UmeetProveryatRekvizityOpenCode::class);
        $credentials->method('tekuschiiAccountId')->willReturn(self::ACCOUNT_ID);

        $runner = $this->createMock(UmeetVypolnyatHodOpenCode::class);
        $runner->method('poluchitResultatHoda')->willReturn($rezultat);
        $runner->method('kanalProgressa')
            ->willReturnCallback(
                static fn (string $workflowId): string => 'progress-' . $workflowId,
            );

        $notifications = $this->createMock(UvedomleniyaOpenCodeRepository::class);
        $notifications->method('save')->willReturn(false);

        $publisher = $this->createMock(UmeetPeredavatUvedomleniyaOpenCode::class);
        $uuid = $this->createMock(UuidGenerator::class);
        $uuid->method('makeGuid')->willReturn('notification-1');

        $zavershenie = new ZavershenieHodaOpenCode(
            $turnRepository,
            $sessionRepository,
            $runner,
            $bronirovanie,
            $notifications,
            $publisher,
            $uuid,
            $this->createMock(LoggerInterface::class),
        );

        return new PoluchitResultatHodaQuery(
            $sessionRepository,
            $turnRepository,
            $credentials,
            $runner,
            $zavershenie,
        );
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
            null,
            [
                PrivyazkaKUnitu::izZnacheniy('unit-1', 'project-1'),
                PrivyazkaKUnitu::izZnacheniy('unit-2', 'project-2'),
            ],
            1000,
        );
    }
}
