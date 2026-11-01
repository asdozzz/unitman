<?php

declare(strict_types=1);

namespace App\Mediateka\Tests\Business;

use App\Mediateka\Business\Model\FaylUnita;
use App\Mediateka\Business\UseCase\UdaliFayliUnitaUseCase;
use App\Mediateka\Tests\Support\FakeFileStorage;
use App\Mediateka\Tests\Support\InMemoryFaylUnitaRepository;
use DomainException;
use PHPUnit\Framework\TestCase;

/**
 * Удаление файлов юнита: объекты в хранилище удаляются до записей реестра,
 * повторный вызов на пустом юните безопасен.
 */
final class UdaliFayliUnitaUseCaseTest extends TestCase
{
    private FakeFileStorage $fileStorage;
    private InMemoryFaylUnitaRepository $repository;
    private UdaliFayliUnitaUseCase $useCase;

    protected function setUp(): void
    {
        $this->fileStorage = new FakeFileStorage();
        $this->repository = new InMemoryFaylUnitaRepository();
        $this->useCase = new UdaliFayliUnitaUseCase($this->repository, $this->fileStorage);
    }

    /**
     * @test
     */
    public function udalyayutsya_vse_fayly_tolko_celevogo_unita(): void
    {
        $this->sohranit('file-1', 'unit-1');
        $this->sohranit('file-2', 'unit-1');
        $this->sohranit('file-3', 'unit-2');

        $this->useCase->handle('unit-1');

        $this->assertSame(
            ['units/unit-1/file-1.zip', 'units/unit-1/file-2.zip'],
            $this->fileStorage->deleted
        );
        $this->assertNull($this->repository->findById('file-1'));
        $this->assertNull($this->repository->findById('file-2'));

        // Чужие файлы остаются нетронутыми.
        $this->assertNotNull($this->repository->findById('file-3'));
        $this->assertSame(1, $this->repository->kolichestvo());
    }

    /**
     * @test
     */
    public function povtornoe_udalenie_ne_privodit_k_oshibke(): void
    {
        $this->sohranit('file-1', 'unit-1');

        $this->useCase->handle('unit-1');
        $this->useCase->handle('unit-1');

        $this->assertSame(['units/unit-1/file-1.zip'], $this->fileStorage->deleted);
        $this->assertSame(0, $this->repository->kolichestvo());
    }

    /**
     * @test
     */
    public function pustoi_unit_id_otklonyaetsya(): void
    {
        $this->expectException(DomainException::class);
        $this->expectExceptionMessage('mediateka.unit_id_is_empty');

        $this->useCase->handle('  ');
    }

    private function sohranit(string $fileId, string $unitId): void
    {
        $this->repository->save(new FaylUnita(
            $fileId,
            $unitId,
            'dump.zip',
            100,
            FaylUnita::MIME_TYPE_ZIP,
            sprintf('units/%s/%s.zip', $unitId, $fileId),
            'user-1',
            time()
        ));
    }
}
