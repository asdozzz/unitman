<?php

declare(strict_types=1);

namespace App\Mediateka\Tests\Business;

use App\Mediateka\Business\Command\ZavershitZagruzkuFayla;
use App\Mediateka\Business\Model\ZagruzkaFaylaToken;
use App\Mediateka\Business\UseCase\ZavershitZagruzkuFaylaUseCase;
use App\Mediateka\Business\Utils\UploadTokenCodec;
use App\Mediateka\Tests\Support\FakeFileStorage;
use App\Mediateka\Tests\Support\InMemoryFaylUnitaRepository;
use DomainException;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

/**
 * Завершение прямой загрузки: подпись, срок, наличие объекта и его размер.
 */
final class ZavershitZagruzkuFaylaUseCaseTest extends TestCase
{
    private const SECRET = 'test-secret';
    private const STORAGE_KEY = 'units/unit-1/file-1.zip';

    private FakeFileStorage $fileStorage;
    private InMemoryFaylUnitaRepository $repository;
    private UploadTokenCodec $tokenCodec;
    private ZavershitZagruzkuFaylaUseCase $useCase;

    protected function setUp(): void
    {
        $this->fileStorage = new FakeFileStorage();
        $this->repository = new InMemoryFaylUnitaRepository();
        $this->tokenCodec = new UploadTokenCodec(self::SECRET);
        $this->useCase = new ZavershitZagruzkuFaylaUseCase(
            $this->repository,
            $this->fileStorage,
            $this->tokenCodec
        );
    }

    #[Test]
    public function validnii_token_i_sovpadenie_razmera_sohranyayut_fayl(): void
    {
        $this->zaregistrirovatObekt(100);

        $file = $this->useCase->handle(new ZavershitZagruzkuFayla($this->token()));

        $this->assertSame('file-1', $file->getId());
        $this->assertSame('unit-1', $file->getUnitId());
        $this->assertNull($file->getProjectId());
        $this->assertSame('damp-bd.zip', $file->getName());
        $this->assertSame(100, $file->getSize());
        $this->assertSame('application/zip', $file->getMimeType());
        $this->assertSame(self::STORAGE_KEY, $file->getStorageKey());
        $this->assertSame('user-1', $file->getUploadedBy());

        $saved = $this->repository->getById('file-1');
        $this->assertSame(self::STORAGE_KEY, $saved->getStorageKey());
        $this->assertSame(1, $this->repository->kolichestvo());
    }

    #[Test]
    public function projektnii_fail_sohranyaetsya_s_project_id(): void
    {
        $this->zaregistrirovatObekt(100);

        $file = $this->useCase->handle(new ZavershitZagruzkuFayla(
            $this->token(projectId: 'project-1', unitId: null)
        ));

        $this->assertNull($file->getUnitId());
        $this->assertSame('project-1', $file->getProjectId());
    }

    #[Test]
    public function istekshii_token_otklonyaetsya(): void
    {
        $this->zaregistrirovatObekt(100);

        $this->expectException(DomainException::class);
        $this->expectExceptionMessage('mediateka.upload.token_expired');

        $this->useCase->handle(new ZavershitZagruzkuFayla(
            $this->token(expiresAt: time() - 1)
        ));
    }

    #[Test]
    public function poddelannii_token_otklonyaetsya(): void
    {
        $this->zaregistrirovatObekt(100);

        $this->expectException(DomainException::class);
        $this->expectExceptionMessage('mediateka.upload.token_invalid');

        $this->useCase->handle(new ZavershitZagruzkuFayla($this->token() . 'x'));
    }

    #[Test]
    public function nekorrektnii_format_tokena_otklonyaetsya(): void
    {
        $this->expectException(DomainException::class);
        $this->expectExceptionMessage('mediateka.upload.token_invalid');

        $this->useCase->handle(new ZavershitZagruzkuFayla('not-a-token'));
    }

    #[Test]
    public function otsutstvuyushchii_obekt_otklonyaetsya(): void
    {
        $this->expectException(DomainException::class);
        $this->expectExceptionMessage('mediateka.upload.file_is_missing');

        $this->useCase->handle(new ZavershitZagruzkuFayla($this->token()));
    }

    #[Test]
    public function nesovpadenie_razmera_otklonyaetsya(): void
    {
        $this->zaregistrirovatObekt(99);

        $this->expectException(DomainException::class);
        $this->expectExceptionMessage('mediateka.upload.size_mismatch');

        $this->useCase->handle(new ZavershitZagruzkuFayla($this->token()));
    }

    private function zaregistrirovatObekt(int $size): void
    {
        $this->fileStorage->statObjects[self::STORAGE_KEY] = [
            'size' => $size,
            'contentType' => 'application/zip',
        ];
    }

    private function token(
        ?string $unitId = 'unit-1',
        ?string $projectId = null,
        ?int $expiresAt = null
    ): string {
        return $this->tokenCodec->encode(new ZagruzkaFaylaToken(
            'file-1',
            $unitId,
            $projectId,
            'damp-bd.zip',
            100,
            'application/zip',
            self::STORAGE_KEY,
            'user-1',
            $expiresAt ?? time() + 600
        ));
    }
}
