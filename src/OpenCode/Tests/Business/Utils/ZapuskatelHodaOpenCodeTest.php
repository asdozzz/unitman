<?php

declare(strict_types=1);

namespace App\OpenCode\Tests\Business\Utils;

use App\OpenCode\Business\Model\PrivyazkaKUnitu;
use App\OpenCode\Business\Model\SessiyaOpenCode;
use App\OpenCode\Business\Model\StatusWorkspace;
use App\OpenCode\Business\Model\WorkspaceKontekst;
use App\OpenCode\Business\Port\HodOpenCodeRepository;
use App\OpenCode\Business\Port\SessiyaOpenCodeRepository;
use App\OpenCode\Business\Port\UmeetBronirovatUniti;
use App\OpenCode\Business\Port\UmeetVypolnyatHodOpenCode;
use App\OpenCode\Business\Port\UuidGenerator;
use App\OpenCode\Business\Utils\VladelecZanyatostiOpenCode;
use App\OpenCode\Business\Utils\ZapuskatelHodaOpenCode;
use App\OpenCode\Tests\Support\FabrikaWorkspace;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

/**
 * Захват юнитов при старте хода и освобождение при отказе.
 *
 * Главный инвариант: если раннер не принял ход, юнит не должен остаться
 * занятым до истечения TTL. Обратная ошибка (освободить чужой захват) тоже
 * недопустима — освобождаем только то, что захватили сами.
 */
final class ZapuskatelHodaOpenCodeTest extends TestCase
{
    private const TURN_ID = 'turn-1';
    private const WORKFLOW_ID = 'workflow-1';

    #[Test]
    public function yuniti_zahvatyvayutsya_do_starta_hoda(): void
    {
        $bronirovanie = $this->createMock(UmeetBronirovatUniti::class);
        $bronirovanie->expects($this->once())
            ->method('zahvatit')
            ->with(['unit-1', 'unit-2'], VladelecZanyatostiOpenCode::dlyaHoda(self::TURN_ID));
        $bronirovanie->expects($this->never())->method('osvobodit');

        $turnRepository = $this->createMock(HodOpenCodeRepository::class);
        $turnRepository->method('sleduyushiiSeq')->willReturn(1);
        $turnRepository->method('findBySessionId')->willReturn([]);
        $turnRepository->expects($this->once())->method('save');

        $sessionRepository = $this->createMock(SessiyaOpenCodeRepository::class);
        $sessionRepository->expects($this->once())->method('update');

        $zapuskatel = new ZapuskatelHodaOpenCode(
            $turnRepository,
            $sessionRepository,
            $this->createMock(UmeetVypolnyatHodOpenCode::class),
            $this->uuidGenerator(),
            $bronirovanie,
        );

        $zapuskatel->zapustit($this->sessiya(), 'Привет', [], $this->privyazki());
    }

    #[Test]
    public function otkaz_rannera_osvobozhdaet_zahvat(): void
    {
        $bronirovanie = $this->createMock(UmeetBronirovatUniti::class);
        $bronirovanie->expects($this->once())->method('zahvatit');
        $bronirovanie->expects($this->once())
            ->method('osvobodit')
            ->with(['unit-1', 'unit-2'], VladelecZanyatostiOpenCode::dlyaHoda(self::TURN_ID));

        $runner = $this->createMock(UmeetVypolnyatHodOpenCode::class);
        $runner->expects($this->once())
            ->method('nachatHod')
            ->willThrowException(new \RuntimeException('temporal unavailable'));

        $turnRepository = $this->createMock(HodOpenCodeRepository::class);
        $turnRepository->method('sleduyushiiSeq')->willReturn(1);
        $turnRepository->method('findBySessionId')->willReturn([]);
        $turnRepository->expects($this->never())->method('save');

        $sessionRepository = $this->createMock(SessiyaOpenCodeRepository::class);
        $sessionRepository->expects($this->never())->method('update');

        $zapuskatel = new ZapuskatelHodaOpenCode(
            $turnRepository,
            $sessionRepository,
            $runner,
            $this->uuidGenerator(),
            $bronirovanie,
        );

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('temporal unavailable');

        $zapuskatel->zapustit($this->sessiya(), 'Привет', [], $this->privyazki());
    }

    #[Test]
    public function otkaz_samogo_zahvata_ne_privodit_k_osvobozhdeniyu(): void
    {
        $bronirovanie = $this->createMock(UmeetBronirovatUniti::class);
        $bronirovanie->expects($this->once())
            ->method('zahvatit')
            ->willThrowException(new \DomainException('unit.zanyat'));
        $bronirovanie->expects($this->never())->method('osvobodit');

        $runner = $this->createMock(UmeetVypolnyatHodOpenCode::class);
        $runner->expects($this->never())->method('nachatHod');

        $turnRepository = $this->createMock(HodOpenCodeRepository::class);
        $turnRepository->method('sleduyushiiSeq')->willReturn(1);
        $turnRepository->method('findBySessionId')->willReturn([]);

        $zapuskatel = new ZapuskatelHodaOpenCode(
            $turnRepository,
            $this->createMock(SessiyaOpenCodeRepository::class),
            $runner,
            $this->uuidGenerator(),
            $bronirovanie,
        );

        $this->expectException(\DomainException::class);
        $this->expectExceptionMessage('unit.zanyat');

        $zapuskatel->zapustit($this->sessiya(), 'Привет', [], $this->privyazki());
    }

    #[Test]
    public function sessiya_bez_unitei_ne_zahvatyvaet_nichego(): void
    {
        $bronirovanie = $this->createMock(UmeetBronirovatUniti::class);
        $bronirovanie->expects($this->never())->method('zahvatit');
        $bronirovanie->expects($this->never())->method('osvobodit');

        $turnRepository = $this->createMock(HodOpenCodeRepository::class);
        $turnRepository->method('sleduyushiiSeq')->willReturn(1);
        $turnRepository->method('findBySessionId')->willReturn([]);
        $turnRepository->expects($this->once())->method('save');

        $sessionRepository = $this->createMock(SessiyaOpenCodeRepository::class);
        $sessionRepository->expects($this->once())->method('update');

        $zapuskatel = new ZapuskatelHodaOpenCode(
            $turnRepository,
            $sessionRepository,
            $this->createMock(UmeetVypolnyatHodOpenCode::class),
            $this->uuidGenerator(),
            $bronirovanie,
        );

        $sessiya = SessiyaOpenCode::create('session-1', 'account-1', 'credential-1', null, [], 1000);

        $zapuskatel->zapustit($sessiya, 'Привет', [], []);
    }

    #[Test]
    public function workspace_ne_zahvatyvaet_unity_i_peredayot_kontekst(): void
    {
        $bronirovanie = $this->createMock(UmeetBronirovatUniti::class);
        $bronirovanie->expects($this->never())->method('zahvatit');
        $bronirovanie->expects($this->never())->method('osvobodit');

        $turnRepository = $this->createMock(HodOpenCodeRepository::class);
        $turnRepository->method('sleduyushiiSeq')->willReturn(1);
        $turnRepository->method('findBySessionId')->willReturn([]);
        $turnRepository->expects($this->once())->method('save');

        $sessionRepository = $this->createMock(SessiyaOpenCodeRepository::class);
        $sessionRepository->expects($this->once())->method('update');

        $zahvachennyiKontekst = null;

        $runner = $this->createMock(UmeetVypolnyatHodOpenCode::class);
        $runner->expects($this->once())
            ->method('nachatHod')
            ->willReturnCallback(
                static function (
                    string $workflowId,
                    string $accountId,
                    string $credentialId,
                    ?string $sessionId,
                    array $units,
                    string $prompt,
                    array $answers,
                    array $history,
                    ?WorkspaceKontekst $workspace = null,
                ) use (&$zahvachennyiKontekst): string {
                    $zahvachennyiKontekst = $workspace;

                    return $workflowId;
                }
            );

        $zapuskatel = new ZapuskatelHodaOpenCode(
            $turnRepository,
            $sessionRepository,
            $runner,
            $this->uuidGenerator(),
            $bronirovanie,
        );

        $sessiya = FabrikaWorkspace::vStatuse(StatusWorkspace::RUNNING);

        $zapuskatel->zapustit($sessiya, 'Первый промпт', [], []);

        $this->assertInstanceOf(WorkspaceKontekst::class, $zahvachennyiKontekst);
        $this->assertSame('workspace-1', $zahvachennyiKontekst->workspaceId);
        $this->assertFalse($zahvachennyiKontekst->readOnly);
        $this->assertSame(['project-1'], array_map(
            static fn ($project): string => $project->projectId,
            $zahvachennyiKontekst->projects,
        ));
        $this->assertSame('payments-refactor', $sessiya->getTitle(), 'Название workspace не подменяется промптом');
    }

    private function uuidGenerator(): UuidGenerator
    {
        $generator = $this->createMock(UuidGenerator::class);
        $generator->method('makeGuid')
            ->willReturnOnConsecutiveCalls(self::TURN_ID, self::WORKFLOW_ID);

        return $generator;
    }

    /**
     * @return list<PrivyazkaKUnitu>
     */
    private function privyazki(): array
    {
        return [
            PrivyazkaKUnitu::izZnacheniy('unit-1', 'project-1'),
            PrivyazkaKUnitu::izZnacheniy('unit-2', 'project-2'),
        ];
    }

    private function sessiya(): SessiyaOpenCode
    {
        return SessiyaOpenCode::create(
            'session-1',
            'account-1',
            'credential-1',
            null,
            $this->privyazki(),
            1000,
        );
    }
}
