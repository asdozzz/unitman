<?php

declare(strict_types=1);

namespace App\OpenCode\Tests\Infra\BackgroundJob;

use App\BackgroundJob\Infra\Service\BackgroundJobGroup;
use App\OpenCode\Business\Model\ProektWorkspace;
use App\OpenCode\Business\Model\SessiyaOpenCode;
use App\OpenCode\Business\Model\StatusWorkspace;
use App\OpenCode\Business\Model\WorkspaceKontekst;
use App\OpenCode\Business\Model\WorkspacePendingUpdate;
use App\OpenCode\Business\Port\HodOpenCodeRepository;
use App\OpenCode\Business\Port\SessiyaOpenCodeRepository;
use App\OpenCode\Business\Port\UmeetBronirovatUniti;
use App\OpenCode\Business\Port\UmeetUpravlyatFaylamiWorkspace;
use App\OpenCode\Business\Port\UmeetUpravlyatWorkspace;
use App\OpenCode\Business\Port\UmeetVypolnyatHodOpenCode;
use App\OpenCode\Business\Port\UuidGenerator;
use App\OpenCode\Business\Utils\ZapuskatelHodaOpenCode;
use App\OpenCode\Infra\BackgroundJob\ObrabotkaWorkspace\ObrabotkaWorkspaceBackgroundJob;
use App\OpenCode\Tests\Support\FabrikaWorkspace;
use App\OpenCode\Tests\Support\ImitaciyaUpravleniyaFaylamiWorkspace;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Psr\Log\LoggerInterface;

final class ObrabotkaWorkspaceBackgroundJobTest extends TestCase
{
    #[Test]
    public function parametri_zadachi_sootvetstvuyut_kontraktu(): void
    {
        $job = $this->job(
            $this->createMock(SessiyaOpenCodeRepository::class),
            $this->createMock(UmeetUpravlyatWorkspace::class),
            $this->launcher(
                $this->createMock(HodOpenCodeRepository::class),
                $this->createMock(SessiyaOpenCodeRepository::class),
                $this->createMock(UmeetVypolnyatHodOpenCode::class),
                $this->createMock(UmeetBronirovatUniti::class),
            ),
            $this->createMock(LoggerInterface::class),
        );

        self::assertSame('opencode_workspace_reconcile', $job->getName());
        self::assertSame(BackgroundJobGroup::timers, $job->getGroup());
        self::assertSame(5, $job->getDelay());
        self::assertSame(1, $job->getProcessNum());
    }

    #[Test]
    public function uspeshnoe_sozdanie_perevodit_v_running_i_zapuskaet_pervyi_prompt(): void
    {
        $creating = FabrikaWorkspace::vStatuse(StatusWorkspace::CREATING);
        $running = FabrikaWorkspace::vStatuse(StatusWorkspace::RUNNING);

        $repository = $this->createMock(SessiyaOpenCodeRepository::class);
        $repository->expects($this->once())->method('findAktivnieWorkspace')->with(50)->willReturn([$creating]);
        $repository->expects($this->once())
            ->method('perevestiStatusWorkspace')
            ->with('workspace-1', [StatusWorkspace::CREATING], StatusWorkspace::RUNNING)
            ->willReturn(true);
        $repository->method('getById')->willReturn($running);
        $repository->expects($this->exactly(2))->method('update');

        $manager = $this->createMock(UmeetUpravlyatWorkspace::class);
        $manager->method('poluchitRezultatOperacii')
            ->willReturn(UmeetUpravlyatWorkspace::RESULT_SUCCESS);

        $turnRepository = $this->createMock(HodOpenCodeRepository::class);
        $turnRepository->method('sleduyushiiSeq')->willReturn(1);
        $turnRepository->method('findBySessionId')->willReturn([]);
        $turnRepository->expects($this->once())->method('save');

        $runner = $this->createMock(UmeetVypolnyatHodOpenCode::class);
        $runner->expects($this->once())
            ->method('nachatHod')
            ->with(
                $this->anything(),
                'account-1',
                'credential-1',
                null,
                [],
                'Первый промпт',
                [],
                [],
                $this->callback(
                    static fn (mixed $context): bool => $context instanceof WorkspaceKontekst
                        && $context->workspaceId === 'workspace-1'
                        && $context->projects[0] instanceof ProektWorkspace
                ),
            )
            ->willReturn('workflow-1');

        $bronirovanie = $this->createMock(UmeetBronirovatUniti::class);
        $bronirovanie->expects($this->never())->method('zahvatit');

        $job = $this->job(
            $repository,
            $manager,
            $this->launcher($turnRepository, $repository, $runner, $bronirovanie),
            $this->createMock(LoggerInterface::class),
        );

        self::assertTrue($job->run());
        self::assertNotNull($running->getWorkspace()?->getInitialPromptStartedAt());
    }

    #[Test]
    public function uspeshnaya_arhivaciya_stavit_archived_i_priznak_arhiva(): void
    {
        $archiving = FabrikaWorkspace::vStatuse(StatusWorkspace::ARCHIVING);
        $archived = FabrikaWorkspace::vStatuse(StatusWorkspace::ARCHIVED);

        $repository = $this->createMock(SessiyaOpenCodeRepository::class);
        $repository->method('findAktivnieWorkspace')->willReturn([$archiving]);
        $repository->expects($this->once())
            ->method('perevestiStatusWorkspace')
            ->with('workspace-1', [StatusWorkspace::ARCHIVING], StatusWorkspace::ARCHIVED)
            ->willReturn(true);
        $repository->method('getById')->willReturn($archived);
        $repository->expects($this->once())->method('update');

        $manager = $this->createMock(UmeetUpravlyatWorkspace::class);
        $manager->method('poluchitRezultatOperacii')
            ->willReturn(UmeetUpravlyatWorkspace::RESULT_SUCCESS);

        $job = $this->job(
            $repository,
            $manager,
            $this->launcher(
                $this->createMock(HodOpenCodeRepository::class),
                $repository,
                $this->createMock(UmeetVypolnyatHodOpenCode::class),
                $this->createMock(UmeetBronirovatUniti::class),
            ),
            $this->createMock(LoggerInterface::class),
        );

        self::assertTrue($job->run());
        self::assertTrue($archived->estArhiv());
    }

    #[Test]
    public function uspeshnoe_udalenie_udalyayet_zapisi_bd(): void
    {
        $deleting = FabrikaWorkspace::vStatuse(StatusWorkspace::DELETING);

        $repository = $this->createMock(SessiyaOpenCodeRepository::class);
        $repository->method('findAktivnieWorkspace')->willReturn([$deleting]);
        $repository->expects($this->once())->method('deleteWorkspace')->with('workspace-1');

        $manager = $this->createMock(UmeetUpravlyatWorkspace::class);
        $manager->method('poluchitRezultatOperacii')
            ->willReturn(UmeetUpravlyatWorkspace::RESULT_SUCCESS);

        $job = $this->job(
            $repository,
            $manager,
            $this->launcher(
                $this->createMock(HodOpenCodeRepository::class),
                $repository,
                $this->createMock(UmeetVypolnyatHodOpenCode::class),
                $this->createMock(UmeetBronirovatUniti::class),
            ),
            $this->createMock(LoggerInterface::class),
        );

        self::assertTrue($job->run());
    }

    #[Test]
    public function udalenie_workspace_snachala_udalyayet_artefakty(): void
    {
        $deleting = FabrikaWorkspace::vStatuse(StatusWorkspace::DELETING);
        $porjadok = [];

        $fayly = $this->createMock(UmeetUpravlyatFaylamiWorkspace::class);
        $fayly->expects($this->once())
            ->method('udalitFajlyWorkspace')
            ->with('workspace-1')
            ->willReturnCallback(static function () use (&$porjadok): void {
                $porjadok[] = 'artifacts';
            });

        $repository = $this->createMock(SessiyaOpenCodeRepository::class);
        $repository->method('findAktivnieWorkspace')->willReturn([$deleting]);
        $repository->expects($this->once())
            ->method('deleteWorkspace')
            ->with('workspace-1')
            ->willReturnCallback(static function () use (&$porjadok): void {
                $porjadok[] = 'session';
            });

        $manager = $this->createMock(UmeetUpravlyatWorkspace::class);
        $manager->method('poluchitRezultatOperacii')
            ->willReturn(UmeetUpravlyatWorkspace::RESULT_SUCCESS);

        $job = $this->job(
            $repository,
            $manager,
            $this->launcher(
                $this->createMock(HodOpenCodeRepository::class),
                $repository,
                $this->createMock(UmeetVypolnyatHodOpenCode::class),
                $this->createMock(UmeetBronirovatUniti::class),
            ),
            $this->createMock(LoggerInterface::class),
            $fayly,
        );

        self::assertTrue($job->run());
        self::assertSame(['artifacts', 'session'], $porjadok);
    }

    #[Test]
    public function oshibka_udaleniya_artefaktov_ne_blokiruet_udalenie_sessii(): void
    {
        $deleting = FabrikaWorkspace::vStatuse(StatusWorkspace::DELETING);

        $fayly = $this->createMock(UmeetUpravlyatFaylamiWorkspace::class);
        $fayly->expects($this->once())
            ->method('udalitFajlyWorkspace')
            ->with('workspace-1')
            ->willThrowException(new \RuntimeException('storage down'));

        $repository = $this->createMock(SessiyaOpenCodeRepository::class);
        $repository->method('findAktivnieWorkspace')->willReturn([$deleting]);
        $repository->expects($this->once())->method('deleteWorkspace')->with('workspace-1');

        $logger = $this->createMock(LoggerInterface::class);
        $logger->expects($this->once())
            ->method('error')
            ->with(
                'opencode.workspace.files_delete_failed',
                $this->callback(static fn (array $context): bool => $context['workspaceId'] === 'workspace-1'
                    && $context['exception'] instanceof \RuntimeException),
            );

        $manager = $this->createMock(UmeetUpravlyatWorkspace::class);
        $manager->method('poluchitRezultatOperacii')
            ->willReturn(UmeetUpravlyatWorkspace::RESULT_SUCCESS);

        $job = $this->job(
            $repository,
            $manager,
            $this->launcher(
                $this->createMock(HodOpenCodeRepository::class),
                $repository,
                $this->createMock(UmeetVypolnyatHodOpenCode::class),
                $this->createMock(UmeetBronirovatUniti::class),
            ),
            $logger,
            $fayly,
        );

        self::assertTrue($job->run());
    }

    #[Test]
    public function oshibka_operacii_perevodit_v_error(): void
    {
        $creating = FabrikaWorkspace::vStatuse(StatusWorkspace::CREATING);
        $error = FabrikaWorkspace::vStatuse(StatusWorkspace::ERROR);

        $repository = $this->createMock(SessiyaOpenCodeRepository::class);
        $repository->method('findAktivnieWorkspace')->willReturn([$creating]);
        $repository->expects($this->once())
            ->method('perevestiStatusWorkspace')
            ->with('workspace-1', [StatusWorkspace::CREATING], StatusWorkspace::ERROR)
            ->willReturn(true);
        $repository->method('getById')->willReturn($error);
        $repository->expects($this->once())->method('update');

        $manager = $this->createMock(UmeetUpravlyatWorkspace::class);
        $manager->method('poluchitRezultatOperacii')
            ->willReturn(UmeetUpravlyatWorkspace::RESULT_FAILURE);

        $job = $this->job(
            $repository,
            $manager,
            $this->launcher(
                $this->createMock(HodOpenCodeRepository::class),
                $repository,
                $this->createMock(UmeetVypolnyatHodOpenCode::class),
                $this->createMock(UmeetBronirovatUniti::class),
            ),
            $this->createMock(LoggerInterface::class),
        );

        self::assertTrue($job->run());
        self::assertSame('opencode.workspace.operation_failed', $error->getWorkspace()?->getErrorKey());
    }

    #[Test]
    public function uspeshnoe_obnovlenie_dobavlyaet_proekty_i_otmechaet_fayly(): void
    {
        $running = FabrikaWorkspace::vStatuse(StatusWorkspace::RUNNING);
        $running->getWorkspace()?->otmetitStartPervogoPrompta(time());

        $pending = new WorkspacePendingUpdate(
            WorkspacePendingUpdate::KIND_PROJECT_AND_FILES,
            [FabrikaWorkspace::proekt('project-2', 'web', 'apps/web', true, 1, 'develop')],
            [['fileId' => 'file-1', 'name' => 'notes.txt']],
            [],
            false,
        );

        $repository = $this->createMock(SessiyaOpenCodeRepository::class);
        $repository->method('findAktivnieWorkspace')->willReturn([$running]);
        $repository->method('findWorkspaceUpdate')
            ->with('workspace-1')
            ->willReturn(['workflowId' => 'wf-update', 'update' => $pending]);
        $repository->expects($this->once())->method('update');
        $repository->expects($this->once())->method('deleteWorkspaceUpdate')->with('workspace-1');

        $manager = $this->createMock(UmeetUpravlyatWorkspace::class);
        $manager->method('poluchitRezultatOperacii')
            ->willReturn(UmeetUpravlyatWorkspace::RESULT_SUCCESS);

        $fayly = new ImitaciyaUpravleniyaFaylamiWorkspace();

        $job = $this->job(
            $repository,
            $manager,
            $this->launcher(
                $this->createMock(HodOpenCodeRepository::class),
                $repository,
                $this->createMock(UmeetVypolnyatHodOpenCode::class),
                $this->createMock(UmeetBronirovatUniti::class),
            ),
            $this->createMock(LoggerInterface::class),
            $fayly,
        );

        self::assertTrue($job->run());

        $workspace = $running->getWorkspace();
        self::assertNotNull($workspace);
        self::assertSame(
            ['project-1', 'project-2'],
            array_map(static fn (ProektWorkspace $project): string => $project->projectId, $workspace->getProjects()),
        );
        self::assertSame('', $workspace->getOperation());
        self::assertNull($workspace->getErrorKey());
        self::assertSame(['file-1'], $fayly->primenennye());
    }

    #[Test]
    public function oshibka_obnovleniya_otkatyvaet_fayly_i_ostavlyaet_running(): void
    {
        $running = FabrikaWorkspace::vStatuse(StatusWorkspace::RUNNING);
        $running->getWorkspace()?->otmetitStartPervogoPrompta(time());

        $pending = new WorkspacePendingUpdate(
            WorkspacePendingUpdate::KIND_FILES,
            [],
            [['fileId' => 'file-1', 'name' => 'notes.txt']],
            [],
            false,
        );

        $repository = $this->createMock(SessiyaOpenCodeRepository::class);
        $repository->method('findAktivnieWorkspace')->willReturn([$running]);
        $repository->method('findWorkspaceUpdate')
            ->with('workspace-1')
            ->willReturn(['workflowId' => 'wf-update', 'update' => $pending]);
        $repository->expects($this->once())->method('update');
        $repository->expects($this->once())->method('deleteWorkspaceUpdate')->with('workspace-1');

        $manager = $this->createMock(UmeetUpravlyatWorkspace::class);
        $manager->method('poluchitRezultatOperacii')
            ->willReturn(UmeetUpravlyatWorkspace::RESULT_FAILURE);

        $fayly = new ImitaciyaUpravleniyaFaylamiWorkspace();

        $job = $this->job(
            $repository,
            $manager,
            $this->launcher(
                $this->createMock(HodOpenCodeRepository::class),
                $repository,
                $this->createMock(UmeetVypolnyatHodOpenCode::class),
                $this->createMock(UmeetBronirovatUniti::class),
            ),
            $this->createMock(LoggerInterface::class),
            $fayly,
        );

        self::assertTrue($job->run());

        $workspace = $running->getWorkspace();
        self::assertNotNull($workspace);
        self::assertSame(StatusWorkspace::RUNNING, $workspace->getStatus());
        self::assertSame('opencode.workspace.update_failed', $workspace->getErrorKey());
        self::assertSame('', $workspace->getOperation());
        self::assertSame(['file-1'], $fayly->udalennye());
        self::assertSame([], $fayly->primenennye());
    }

    #[Test]
    public function flagi_off_ispolzuyut_legasi_repozitorii(): void
    {
        $repository = $this->createMock(SessiyaOpenCodeRepository::class);
        $repository->expects($this->once())->method('findAktivnieWorkspace')->with(50)->willReturn([]);

        $workspaces = $this->createMock(\App\OpenCode\Business\Workspace\Port\WorkspaceSessionRepository::class);
        $workspaces->expects($this->never())->method('findAktivnieVTranzite');

        $job = new ObrabotkaWorkspaceBackgroundJob(
            $repository,
            $this->createMock(UmeetUpravlyatWorkspace::class),
            $this->launcher(
                $this->createMock(HodOpenCodeRepository::class),
                $repository,
                $this->createMock(UmeetVypolnyatHodOpenCode::class),
                $this->createMock(UmeetBronirovatUniti::class),
            ),
            $this->createMock(LoggerInterface::class),
            null,
            $workspaces,
            new \App\OpenCode\Business\Workspace\Model\WorkspaceFeatureFlags(read: false, write: false, mirror: false),
        );

        self::assertTrue($job->run());
    }

    #[Test]
    public function pri_write_bez_zerkala_tranzitnye_statusy_dovodyatsya_na_novom_agregate(): void
    {
        $creating = FabrikaWorkspace::workspaceSessionVStatuse(StatusWorkspace::CREATING);
        $running = FabrikaWorkspace::workspaceSessionVStatuse(StatusWorkspace::RUNNING);

        $legacyRepository = $this->createMock(SessiyaOpenCodeRepository::class);
        $legacyRepository->expects($this->never())->method('findAktivnieWorkspace');

        $workspaces = $this->createMock(\App\OpenCode\Business\Workspace\Port\WorkspaceSessionRepository::class);
        $workspaces->expects($this->once())->method('findAktivnieVTranzite')->with(50)->willReturn([$creating]);
        $workspaces->expects($this->once())
            ->method('perevestiStatus')
            ->with('workspace-1', [StatusWorkspace::CREATING], StatusWorkspace::RUNNING)
            ->willReturn(true);
        $workspaces->expects($this->once())->method('getById')->with('workspace-1')->willReturn($running);

        $manager = $this->createMock(UmeetUpravlyatWorkspace::class);
        $manager->method('poluchitRezultatOperacii')
            ->willReturn(UmeetUpravlyatWorkspace::RESULT_SUCCESS);

        $job = new ObrabotkaWorkspaceBackgroundJob(
            $legacyRepository,
            $manager,
            $this->launcher(
                $this->createMock(HodOpenCodeRepository::class),
                $legacyRepository,
                $this->createMock(UmeetVypolnyatHodOpenCode::class),
                $this->createMock(UmeetBronirovatUniti::class),
            ),
            $this->createMock(LoggerInterface::class),
            null,
            $workspaces,
            new \App\OpenCode\Business\Workspace\Model\WorkspaceFeatureFlags(read: true, write: true, mirror: false),
        );

        self::assertTrue($job->run());
    }

    #[Test]
    public function pri_write_bez_zerkala_udalenie_udalyayet_noviy_agregat(): void
    {
        $deleting = FabrikaWorkspace::workspaceSessionVStatuse(StatusWorkspace::DELETING);

        $legacyRepository = $this->createMock(SessiyaOpenCodeRepository::class);
        $legacyRepository->expects($this->never())->method('findAktivnieWorkspace');

        $workspaces = $this->createMock(\App\OpenCode\Business\Workspace\Port\WorkspaceSessionRepository::class);
        $workspaces->method('findAktivnieVTranzite')->willReturn([$deleting]);
        $workspaces->expects($this->once())->method('delete')->with('workspace-1');

        $manager = $this->createMock(UmeetUpravlyatWorkspace::class);
        $manager->method('poluchitRezultatOperacii')
            ->willReturn(UmeetUpravlyatWorkspace::RESULT_SUCCESS);

        $job = new ObrabotkaWorkspaceBackgroundJob(
            $legacyRepository,
            $manager,
            $this->launcher(
                $this->createMock(HodOpenCodeRepository::class),
                $legacyRepository,
                $this->createMock(UmeetVypolnyatHodOpenCode::class),
                $this->createMock(UmeetBronirovatUniti::class),
            ),
            $this->createMock(LoggerInterface::class),
            null,
            $workspaces,
            new \App\OpenCode\Business\Workspace\Model\WorkspaceFeatureFlags(read: true, write: true, mirror: false),
        );

        self::assertTrue($job->run());
    }

    private function job(
        SessiyaOpenCodeRepository $repository,
        UmeetUpravlyatWorkspace $manager,
        ZapuskatelHodaOpenCode $launcher,
        LoggerInterface $logger,
        ?UmeetUpravlyatFaylamiWorkspace $fayly = null,
    ): ObrabotkaWorkspaceBackgroundJob {
        return new ObrabotkaWorkspaceBackgroundJob($repository, $manager, $launcher, $logger, $fayly);
    }

    private function launcher(
        HodOpenCodeRepository $turnRepository,
        SessiyaOpenCodeRepository $sessionRepository,
        UmeetVypolnyatHodOpenCode $runner,
        UmeetBronirovatUniti $bronirovanie,
    ): ZapuskatelHodaOpenCode {
        $uuid = $this->createMock(UuidGenerator::class);
        $uuid->method('makeGuid')->willReturnOnConsecutiveCalls('turn-1', 'workflow-1');

        $credentials = $this->createMock(\App\OpenCode\Business\Port\UmeetProveryatRekvizityOpenCode::class);
        $credentials->method('razreshitModel')->willReturn('default-model');

        $projectami = $this->createMock(\App\OpenCode\Business\Port\UmeetUpravlyatProektamiWorkspace::class);
        $projectami->method('estDostupKProektam')->willReturn(true);

        return new ZapuskatelHodaOpenCode(
            $turnRepository,
            $sessionRepository,
            $runner,
            $uuid,
            $bronirovanie,
            $this->createMock(\App\OpenCode\Business\Port\UmeetUpravlyatUnitami::class),
            $this->createMock(\App\OpenCode\Business\Port\UmeetPoluchatTokenRedaktirovaniyaUnita::class),
            new \App\OpenCode\Business\Utils\OpredelitelRezhimaHodaOpenCode(),
            new \App\OpenCode\Business\Utils\OpredelitelModeliHodaOpenCode($credentials),
            $projectami,
        );
    }
}
