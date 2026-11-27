<?php

declare(strict_types=1);

namespace App\Mediateka\Tests\Business;

use App\Mediateka\Business\Model\FaylUnita;
use App\Mediateka\Business\UseCase\UdaliFajlyWorkspaceUseCase;
use App\Mediateka\Tests\Support\FakeFileStorage;
use App\Mediateka\Tests\Support\InMemoryFaylUnitaRepository;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

/**
 * Удаление файлов workspace: объекты и записи реестра, best-effort
 * по объектам, пустой workspace — no-op.
 */
final class UdaliFajlyWorkspaceUseCaseTest extends TestCase
{
    private FakeFileStorage $fileStorage;
    private InMemoryFaylUnitaRepository $repository;
    private UdaliFajlyWorkspaceUseCase $useCase;

    protected function setUp(): void
    {
        $this->fileStorage = new FakeFileStorage();
        $this->repository = new InMemoryFaylUnitaRepository();
        $this->useCase = new UdaliFajlyWorkspaceUseCase($this->repository, $this->fileStorage);
    }

    #[Test]
    public function udalyayutsya_fajly_tolko_celevogo_workspace(): void
    {
        $this->sohranit('file-1', 'ws-1');
        $this->sohranit('file-2', 'ws-1');
        $this->sohranit('file-3', 'ws-2');

        $this->useCase->handle('ws-1');

        $this->assertSame(
            ['workspaces/ws-1/file-1/file-1.md', 'workspaces/ws-1/file-2/file-2.md'],
            $this->fileStorage->deleted
        );
        $this->assertNull($this->repository->findById('file-1'));
        $this->assertNull($this->repository->findById('file-2'));
        $this->assertNotNull($this->repository->findById('file-3'));
        $this->assertSame(1, $this->repository->kolichestvo());
    }

    #[Test]
    public function oshibka_udalenniya_obekta_ne_meshaet_udalit_reestr(): void
    {
        $this->sohranit('file-1', 'ws-1');
        $this->sohranit('file-2', 'ws-1');
        $this->fileStorage->failDelete['workspaces/ws-1/file-1/file-1.md'] = true;

        $this->useCase->handle('ws-1');

        $this->assertSame(['workspaces/ws-1/file-2/file-2.md'], $this->fileStorage->deleted);
        $this->assertSame(0, $this->repository->kolichestvo());
    }

    #[Test]
    public function pustoj_workspace_ne_yavlyaetsya_oshibkoj(): void
    {
        $this->useCase->handle('ws-empty');

        $this->assertSame([], $this->fileStorage->deleted);
        $this->assertSame(0, $this->repository->kolichestvo());
    }

    private function sohranit(string $fileId, string $workspaceId): void
    {
        $key = sprintf('workspaces/%s/%s/%s.md', $workspaceId, $fileId, $fileId);

        $this->repository->save(new FaylUnita(
            $fileId,
            null,
            $fileId . '.md',
            10,
            'text/markdown',
            $key,
            'account-1',
            1000,
            null,
            $workspaceId,
            FaylUnita::KIND_WORKSPACE,
            null,
            'turn-1',
        ));
    }
}
