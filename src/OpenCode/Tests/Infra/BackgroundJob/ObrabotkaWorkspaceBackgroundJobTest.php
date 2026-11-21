<?php

declare(strict_types=1);

namespace App\OpenCode\Tests\Infra\BackgroundJob;

use App\BackgroundJob\Infra\Service\BackgroundJobGroup;
use App\OpenCode\Business\Model\ProektWorkspace;
use App\OpenCode\Business\Model\SessiyaOpenCode;
use App\OpenCode\Business\Model\StatusWorkspace;
use App\OpenCode\Business\Model\WorkspaceKontekst;
use App\OpenCode\Business\Port\HodOpenCodeRepository;
use App\OpenCode\Business\Port\SessiyaOpenCodeRepository;
use App\OpenCode\Business\Port\UmeetBronirovatUniti;
use App\OpenCode\Business\Port\UmeetUpravlyatWorkspace;
use App\OpenCode\Business\Port\UmeetVypolnyatHodOpenCode;
use App\OpenCode\Business\Port\UuidGenerator;
use App\OpenCode\Business\Utils\ZapuskatelHodaOpenCode;
use App\OpenCode\Infra\BackgroundJob\ObrabotkaWorkspace\ObrabotkaWorkspaceBackgroundJob;
use App\OpenCode\Tests\Support\FabrikaWorkspace;
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

    private function job(
        SessiyaOpenCodeRepository $repository,
        UmeetUpravlyatWorkspace $manager,
        ZapuskatelHodaOpenCode $launcher,
        LoggerInterface $logger,
    ): ObrabotkaWorkspaceBackgroundJob {
        return new ObrabotkaWorkspaceBackgroundJob($repository, $manager, $launcher, $logger);
    }

    private function launcher(
        HodOpenCodeRepository $turnRepository,
        SessiyaOpenCodeRepository $sessionRepository,
        UmeetVypolnyatHodOpenCode $runner,
        UmeetBronirovatUniti $bronirovanie,
    ): ZapuskatelHodaOpenCode {
        $uuid = $this->createMock(UuidGenerator::class);
        $uuid->method('makeGuid')->willReturnOnConsecutiveCalls('turn-1', 'workflow-1');

        return new ZapuskatelHodaOpenCode(
            $turnRepository,
            $sessionRepository,
            $runner,
            $uuid,
            $bronirovanie,
            $this->createMock(\App\OpenCode\Business\Port\UmeetUpravlyatUnitami::class),
            $this->createMock(\App\OpenCode\Business\Port\UmeetPoluchatTokenRedaktirovaniyaUnita::class),
            new \App\OpenCode\Business\Utils\OpredelitelRezhimaHodaOpenCode(),
        );
    }
}
