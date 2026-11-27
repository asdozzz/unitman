<?php

declare(strict_types=1);

namespace App\OpenCode\Tests\Business\UseCase;

use App\OpenCode\Business\Command\DobavitProektyVWorkspace;
use App\OpenCode\Business\Model\StatusWorkspace;
use App\OpenCode\Business\Model\Workspace;
use App\OpenCode\Business\Model\WorkspacePendingUpdate;
use App\OpenCode\Business\Port\SessiyaOpenCodeRepository;
use App\OpenCode\Business\Port\UmeetProveryatRekvizityOpenCode;
use App\OpenCode\Business\Port\UmeetUpravlyatProektamiWorkspace;
use App\OpenCode\Business\UseCase\DobavitProektyVWorkspaceUseCase;
use App\OpenCode\Tests\Support\FabrikaWorkspace;
use App\OpenCode\Tests\Support\ImitaciyaUpravleniyaWorkspace;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

/**
 * Добавление проектов в работающий workspace: проект попадает в снимок
 * только после успешного обновления контейнера.
 */
final class DobavitProektyVWorkspaceUseCaseTest extends TestCase
{
    #[Test]
    public function dobavlyaet_proekt_i_zapuskaet_obnovlenie(): void
    {
        $session = FabrikaWorkspace::vStatuse(StatusWorkspace::RUNNING);

        $repository = $this->createMock(SessiyaOpenCodeRepository::class);
        $repository->method('getById')->with('workspace-1')->willReturn($session);
        $repository->method('findWorkspaceUpdate')->with('workspace-1')->willReturn(null);

        $savedPending = null;
        $repository->expects($this->once())
            ->method('saveWorkspaceUpdate')
            ->willReturnCallback(static function (string $id, string $workflowId, WorkspacePendingUpdate $update) use (&$savedPending): void {
                $savedPending = $update;
            });
        $repository->expects($this->once())->method('update')->with($session);

        $projectami = $this->createMock(UmeetUpravlyatProektamiWorkspace::class);
        $projectami->expects($this->once())
            ->method('poluchitProekti')
            ->with(['project-2'], 'account-1', 'feature/payments', ['project-2' => 'feature/added'])
            ->willReturn([
                FabrikaWorkspace::proekt('project-2', 'web', 'apps/web', true, 0, 'develop'),
            ]);

        $manager = new ImitaciyaUpravleniyaWorkspace();
        $manager->zadatWorkflowObnovleniya('wf-add-project');

        $useCase = new DobavitProektyVWorkspaceUseCase(
            $repository,
            $this->credentials(),
            $projectami,
            $manager,
        );

        $useCase->handle(new DobavitProektyVWorkspace('workspace-1', [
            ['projectId' => 'project-2', 'branch' => 'feature/added'],
        ]));

        $this->assertCount(1, $manager->obnovleniya());
        $pending = $manager->obnovleniya()[0]['update'];
        $this->assertSame(WorkspacePendingUpdate::KIND_PROJECT, $pending->kind);
        $this->assertCount(1, $pending->projects);
        $this->assertSame('project-2', $pending->projects[0]->projectId);
        $this->assertSame(Workspace::OPERATION_UPDATE, $session->getWorkspace()?->getOperation());

        $this->assertSame($pending, $savedPending);
        $this->assertCount(1, $session->getWorkspace()?->getProjects() ?? [], 'проект ещё не должен попасть в снимок');
    }

    #[Test]
    public function nerabochii_workspace_otklonyaetsya(): void
    {
        $session = FabrikaWorkspace::vStatuse(StatusWorkspace::CREATING);

        $repository = $this->createMock(SessiyaOpenCodeRepository::class);
        $repository->method('getById')->willReturn($session);
        $repository->expects($this->never())->method('saveWorkspaceUpdate');

        $projectami = $this->createMock(UmeetUpravlyatProektamiWorkspace::class);
        $projectami->expects($this->never())->method('poluchitProekti');

        $useCase = new DobavitProektyVWorkspaceUseCase(
            $repository,
            $this->credentials(),
            $projectami,
            new ImitaciyaUpravleniyaWorkspace(),
        );

        $this->expectException(\DomainException::class);
        $this->expectExceptionMessage('opencode.workspace.not_running');

        $useCase->handle(new DobavitProektyVWorkspace('workspace-1', [
            ['projectId' => 'project-2', 'branch' => 'feature/added'],
        ]));
    }

    #[Test]
    public function parallelnoe_obnovlenie_otklonyaetsya(): void
    {
        $session = FabrikaWorkspace::vStatuse(StatusWorkspace::RUNNING);

        $repository = $this->createMock(SessiyaOpenCodeRepository::class);
        $repository->method('getById')->willReturn($session);
        $repository->method('findWorkspaceUpdate')->willReturn(['workflow_id' => 'wf-current']);
        $repository->expects($this->never())->method('saveWorkspaceUpdate');

        $useCase = new DobavitProektyVWorkspaceUseCase(
            $repository,
            $this->credentials(),
            $this->createMock(UmeetUpravlyatProektamiWorkspace::class),
            new ImitaciyaUpravleniyaWorkspace(),
        );

        $this->expectException(\DomainException::class);
        $this->expectExceptionMessage('opencode.workspace.update_in_progress');

        $useCase->handle(new DobavitProektyVWorkspace('workspace-1', [
            ['projectId' => 'project-2', 'branch' => 'feature/added'],
        ]));
    }

    #[Test]
    public function uzhe_dobavlennyi_proekt_otklonyaetsya(): void
    {
        $session = FabrikaWorkspace::vStatuse(StatusWorkspace::RUNNING);

        $repository = $this->createMock(SessiyaOpenCodeRepository::class);
        $repository->method('getById')->willReturn($session);
        $repository->method('findWorkspaceUpdate')->willReturn(null);

        $projectami = $this->createMock(UmeetUpravlyatProektamiWorkspace::class);
        $projectami->expects($this->never())->method('poluchitProekti');

        $useCase = new DobavitProektyVWorkspaceUseCase(
            $repository,
            $this->credentials(),
            $projectami,
            new ImitaciyaUpravleniyaWorkspace(),
        );

        $this->expectException(\DomainException::class);
        $this->expectExceptionMessage('opencode.workspace.project_conflict');

        $useCase->handle(new DobavitProektyVWorkspace('workspace-1', [
            ['projectId' => 'project-1', 'branch' => 'feature/added'],
        ]));
    }

    #[Test]
    public function praznaya_vetka_otklonyaetsya(): void
    {
        $session = FabrikaWorkspace::vStatuse(StatusWorkspace::RUNNING);

        $repository = $this->createMock(SessiyaOpenCodeRepository::class);
        $repository->method('getById')->willReturn($session);
        $repository->method('findWorkspaceUpdate')->willReturn(null);

        $useCase = new DobavitProektyVWorkspaceUseCase(
            $repository,
            $this->credentials(),
            $this->createMock(UmeetUpravlyatProektamiWorkspace::class),
            new ImitaciyaUpravleniyaWorkspace(),
        );

        $this->expectException(\DomainException::class);
        $this->expectExceptionMessage('opencode.workspace.branch_required');

        $useCase->handle(new DobavitProektyVWorkspace('workspace-1', [
            ['projectId' => 'project-2', 'branch' => '   '],
        ]));
    }

    private function credentials(): UmeetProveryatRekvizityOpenCode
    {
        $credentials = $this->createMock(UmeetProveryatRekvizityOpenCode::class);
        $credentials->method('tekuschiiAccountId')->willReturn('account-1');

        return $credentials;
    }
}
