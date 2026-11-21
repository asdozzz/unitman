<?php

declare(strict_types=1);

namespace App\OpenCode\Tests\Business\Model;

use App\OpenCode\Business\Model\ProektWorkspace;
use App\OpenCode\Business\Model\SessiyaOpenCode;
use App\OpenCode\Business\Model\StatusWorkspace;
use App\OpenCode\Business\Model\TipSessiiOpenCode;
use App\OpenCode\Business\Model\Workspace;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

final class SessiyaOpenCodeWorkspaceTest extends TestCase
{
    private const NOW = 1_700_000_000;

    #[Test]
    public function create_workspace_sozdaet_sessiyu_bez_unitov(): void
    {
        $sessiya = $this->workspace();

        $this->assertSame(TipSessiiOpenCode::WORKSPACE, $sessiya->getTip());
        $this->assertTrue($sessiya->estWorkspace());
        $this->assertSame('payments-refactor', $sessiya->getTitle());
        $this->assertSame([], $sessiya->getUnits());
        $this->assertSame([], $sessiya->getUnitIds());
        $this->assertNull($sessiya->getUnitId());
        $this->assertNull($sessiya->getUnitProjectId());
        $this->assertFalse($sessiya->privezanaKUnitu());
        $this->assertNotNull($sessiya->getWorkspace());
        $this->assertSame(StatusWorkspace::CREATING, $sessiya->getWorkspace()?->getStatus());
    }

    #[Test]
    public function obychnaya_sessiya_imeet_tip_unit_i_bez_workspace(): void
    {
        $sessiya = SessiyaOpenCode::create('s-1', 'acc-1', 'cred-1', null, [], self::NOW);

        $this->assertSame(TipSessiiOpenCode::UNIT, $sessiya->getTip());
        $this->assertFalse($sessiya->estWorkspace());
        $this->assertNull($sessiya->getWorkspace());
    }

    #[Test]
    public function imya_workspace_obyazatelno(): void
    {
        $this->expectException(\DomainException::class);
        $this->expectExceptionMessage('opencode.workspace.name_required');

        SessiyaOpenCode::createWorkspace(
            's-1',
            'acc-1',
            'cred-1',
            '   ',
            'main',
            [$this->proekt('p-1', true)],
            'Промпт',
            self::NOW,
        );
    }

    #[Test]
    public function imya_workspace_ogranicheno_dlinoj(): void
    {
        $this->expectException(\DomainException::class);
        $this->expectExceptionMessage('opencode.workspace.name_too_long');

        SessiyaOpenCode::createWorkspace(
            's-1',
            'acc-1',
            'cred-1',
            str_repeat('n', 300),
            'main',
            [$this->proekt('p-1', true)],
            'Промпт',
            self::NOW,
        );
    }

    #[Test]
    public function bez_proektov_workspace_ne_sozdaetsya(): void
    {
        $this->expectException(\DomainException::class);
        $this->expectExceptionMessage('opencode.workspace.projects_required');

        SessiyaOpenCode::createWorkspace('s-1', 'acc-1', 'cred-1', 'name', 'main', [], 'Промпт', self::NOW);
    }

    #[Test]
    public function prevyshenie_limita_proektov_otklonyaetsya(): void
    {
        $projects = [];
        for ($i = 1; $i <= SessiyaOpenCode::MAX_WORKSPACE_PROJECTS + 1; ++$i) {
            $projects[] = $this->proekt('p-' . $i, true, $i - 1);
        }

        $this->expectException(\DomainException::class);
        $this->expectExceptionMessage('opencode.workspace.too_many_projects');

        SessiyaOpenCode::createWorkspace('s-1', 'acc-1', 'cred-1', 'name', 'main', $projects, 'Промпт', self::NOW);
    }

    #[Test]
    public function dublikat_proekta_otklonyaetsya(): void
    {
        $this->expectException(\DomainException::class);
        $this->expectExceptionMessage('opencode.workspace.project_conflict');

        SessiyaOpenCode::createWorkspace(
            's-1',
            'acc-1',
            'cred-1',
            'name',
            'main',
            [$this->proekt('p-1', true), $this->proekt('p-1', true)],
            'Промпт',
            self::NOW,
        );
    }

    #[Test]
    public function podpis_po_promptu_ne_menyaet_imya_workspace(): void
    {
        $sessiya = $this->workspace();

        $sessiya->podpisatPoPromptu('Совсем другой промпт', self::NOW + 10);

        $this->assertSame('payments-refactor', $sessiya->getTitle());
        $this->assertSame(self::NOW, $sessiya->getUpdatedAt());
    }

    #[Test]
    public function arhiv_i_vosstanovlenie_workspace(): void
    {
        $sessiya = $this->workspace();

        $sessiya->arhivirovat(SessiyaOpenCode::ARCHIVE_REASON_MANUAL, self::NOW + 10);

        $this->assertTrue($sessiya->estArhiv());
        $this->assertSame(self::NOW + 10, $sessiya->getArchivedAt());
        $this->assertSame(SessiyaOpenCode::ARCHIVE_REASON_MANUAL, $sessiya->getArchiveReason());

        $sessiya->snovaAktivna(self::NOW + 20);

        $this->assertFalse($sessiya->estArhiv());
        $this->assertNull($sessiya->getArchivedAt());
        $this->assertNull($sessiya->getArchiveReason());
        $this->assertSame(self::NOW + 20, $sessiya->getUpdatedAt());
    }

    #[Test]
    public function snova_aktivna_na_aktivnoj_sessii_nichego_ne_menyaet(): void
    {
        $sessiya = $this->workspace();

        $sessiya->snovaAktivna(self::NOW + 20);

        $this->assertSame(self::NOW, $sessiya->getUpdatedAt());
    }

    #[Test]
    public function restore_workspace_vosstanavlivaet_status_hranilishcha(): void
    {
        $workspace = Workspace::restore(
            'release',
            'Промпт',
            [$this->proekt('p-1', false)],
            StatusWorkspace::ARCHIVED,
            true,
            null,
            Workspace::OPERATION_STOP,
            'wf-1',
            0,
            0,
            self::NOW,
            self::NOW - 10,
            self::NOW,
        );

        $sessiya = SessiyaOpenCode::restoreWorkspace(
            's-1',
            'acc-1',
            'cred-1',
            'payments-refactor',
            'ext-1',
            self::NOW,
            'manual',
            $workspace,
            self::NOW - 10,
            self::NOW,
        );

        $this->assertSame(TipSessiiOpenCode::WORKSPACE, $sessiya->getTip());
        $this->assertTrue($sessiya->estWorkspace());
        $this->assertSame('payments-refactor', $sessiya->getTitle());
        $this->assertSame('ext-1', $sessiya->getExternalSessionId());
        $this->assertSame('manual', $sessiya->getArchiveReason());
        $this->assertSame(StatusWorkspace::ARCHIVED, $sessiya->getWorkspace()?->getStatus());
    }

    #[Test]
    public function restore_arhiva_bez_prichiny_otklonyaetsya(): void
    {
        $workspace = Workspace::create('main', 'Промпт', [$this->proekt('p-1', true)], self::NOW);

        $this->expectException(\DomainException::class);
        $this->expectExceptionMessage('opencode.session.archive_reason_required');

        SessiyaOpenCode::restoreWorkspace(
            's-1',
            'acc-1',
            'cred-1',
            'name',
            null,
            self::NOW,
            null,
            $workspace,
            self::NOW,
            self::NOW,
        );
    }

    private function workspace(): SessiyaOpenCode
    {
        return SessiyaOpenCode::createWorkspace(
            's-1',
            'acc-1',
            'cred-1',
            'payments-refactor',
            'feature/payments',
            [$this->proekt('p-1', true), $this->proekt('p-2', false, 1)],
            'Сделай рефакторинг',
            self::NOW,
        );
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
