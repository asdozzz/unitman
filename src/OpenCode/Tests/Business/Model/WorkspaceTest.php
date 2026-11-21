<?php

declare(strict_types=1);

namespace App\OpenCode\Tests\Business\Model;

use App\OpenCode\Business\Model\ProektWorkspace;
use App\OpenCode\Business\Model\StatusWorkspace;
use App\OpenCode\Business\Model\Workspace;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

final class WorkspaceTest extends TestCase
{
    private const NOW = 1_700_000_000;

    #[Test]
    public function sozdanie_startuet_v_creating(): void
    {
        $workspace = Workspace::create('main', 'Сделай рефакторинг', [$this->proekt('p-1', true)], self::NOW);

        $this->assertSame('main', $workspace->getBranch());
        $this->assertSame('Сделай рефакторинг', $workspace->getInitialPrompt());
        $this->assertSame(StatusWorkspace::CREATING, $workspace->getStatus());
        $this->assertFalse($workspace->isReadOnly());
        $this->assertNull($workspace->getErrorKey());
        $this->assertSame(Workspace::OPERATION_CREATE, $workspace->getOperation());
        $this->assertSame('', $workspace->getOperationWorkflowId());
        $this->assertSame(0, $workspace->getOperationAttempts());
        $this->assertSame(0, $workspace->getNextAttemptAt());
        $this->assertNull($workspace->getInitialPromptStartedAt());
        $this->assertSame(self::NOW, $workspace->getCreatedAt());
        $this->assertSame(self::NOW, $workspace->getUpdatedAt());
    }

    #[Test]
    public function hotya_by_odin_proekt_bez_push_delaet_workspace_read_only(): void
    {
        $workspace = Workspace::create(
            'main',
            'Промпт',
            [$this->proekt('p-1', true), $this->proekt('p-2', false)],
            self::NOW,
        );

        $this->assertTrue($workspace->isReadOnly());
    }

    #[Test]
    public function vse_proekty_s_push_ostavlyayut_workspace_rw(): void
    {
        $workspace = Workspace::create(
            'main',
            'Промпт',
            [$this->proekt('p-1', true), $this->proekt('p-2', true)],
            self::NOW,
        );

        $this->assertFalse($workspace->isReadOnly());
    }

    #[Test]
    public function poryadok_proektov_sohranyaetsya(): void
    {
        $workspace = Workspace::create(
            'main',
            'Промпт',
            [$this->proekt('p-2', true, 0), $this->proekt('p-1', true, 1)],
            self::NOW,
        );

        $this->assertSame('p-2', $workspace->getProjects()[0]->projectId);
        $this->assertSame('p-1', $workspace->getProjects()[1]->projectId);
    }

    #[Test]
    public function pustaya_vetka_zapreshchena(): void
    {
        $this->expectException(\DomainException::class);
        $this->expectExceptionMessage('opencode.workspace.branch_required');

        Workspace::create('   ', 'Промпт', [$this->proekt('p-1', true)], self::NOW);
    }

    #[Test]
    public function dlinnya_vetka_zapreshchena(): void
    {
        $this->expectException(\DomainException::class);
        $this->expectExceptionMessage('opencode.workspace.branch_too_long');

        Workspace::create(str_repeat('b', 257), 'Промпт', [$this->proekt('p-1', true)], self::NOW);
    }

    #[Test]
    public function pustoi_prompt_zapreshchen(): void
    {
        $this->expectException(\DomainException::class);
        $this->expectExceptionMessage('opencode.workspace.prompt_required');

        Workspace::create('main', "  \n ", [$this->proekt('p-1', true)], self::NOW);
    }

    #[Test]
    public function bez_proektov_workspace_ne_sozdaetsya(): void
    {
        $this->expectException(\DomainException::class);
        $this->expectExceptionMessage('opencode.workspace.projects_required');

        Workspace::create('main', 'Промпт', [], self::NOW);
    }

    #[Test]
    public function dublikat_proekta_zapreshchen(): void
    {
        $this->expectException(\DomainException::class);
        $this->expectExceptionMessage('opencode.workspace.project_conflict');

        Workspace::create(
            'main',
            'Промпт',
            [$this->proekt('p-1', true), $this->proekt('p-1', true)],
            self::NOW,
        );
    }

    #[Test]
    public function perevod_v_dopustimyi_status_dvigaet_updated_at(): void
    {
        $workspace = Workspace::create('main', 'Промпт', [$this->proekt('p-1', true)], self::NOW);

        $workspace->perevestiV(StatusWorkspace::RUNNING, self::NOW + 10);

        $this->assertSame(StatusWorkspace::RUNNING, $workspace->getStatus());
        $this->assertSame(self::NOW + 10, $workspace->getUpdatedAt());
    }

    #[Test]
    public function nedopustimyi_perehod_otklonyaetsya(): void
    {
        $workspace = Workspace::create('main', 'Промпт', [$this->proekt('p-1', true)], self::NOW);

        $this->expectException(\DomainException::class);
        $this->expectExceptionMessage('opencode.workspace.invalid_transition');

        $workspace->perevestiV(StatusWorkspace::ARCHIVED, self::NOW + 10);
    }

    #[Test]
    public function operaciya_i_popytka_zapominayutsya(): void
    {
        $workspace = Workspace::create('main', 'Промпт', [$this->proekt('p-1', true)], self::NOW);

        $workspace->zapomnitOperaciyu(Workspace::OPERATION_START, 'wf-1', self::NOW + 1);
        $workspace->zapomnitPopitku(2, self::NOW + 60, self::NOW + 2);

        $this->assertSame(Workspace::OPERATION_START, $workspace->getOperation());
        $this->assertSame('wf-1', $workspace->getOperationWorkflowId());
        $this->assertSame(2, $workspace->getOperationAttempts());
        $this->assertSame(self::NOW + 60, $workspace->getNextAttemptAt());
        $this->assertSame(self::NOW + 2, $workspace->getUpdatedAt());
    }

    #[Test]
    public function oshibka_perevodit_creating_v_error(): void
    {
        $workspace = Workspace::create('main', 'Промпт', [$this->proekt('p-1', true)], self::NOW);

        $workspace->otmetitOshibku('opencode.workspace.initial_prompt_failed', self::NOW + 5);

        $this->assertSame('opencode.workspace.initial_prompt_failed', $workspace->getErrorKey());
        $this->assertSame(StatusWorkspace::ERROR, $workspace->getStatus());
        $this->assertSame(self::NOW + 5, $workspace->getUpdatedAt());
    }

    #[Test]
    public function oshibka_v_running_ne_menyaet_status(): void
    {
        $workspace = Workspace::create('main', 'Промпт', [$this->proekt('p-1', true)], self::NOW);
        $workspace->perevestiV(StatusWorkspace::RUNNING, self::NOW + 1);

        $workspace->otmetitOshibku('opencode.workspace.operation_failed', self::NOW + 2);

        $this->assertSame('opencode.workspace.operation_failed', $workspace->getErrorKey());
        $this->assertSame(StatusWorkspace::RUNNING, $workspace->getStatus());
    }

    #[Test]
    public function start_pervogo_prompta_zapominaetsya(): void
    {
        $workspace = Workspace::create('main', 'Промпт', [$this->proekt('p-1', true)], self::NOW);

        $workspace->otmetitStartPervogoPrompta(self::NOW + 7);

        $this->assertSame(self::NOW + 7, $workspace->getInitialPromptStartedAt());
        $this->assertSame(self::NOW + 7, $workspace->getUpdatedAt());
    }

    #[Test]
    public function restore_sohranyaet_status_i_operaciyu_bez_zameny(): void
    {
        $workspace = Workspace::restore(
            'release',
            'Промпт',
            [$this->proekt('p-1', false)],
            StatusWorkspace::ARCHIVED,
            true,
            'opencode.workspace.operation_failed',
            Workspace::OPERATION_STOP,
            'wf-2',
            3,
            self::NOW + 100,
            self::NOW + 50,
            self::NOW,
            self::NOW + 60,
        );

        $this->assertSame(StatusWorkspace::ARCHIVED, $workspace->getStatus());
        $this->assertSame(Workspace::OPERATION_STOP, $workspace->getOperation());
        $this->assertTrue($workspace->isReadOnly());
        $this->assertSame('opencode.workspace.operation_failed', $workspace->getErrorKey());
        $this->assertSame(3, $workspace->getOperationAttempts());
        $this->assertSame(self::NOW + 100, $workspace->getNextAttemptAt());
        $this->assertSame(self::NOW + 50, $workspace->getInitialPromptStartedAt());
    }

    private function proekt(string $projectId, bool $canPush, int $positionIndex = 0): ProektWorkspace
    {
        return new ProektWorkspace(
            $projectId,
            'project-' . $projectId,
            'services/' . $projectId,
            $canPush,
            $positionIndex,
            'main',
        );
    }
}
