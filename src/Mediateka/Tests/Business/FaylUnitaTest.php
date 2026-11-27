<?php

declare(strict_types=1);

namespace App\Mediateka\Tests\Business;

use App\Mediateka\Business\Model\FaylUnita;
use DomainException;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

/**
 * Модель файла: валидации хода, привязка к ходу и сериализация.
 */
final class FaylUnitaTest extends TestCase
{
    #[Test]
    public function pustoj_opencode_turn_id_otklonyaetsya(): void
    {
        $this->expectException(DomainException::class);
        $this->expectExceptionMessage('mediateka.file.opencode_turn_id_is_empty');

        $this->sozdat(workspaceId: 'ws-1', opencodeTurnId: '  ');
    }

    #[Test]
    public function hod_bez_workspace_otklonyaetsya(): void
    {
        $this->expectException(DomainException::class);
        $this->expectExceptionMessage('mediateka.file.turn_without_workspace');

        $this->sozdat(workspaceId: null, opencodeTurnId: 'turn-1');
    }

    #[Test]
    public function privyazka_k_hodu_zapolnyaet_polya(): void
    {
        $file = $this->sozdat();

        $this->assertNull($file->getAppliedAt());
        $this->assertFalse($file->esliFajlHoda());

        $privyazan = $file->prikrepitKWorkspaceKakFajlHoda('ws-1', 'turn-1', 777);

        $this->assertSame($file->getId(), $privyazan->getId());
        $this->assertSame($file->getName(), $privyazan->getName());
        $this->assertSame($file->getStorageKey(), $privyazan->getStorageKey());
        $this->assertNull($privyazan->getUnitId());
        $this->assertNull($privyazan->getProjectId());
        $this->assertSame('ws-1', $privyazan->getWorkspaceId());
        $this->assertSame(FaylUnita::KIND_WORKSPACE, $privyazan->getKind());
        $this->assertSame(777, $privyazan->getAppliedAt());
        $this->assertSame('turn-1', $privyazan->getOpencodeTurnId());
        $this->assertTrue($privyazan->esliFajlHoda());
    }

    #[Test]
    public function privyazka_k_hodu_otklonyaet_fajl_unita(): void
    {
        $file = $this->sozdat(unitId: 'unit-1');

        $this->expectException(DomainException::class);
        $this->expectExceptionMessage('mediateka.file.is_not_workspace_draft');

        $file->prikrepitKWorkspaceKakFajlHoda('ws-1', 'turn-1', 777);
    }

    #[Test]
    public function privyazka_k_hodu_otklonyaet_pustoj_turn(): void
    {
        $this->expectException(DomainException::class);
        $this->expectExceptionMessage('mediateka.file.opencode_turn_id_is_empty');

        $this->sozdat()->prikrepitKWorkspaceKakFajlHoda('ws-1', '  ', 777);
    }

    #[Test]
    public function to_array_i_from_row_save_opencode_turn_id(): void
    {
        $file = $this->sozdat(workspaceId: 'ws-1', opencodeTurnId: 'turn-1');

        $array = $file->toArray();
        $this->assertSame('turn-1', $array['opencodeTurnId']);

        $restored = FaylUnita::fromRow([
            'id' => $file->getId(),
            'unit_id' => null,
            'project_id' => null,
            'workspace_id' => 'ws-1',
            'kind' => FaylUnita::KIND_WORKSPACE,
            'name' => $file->getName(),
            'size' => $file->getSize(),
            'mime_type' => $file->getMimeType(),
            'storage_key' => $file->getStorageKey(),
            'uploaded_by' => $file->getUploadedBy(),
            'applied_at' => null,
            'opencode_turn_id' => 'turn-1',
            'created_at' => $file->getCreatedAt(),
        ]);

        $this->assertSame('turn-1', $restored->getOpencodeTurnId());
        $this->assertSame($file->toArray(), $restored->toArray());
    }

    #[Test]
    public function from_row_prevrashchaet_pustoj_turn_v_null(): void
    {
        $restored = FaylUnita::fromRow([
            'id' => 'file-1',
            'workspace_id' => 'ws-1',
            'name' => 'note.md',
            'size' => 10,
            'mime_type' => 'text/markdown',
            'storage_key' => 'workspaces/ws-1/file-1/note.md',
            'created_at' => 1000,
            'opencode_turn_id' => '',
        ]);

        $this->assertNull($restored->getOpencodeTurnId());
    }

    private function sozdat(
        ?string $unitId = null,
        ?string $projectId = null,
        ?string $workspaceId = null,
        ?string $opencodeTurnId = null,
        ?int $appliedAt = null,
    ): FaylUnita {
        return new FaylUnita(
            'file-1',
            $unitId,
            'note.md',
            10,
            'text/markdown',
            'workspaces/ws-1/file-1/note.md',
            'account-1',
            1000,
            $projectId,
            $workspaceId,
            FaylUnita::KIND_WORKSPACE,
            $appliedAt,
            $opencodeTurnId,
        );
    }
}
