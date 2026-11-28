<?php

declare(strict_types=1);

namespace App\OpenCode\Tests\Acl;

use App\OpenCode\Acl\RunnerAdapter;
use App\OpenCode\Business\Port\UuidGenerator;
use App\OpenCode\Business\Workspace\Model\WorkspaceSession;
use App\OpenCode\Tests\Support\FabrikaWorkspace;
use App\Runner\Api\RunnerApiInterface;
use App\Runner\Business\Command\Workspace\ZapustitWorkspaceZapros;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

/**
 * Payload раннера не зависит от типа сессии: легаси `SessiyaOpenCode` и новый
 * `WorkspaceSession` дают идентичный запрос запуска.
 */
final class RunnerAdapterWorkspaceSessionTest extends TestCase
{
    #[Test]
    public function zapusk_na_workspace_session_identichen_legasi(): void
    {
        $legacy = FabrikaWorkspace::sozdanie();

        $workspace = WorkspaceSession::create(
            'workspace-1',
            'account-1',
            'credential-1',
            'payments-refactor',
            'feature/payments',
            [FabrikaWorkspace::proekt()],
            'Первый промпт',
            FabrikaWorkspace::NOW,
        );

        $legacyRequest = $this->zaprosDlya($legacy);
        $workspaceRequest = $this->zaprosDlya($workspace);

        self::assertSame($legacyRequest->WorkspaceId, $workspaceRequest->WorkspaceId);
        self::assertSame($legacyRequest->WorkspaceName, $workspaceRequest->WorkspaceName);
        self::assertSame($legacyRequest->WorkspaceSlug, $workspaceRequest->WorkspaceSlug);
        self::assertSame($legacyRequest->Branch, $workspaceRequest->Branch);
        self::assertSame($legacyRequest->ReadOnly, $workspaceRequest->ReadOnly);
        self::assertSame($legacyRequest->MemoryLimit, $workspaceRequest->MemoryLimit);
        self::assertCount(count($legacyRequest->Projects), $workspaceRequest->Projects);
        self::assertSame('project-1', $workspaceRequest->Projects[0]->ProjectId);
        self::assertSame('main', $workspaceRequest->Projects[0]->MainBranch);
    }

    private function zaprosDlya(object $session): ZapustitWorkspaceZapros
    {
        $zapros = null;

        $runnerApi = $this->createMock(RunnerApiInterface::class);
        $runnerApi->expects($this->once())
            ->method('zapustitWorkspace')
            ->willReturnCallback(
                static function (string $workflowId, ZapustitWorkspaceZapros $command) use (&$zapros): string {
                    $zapros = $command;

                    return $workflowId;
                }
            );

        $uuid = $this->createMock(UuidGenerator::class);
        $uuid->method('makeGuid')->willReturn('wf-create');

        $adapter = new RunnerAdapter($runnerApi, $uuid);
        $adapter->zapustit($session);

        self::assertInstanceOf(ZapustitWorkspaceZapros::class, $zapros);

        return $zapros;
    }
}
