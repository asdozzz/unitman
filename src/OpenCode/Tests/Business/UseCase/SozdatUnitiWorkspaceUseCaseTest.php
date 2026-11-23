<?php

declare(strict_types=1);

namespace App\OpenCode\Tests\Business\UseCase;

use App\OpenCode\Business\Command\SozdatUnitiWorkspace;
use App\OpenCode\Business\Model\SozdanniyUnit;
use App\OpenCode\Business\Model\StatusWorkspace;
use App\OpenCode\Business\Model\WorkspaceUnit;
use App\OpenCode\Business\Port\SessiyaOpenCodeRepository;
use App\OpenCode\Business\Port\UmeetProveryatRekvizityOpenCode;
use App\OpenCode\Business\Port\UmeetUpravlyatUnitami;
use App\OpenCode\Business\UseCase\SozdatUnitiWorkspaceUseCase;
use App\OpenCode\Business\Utils\SlugWorkspace;
use App\OpenCode\Tests\Support\FabrikaWorkspace;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

final class SozdatUnitiWorkspaceUseCaseTest extends TestCase
{
    #[Test]
    public function sozdaet_po_unitu_na_proekt_i_sohranyaet_snimok(): void
    {
        $projects = [
            FabrikaWorkspace::proekt('project-1', 'api', 'services/api', position: 0),
            FabrikaWorkspace::proekt('project-2', 'web', 'services/web', position: 1),
        ];

        $session = FabrikaWorkspace::sozdanie(projects: $projects);
        $workspace = $session->getWorkspace();
        self::assertNotNull($workspace);
        $workspace->perevestiV(StatusWorkspace::RUNNING, FabrikaWorkspace::NOW);

        $repository = $this->createMock(SessiyaOpenCodeRepository::class);
        $repository->expects(self::once())->method('getById')->with('workspace-1')->willReturn($session);
        $repository->expects(self::once())->method('update')->with($session);

        $credentials = $this->createMock(UmeetProveryatRekvizityOpenCode::class);
        $credentials->expects(self::once())->method('tekuschiiAccountId')->willReturn('account-1');

        $unitName = SlugWorkspace::dlya('payments-refactor', 'workspace-1');

        $unitami = $this->createMock(UmeetUpravlyatUnitami::class);
        $unitami->expects(self::once())
            ->method('sozdatUnitiDlyaProektov')
            ->with(
                [
                    ['projectId' => 'project-1', 'branch' => 'feature/payments'],
                    ['projectId' => 'project-2', 'branch' => 'feature/payments'],
                ],
                $unitName,
                'account-1',
            )
            ->willReturn([
                new SozdanniyUnit('unit-1', 'project-1', $unitName, 'feature/payments'),
                new SozdanniyUnit('unit-2', 'project-2', $unitName, 'feature/payments'),
            ]);

        $useCase = new SozdatUnitiWorkspaceUseCase($repository, $credentials, $unitami);
        $result = $useCase->handle(new SozdatUnitiWorkspace('workspace-1'));

        self::assertCount(2, $result);
        self::assertSame('unit-1', $result[0]->unitId);
        self::assertSame('unit-2', $result[1]->unitId);
        self::assertCount(2, $workspace->getUnits());
        self::assertSame('unit-1', $workspace->getUnits()[0]->unitId);
    }

    #[Test]
    public function povtornoe_sozdanie_zapreshcheno_esli_uniti_uge_est(): void
    {
        $session = FabrikaWorkspace::vStatuse(StatusWorkspace::RUNNING);
        $workspace = $session->getWorkspace();
        self::assertNotNull($workspace);
        $workspace->zapomnitUniti([
            new WorkspaceUnit('unit-1', 'project-1', 'payments-refactor', 'feature/payments'),
        ], FabrikaWorkspace::NOW);

        $repository = $this->createMock(SessiyaOpenCodeRepository::class);
        $repository->method('getById')->willReturn($session);
        $repository->expects(self::never())->method('update');

        $credentials = $this->createMock(UmeetProveryatRekvizityOpenCode::class);
        $credentials->method('tekuschiiAccountId')->willReturn('account-1');

        $unitami = $this->createMock(UmeetUpravlyatUnitami::class);
        $unitami->expects(self::never())->method('sozdatUnitiDlyaProektov');

        $useCase = new SozdatUnitiWorkspaceUseCase($repository, $credentials, $unitami);

        $this->expectException(\DomainException::class);
        $this->expectExceptionMessage('opencode.workspace.units_already_created');

        $useCase->handle(new SozdatUnitiWorkspace('workspace-1'));
    }

    #[Test]
    public function trebuet_status_running(): void
    {
        $session = FabrikaWorkspace::vStatuse(StatusWorkspace::CREATING);

        $repository = $this->createMock(SessiyaOpenCodeRepository::class);
        $repository->method('getById')->willReturn($session);
        $repository->expects(self::never())->method('update');

        $credentials = $this->createMock(UmeetProveryatRekvizityOpenCode::class);
        $credentials->method('tekuschiiAccountId')->willReturn('account-1');

        $unitami = $this->createMock(UmeetUpravlyatUnitami::class);
        $unitami->expects(self::never())->method('sozdatUnitiDlyaProektov');

        $useCase = new SozdatUnitiWorkspaceUseCase($repository, $credentials, $unitami);

        $this->expectException(\DomainException::class);
        $this->expectExceptionMessage('opencode.workspace.not_running');

        $useCase->handle(new SozdatUnitiWorkspace('workspace-1'));
    }
}
