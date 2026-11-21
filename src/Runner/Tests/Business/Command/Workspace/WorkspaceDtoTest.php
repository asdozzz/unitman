<?php

declare(strict_types=1);

namespace App\Runner\Tests\Business\Command\Workspace;

use App\Runner\Business\Command\Workspace\OstanovitWorkspace;
use App\Runner\Business\Command\Workspace\ProektWorkspaceCommand;
use App\Runner\Business\Command\Workspace\ProektWorkspaceZapros;
use App\Runner\Business\Command\Workspace\ProveritIzmeneniyaWorkspace;
use App\Runner\Business\Command\Workspace\ResultatOstanovkiWorkspace;
use App\Runner\Business\Command\Workspace\ResultatProverkiIzmeneniyWorkspace;
use App\Runner\Business\Command\Workspace\ResultatUdaleniyaWorkspace;
use App\Runner\Business\Command\Workspace\ResultatZapuskaWorkspace;
use App\Runner\Business\Command\Workspace\UdalitWorkspace;
use App\Runner\Business\Command\Workspace\ZapustitWorkspace;
use App\Runner\Business\Command\Workspace\ZapustitWorkspaceZapros;
use PHPUnit\Framework\TestCase;

final class WorkspaceDtoTest extends TestCase
{
    public function test_zapustit_workspace_sohranyaet_polya_i_proekty(): void
    {
        $project = new ProektWorkspaceCommand(
            'project-1',
            'api',
            'services/api',
            'https://oauth2:token@gitlab.example/services/api.git',
            true,
            'main',
        );

        $command = new ZapustitWorkspace(
            'workspace-1',
            'payments-refactor',
            'payments-refactor-1a2b3c4d',
            'feature/payments',
            false,
            3072,
            [$project],
        );

        self::assertSame('workspace-1', $command->WorkspaceId);
        self::assertSame('payments-refactor', $command->WorkspaceName);
        self::assertSame('payments-refactor-1a2b3c4d', $command->WorkspaceSlug);
        self::assertSame('feature/payments', $command->Branch);
        self::assertFalse($command->ReadOnly);
        self::assertSame(3072, $command->MemoryLimit);
        self::assertSame([$project], $command->Projects);
    }

    public function test_zapustit_workspace_zapros_ne_soderzhit_storage_url(): void
    {
        $project = new ProektWorkspaceZapros(
            'project-1',
            'api',
            'services/api',
            true,
            'main',
        );

        $request = new ZapustitWorkspaceZapros(
            'workspace-1',
            'payments-refactor',
            'payments-refactor-1a2b3c4d',
            'feature/payments',
            false,
            3072,
            [$project],
        );

        self::assertSame('workspace-1', $request->WorkspaceId);
        self::assertSame('payments-refactor', $request->WorkspaceName);
        self::assertSame('payments-refactor-1a2b3c4d', $request->WorkspaceSlug);
        self::assertSame('feature/payments', $request->Branch);
        self::assertFalse($request->ReadOnly);
        self::assertSame(3072, $request->MemoryLimit);
        self::assertSame([$project], $request->Projects);

        self::assertSame('project-1', $project->ProjectId);
        self::assertSame('api', $project->ProjectName);
        self::assertSame('services/api', $project->ProjectCode);
        self::assertTrue($project->CanPush);
        self::assertSame('main', $project->MainBranch);

        self::assertFalse(
            $this->hasStorageUrlProperty(ZapustitWorkspaceZapros::class),
            'Публичный запрос не должен нести токен',
        );
        self::assertFalse(
            $this->hasStorageUrlProperty(ProektWorkspaceZapros::class),
            'Публичный проект не должен нести токен',
        );
    }

    private function hasStorageUrlProperty(string $class): bool
    {
        foreach ((new \ReflectionClass($class))->getProperties() as $property) {
            if (str_contains(strtolower($property->getName()), 'storageurl')) {
                return true;
            }
        }

        return false;
    }

    public function test_proekt_workspace_sohranyaet_polya(): void
    {
        $project = new ProektWorkspaceCommand(
            'project-1',
            'api',
            'services/api',
            'https://oauth2:token@gitlab.example/services/api.git',
            false,
            'master',
        );

        self::assertSame('project-1', $project->ProjectId);
        self::assertSame('api', $project->ProjectName);
        self::assertSame('services/api', $project->ProjectCode);
        self::assertSame('https://oauth2:token@gitlab.example/services/api.git', $project->StorageUrl);
        self::assertFalse($project->CanPush);
        self::assertSame('master', $project->MainBranch);
    }

    public function test_ostanovit_i_udalit_workspace_sohranyayut_id(): void
    {
        self::assertSame('workspace-1', (new OstanovitWorkspace('workspace-1'))->WorkspaceId);
        self::assertSame('workspace-1', (new UdalitWorkspace('workspace-1'))->WorkspaceId);
    }

    public function test_proverit_izmeneniya_sohranyaet_proekty(): void
    {
        $projects = [
            ['ProjectId' => 'project-1', 'ProjectName' => 'api'],
        ];

        $command = new ProveritIzmeneniyaWorkspace('workspace-1', $projects);

        self::assertSame('workspace-1', $command->WorkspaceId);
        self::assertSame($projects, $command->Projects);
    }

    public function test_resultat_zapuska_imeet_znacheniya_po_umolchaniyu(): void
    {
        $result = new ResultatZapuskaWorkspace(1);

        self::assertSame(1, $result->Success);
        self::assertSame([], $result->Steps);
        self::assertSame('', $result->ResponseId);
        self::assertSame([], $result->Projects);
    }

    public function test_resultat_ostanovki_i_udaleniya_imeyut_znacheniya_po_umolchaniyu(): void
    {
        $stop = new ResultatOstanovkiWorkspace(0);
        $delete = new ResultatUdaleniyaWorkspace(0);

        self::assertSame(0, $stop->Success);
        self::assertSame([], $stop->Steps);
        self::assertSame('', $stop->ResponseId);

        self::assertSame(0, $delete->Success);
        self::assertSame([], $delete->Steps);
        self::assertSame('', $delete->ResponseId);
    }

    public function test_resultat_proverki_sohranyaet_proekty(): void
    {
        $projects = [
            ['ProjectId' => 'project-1', 'Name' => 'api', 'Dirty' => true, 'Files' => [' M src/Foo.php']],
        ];

        $result = new ResultatProverkiIzmeneniyWorkspace(true, $projects);

        self::assertTrue($result->Success);
        self::assertSame($projects, $result->Projects);
    }
}
