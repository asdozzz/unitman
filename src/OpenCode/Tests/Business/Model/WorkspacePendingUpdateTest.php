<?php

declare(strict_types=1);

namespace App\OpenCode\Tests\Business\Model;

use App\OpenCode\Business\Model\ProektWorkspace;
use App\OpenCode\Business\Model\WorkspacePendingUpdate;
use App\OpenCode\Tests\Support\FabrikaWorkspace;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

/**
 * Сериализация отложенного обновления workspace для таблицы
 * opencode_workspace_update.
 */
final class WorkspacePendingUpdateTest extends TestCase
{
    #[Test]
    public function proekty_serializuyutsya_i_vosstanavlivayutsya(): void
    {
        $update = new WorkspacePendingUpdate(
            WorkspacePendingUpdate::KIND_PROJECT,
            [
                new ProektWorkspace('project-2', 'web', 'apps/web', false, 1, 'develop', 'feature/x'),
            ],
            [],
            [],
            true,
        );

        $restored = WorkspacePendingUpdate::fromArray($update->toArray());

        $this->assertSame(WorkspacePendingUpdate::KIND_PROJECT, $restored->kind);
        $this->assertTrue($restored->readOnly);
        $this->assertCount(1, $restored->projects);
        $project = $restored->projects[0];
        $this->assertSame('project-2', $project->projectId);
        $this->assertSame('web', $project->projectName);
        $this->assertSame('apps/web', $project->projectCode);
        $this->assertFalse($project->canPush);
        $this->assertSame(1, $project->positionIndex);
        $this->assertSame('develop', $project->mainBranch);
        $this->assertSame('feature/x', $project->branch);
    }

    #[Test]
    public function fayly_serializuyutsya_i_vosstanavlivayutsya(): void
    {
        $update = new WorkspacePendingUpdate(
            WorkspacePendingUpdate::KIND_FILES,
            [],
            [['fileId' => 'file-1', 'name' => 'notes.txt']],
            [],
            false,
        );

        $restored = WorkspacePendingUpdate::fromArray($update->toArray());

        $this->assertSame(WorkspacePendingUpdate::KIND_FILES, $restored->kind);
        $this->assertSame([['fileId' => 'file-1', 'name' => 'notes.txt']], $restored->files);
        $this->assertSame([], $restored->filesToDelete);
        $this->assertFalse($restored->readOnly);
    }

    #[Test]
    public function fayly_k_udaleniyu_serializuyutsya(): void
    {
        $update = new WorkspacePendingUpdate(
            WorkspacePendingUpdate::KIND_FILES_DELETE,
            [],
            [],
            [['fileId' => 'file-9', 'name' => 'old.md']],
            false,
        );

        $restored = WorkspacePendingUpdate::fromArray($update->toArray());

        $this->assertSame(
            [['fileId' => 'file-9', 'name' => 'old.md']],
            $restored->filesToDelete,
        );
    }

    #[Test]
    public function povrezhdennye_elementy_propuskayutsya(): void
    {
        $restored = WorkspacePendingUpdate::fromArray([
            'kind' => WorkspacePendingUpdate::KIND_PROJECT_AND_FILES,
            'projects' => ['not-an-array', ['projectId' => 'project-1']],
            'files' => [['name' => 'no-id.txt'], ['fileId' => 'file-1', 'name' => 'ok.txt']],
            'filesToDelete' => 'broken',
            'readOnly' => 1,
        ]);

        $this->assertCount(1, $restored->projects);
        $this->assertSame('project-1', $restored->projects[0]->projectId);
        $this->assertSame([['fileId' => 'file-1', 'name' => 'ok.txt']], $restored->files);
        $this->assertSame([], $restored->filesToDelete);
        $this->assertTrue($restored->readOnly);
    }

    #[Test]
    public function pustoе_pole_kind_beret_znachenie_po_umolchaniyu(): void
    {
        $restored = WorkspacePendingUpdate::fromArray([]);

        $this->assertSame(WorkspacePendingUpdate::KIND_PROJECT, $restored->kind);
        $this->assertSame([], $restored->projects);
        $this->assertFalse($restored->readOnly);
    }

    #[Test]
    public function fabrika_sozdaet_proekt_dlya_testov(): void
    {
        $project = FabrikaWorkspace::proekt('project-7', 'search', 'services/search', true, 3, 'main');

        $this->assertSame('project-7', $project->projectId);
        $this->assertSame('services/search', $project->projectCode);
        $this->assertSame(3, $project->positionIndex);
    }
}
