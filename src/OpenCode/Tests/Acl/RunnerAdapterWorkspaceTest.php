<?php

declare(strict_types=1);

namespace App\OpenCode\Tests\Acl;

use App\OpenCode\Acl\RunnerAdapter;
use App\OpenCode\Business\Model\ProektWorkspace;
use App\OpenCode\Business\Model\StatusWorkspace;
use App\OpenCode\Business\Model\Workspace;
use App\OpenCode\Business\Model\WorkspaceKontekst;
use App\OpenCode\Business\Port\UuidGenerator;
use App\OpenCode\Tests\Support\FabrikaWorkspace;
use App\Runner\Api\RunnerApiInterface;
use App\Runner\Business\Command\NachatHodOpenCode;
use App\Runner\Business\Command\Workspace\ZapustitWorkspaceZapros;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

final class RunnerAdapterWorkspaceTest extends TestCase
{
    #[Test]
    public function zapusk_peredayot_slug_i_ne_soderzhit_token(): void
    {
        $session = FabrikaWorkspace::vStatuse(StatusWorkspace::RUNNING);

        $runnerApi = $this->createMock(RunnerApiInterface::class);
        $runnerApi->expects($this->once())
            ->method('zapustitWorkspace')
            ->with('wf-1', $this->callback(
                static function (ZapustitWorkspaceZapros $command): bool {
                    self::assertSame('workspace-1', $command->WorkspaceId);
                    self::assertSame('payments-refactor', $command->WorkspaceName);
                    self::assertSame('payments-refactor-workspac', $command->WorkspaceSlug);
                    self::assertSame('feature/payments', $command->Branch);
                    self::assertFalse($command->ReadOnly);
                    self::assertSame(3072, $command->MemoryLimit);
                    self::assertCount(1, $command->Projects);
                    self::assertSame('project-1', $command->Projects[0]->ProjectId);
                    self::assertTrue($command->Projects[0]->CanPush);

                    return true;
                }
            ))
            ->willReturn('wf-1');

        $uuid = $this->createMock(UuidGenerator::class);
        $uuid->method('makeGuid')->willReturn('wf-1');

        $adapter = new RunnerAdapter($runnerApi, $uuid);
        $adapter->zapustit($session);

        $workspace = $session->getWorkspace();
        self::assertSame(Workspace::OPERATION_CREATE, $workspace?->getOperation());
        self::assertSame('wf-1', $workspace?->getOperationWorkflowId());
    }

    #[Test]
    public function hod_workspace_peredayot_kontekst_a_ne_unity(): void
    {
        $session = FabrikaWorkspace::vStatuse(StatusWorkspace::RUNNING);
        $workspace = $session->getWorkspace();
        \assert($workspace !== null);

        $kontekst = new WorkspaceKontekst(
            $session->getId(),
            $workspace->isReadOnly(),
            $workspace->getProjects(),
        );

        $zapros = null;

        $runnerApi = $this->createMock(RunnerApiInterface::class);
        $runnerApi->expects($this->once())
            ->method('nachatHodOpenCode')
            ->willReturnCallback(
                static function (string $workflowId, NachatHodOpenCode $command) use (&$zapros): string {
                    $zapros = $command;

                    return $workflowId;
                }
            );

        $adapter = new RunnerAdapter($runnerApi, $this->createMock(UuidGenerator::class));

        $adapter->nachatHod(
            'wf-turn',
            'account-1',
            'credential-1',
            null,
            [],
            'Промпт',
            [],
            [],
            $kontekst,
        );

        self::assertInstanceOf(NachatHodOpenCode::class, $zapros);
        self::assertSame('workspace-1', $zapros->WorkspaceId);
        self::assertFalse($zapros->WorkspaceReadOnly);
        self::assertSame([], $zapros->Units);
        self::assertNull($zapros->UnitId);
        self::assertNull($zapros->ProjectId);
        self::assertCount(1, $zapros->WorkspaceProjects);
        self::assertSame('project-1', $zapros->WorkspaceProjects[0]['ProjectId']);
        self::assertTrue($zapros->WorkspaceProjects[0]['CanPush']);
    }

    #[Test]
    public function rezultat_ostanovki_mapping_v_success(): void
    {
        $session = FabrikaWorkspace::vStatuse(StatusWorkspace::RUNNING);

        $runnerApi = $this->createMock(RunnerApiInterface::class);
        $runnerApi->method('ostanovitWorkspace')->willReturn('wf-stop');
        $runnerApi->method('poluchitResultatOstanovkiWorkspace')->willReturn(
            new \App\Runner\Business\Command\Workspace\ResultatOstanovkiWorkspace(1),
        );

        $uuid = $this->createMock(UuidGenerator::class);
        $uuid->method('makeGuid')->willReturn('wf-stop');

        $adapter = new RunnerAdapter($runnerApi, $uuid);
        $adapter->ostanovit($session);

        self::assertSame(
            \App\OpenCode\Business\Port\UmeetUpravlyatWorkspace::RESULT_SUCCESS,
            $adapter->poluchitRezultatOperacii($session),
        );
    }

    private function proekt(): ProektWorkspace
    {
        return FabrikaWorkspace::proekt();
    }
}
