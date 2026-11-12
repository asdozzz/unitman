<?php

declare(strict_types=1);

namespace App\Mediateka\Tests\Business;

use App\Mediateka\Business\Model\FaylUnita;
use App\Mediateka\Business\Port\FileLinkChecker;
use App\Mediateka\Business\UseCase\UdalitFaylUseCase;
use App\Mediateka\Tests\Support\FakeFileStorage;
use App\Mediateka\Tests\Support\InMemoryFaylUnitaRepository;
use DomainException;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

/**
 * Удаление файла медиатеки: связанный с внешней сущностью (например,
 * с типом тома проекта) файл удалять нельзя — иначе каталог останется
 * со ссылкой на несуществующий архив.
 */
final class UdalitFaylUseCaseTest extends TestCase
{
    private FakeFileStorage $fileStorage;
    private InMemoryFaylUnitaRepository $repository;

    protected function setUp(): void
    {
        $this->fileStorage = new FakeFileStorage();
        $this->repository = new InMemoryFaylUnitaRepository();
    }

    #[Test]
    public function svyazannii_fayl_udalit_nelzya(): void
    {
        $this->sohranit('file-1', null, 'project-1');

        $useCase = new UdalitFaylUseCase(
            $this->repository,
            $this->fileStorage,
            [$this->checker(['file-1'])]
        );

        $this->expectException(DomainException::class);
        $this->expectExceptionMessage('mediateka.file.is_linked');

        $useCase->handle('file-1');
    }

    #[Test]
    public function nesvyazannii_fayl_udaetsya_iz_hranilishcha_i_reestra(): void
    {
        $this->sohranit('file-1', null, 'project-1');

        $useCase = new UdalitFaylUseCase(
            $this->repository,
            $this->fileStorage,
            [$this->checker([])]
        );

        $useCase->handle('file-1');

        $this->assertSame(['projects/project-1/file-1.zip'], $this->fileStorage->deleted);
        $this->assertNull($this->repository->findById('file-1'));
    }

    #[Test]
    public function bez_chekerov_udalenie_razresheno(): void
    {
        $this->sohranit('file-1', 'unit-1', null);

        $useCase = new UdalitFaylUseCase($this->repository, $this->fileStorage);

        $useCase->handle('file-1');

        $this->assertSame(['units/unit-1/file-1.zip'], $this->fileStorage->deleted);
        $this->assertSame(0, $this->repository->kolichestvo());
    }

    #[Test]
    public function udalit_bez_proverki_ignoriruet_svyazi(): void
    {
        $this->sohranit('file-1', null, 'project-1');

        $useCase = new UdalitFaylUseCase(
            $this->repository,
            $this->fileStorage,
            [$this->checker(['file-1'])]
        );

        $useCase->udalitBezProverki('file-1');

        $this->assertSame(['projects/project-1/file-1.zip'], $this->fileStorage->deleted);
        $this->assertNull($this->repository->findById('file-1'));
    }

    private function sohranit(string $fileId, ?string $unitId, ?string $projectId): void
    {
        $key = $unitId !== null
            ? sprintf('units/%s/%s.zip', $unitId, $fileId)
            : sprintf('projects/%s/%s.zip', (string) $projectId, $fileId);

        $this->repository->save(new FaylUnita(
            $fileId,
            $unitId,
            'dump.zip',
            100,
            FaylUnita::MIME_TYPE_ZIP,
            $key,
            'user-1',
            time(),
            $projectId
        ));
    }

    /**
     * @param string[] $linkedFileIds
     */
    private function checker(array $linkedFileIds): FileLinkChecker
    {
        return new class ($linkedFileIds) implements FileLinkChecker {
            /**
             * @param string[] $linkedFileIds
             */
            public function __construct(private readonly array $linkedFileIds)
            {
            }

            public function esliFaylPrivyazan(string $fileId): bool
            {
                return in_array($fileId, $this->linkedFileIds, true);
            }
        };
    }
}
