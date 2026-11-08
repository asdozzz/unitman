<?php

declare(strict_types=1);

namespace App\Mediateka\Tests\Business;

use App\Mediateka\Business\Command\ZagruzitFaylUnita;
use App\Mediateka\Business\Model\FaylUnita;
use App\Mediateka\Business\UseCase\ZagruzitFaylUnitaUseCase;
use App\Mediateka\Tests\Support\FakeFileStorage;
use App\Mediateka\Tests\Support\FixedGuidGenerator;
use App\Mediateka\Tests\Support\InMemoryFaylUnitaRepository;
use DomainException;
use PHPUnit\Framework\TestCase;

/**
 * Загрузка файла юнита: только zip-архив, не больше лимита (200 МБ в проде).
 */
final class ZagruzitFaylUnitaUseCaseTest extends TestCase
{
    private const MAX_FILE_SIZE = 200 * 1024 * 1024;

    private FakeFileStorage $fileStorage;
    private InMemoryFaylUnitaRepository $repository;
    private ZagruzitFaylUnitaUseCase $useCase;

    protected function setUp(): void
    {
        $this->fileStorage = new FakeFileStorage();
        $this->repository = new InMemoryFaylUnitaRepository();
        $this->useCase = new ZagruzitFaylUnitaUseCase(
            $this->repository,
            $this->fileStorage,
            new FixedGuidGenerator('file-id-1'),
            self::MAX_FILE_SIZE
        );
    }

    /**
     * @test
     */
    public function zip_arhiv_sohranyaetsya_v_hranilishe_i_reestre(): void
    {
        $content = "PK\x03\x04" . str_repeat('a', 96);

        $file = $this->useCase->handle($this->komanda($content));

        $this->assertSame('file-id-1', $file->getId());
        $this->assertSame('unit-1', $file->getUnitId());
        $this->assertSame('dump.zip', $file->getName());
        $this->assertSame(strlen($content), $file->getSize());
        $this->assertSame(FaylUnita::MIME_TYPE_ZIP, $file->getMimeType());
        $this->assertSame('units/unit-1/file-id-1.zip', $file->getStorageKey());

        $this->assertArrayHasKey('units/unit-1/file-id-1.zip', $this->fileStorage->saved);
        $this->assertSame(
            ['size' => strlen($content), 'contentType' => FaylUnita::MIME_TYPE_ZIP],
            $this->fileStorage->saved['units/unit-1/file-id-1.zip']
        );

        $saved = $this->repository->getById('file-id-1');
        $this->assertSame('units/unit-1/file-id-1.zip', $saved->getStorageKey());
    }

    /**
     * @test
     */
    public function pustoi_zip_arhiv_prinimaetsya(): void
    {
        // "PK\x05\x06" — конец центрального каталога, валидный пустой архив.
        $content = "PK\x05\x06" . str_repeat("\0", 18);

        $file = $this->useCase->handle($this->komanda($content));

        $this->assertSame(strlen($content), $file->getSize());
    }

    /**
     * @test
     */
    public function ne_zip_otklonyaetsya(): void
    {
        $this->expectException(DomainException::class);
        $this->expectExceptionMessage('mediateka.upload.file_is_not_zip');

        $this->useCase->handle($this->komanda('обычный текст, не архив'));
    }

    /**
     * @test
     */
    public function pustoi_fayl_otklonyaetsya(): void
    {
        $this->expectException(DomainException::class);
        $this->expectExceptionMessage('mediateka.upload.file_is_empty');

        $this->useCase->handle($this->komanda(''));
    }

    /**
     * @test
     */
    public function fayl_bolshe_limita_otklonyaetsya(): void
    {
        $this->expectException(DomainException::class);
        $this->expectExceptionMessage('mediateka.upload.file_is_too_large');

        $this->useCase->handle($this->komanda("PK\x03\x04", self::MAX_FILE_SIZE + 1));
    }

    /**
     * @test
     */
    public function content_length_bolshe_limita_otklonyaetsya_do_chteniya_potoka(): void
    {
        $this->expectException(DomainException::class);
        $this->expectExceptionMessage('mediateka.upload.file_is_too_large');

        $this->useCase->handle(new ZagruzitFaylUnita(
            'unit-1',
            'dump.zip',
            10,
            self::MAX_FILE_SIZE + 1,
            'user-1',
            $this->potok("PK\x03\x04" . str_repeat('a', 6))
        ));
    }

    /**
     * @test
     */
    public function pustoie_imya_otklonyaetsya(): void
    {
        $this->expectException(DomainException::class);
        $this->expectExceptionMessage('mediateka.upload.name_is_empty');

        $this->useCase->handle(new ZagruzitFaylUnita(
            'unit-1',
            '   ',
            10,
            null,
            'user-1',
            $this->potok("PK\x03\x04" . str_repeat('a', 6))
        ));
    }

    /**
     * @test
     */
    public function slishkom_dlinnoe_imya_otklonyaetsya(): void
    {
        $this->expectException(DomainException::class);
        $this->expectExceptionMessage('mediateka.upload.name_is_too_long');

        $this->useCase->handle(new ZagruzitFaylUnita(
            'unit-1',
            str_repeat('a', ZagruzitFaylUnitaUseCase::NAME_MAX_LENGTH + 1),
            10,
            null,
            'user-1',
            $this->potok("PK\x03\x04" . str_repeat('a', 6))
        ));
    }

    /**
     * @test
     */
    public function bez_unit_id_sohranyaetsya_kak_chernovik(): void
    {
        $content = "PK\x03\x04" . str_repeat('a', 96);

        $file = $this->useCase->handle(new ZagruzitFaylUnita(
            '  ',
            'dump.zip',
            strlen($content),
            null,
            'user-1',
            $this->potok($content)
        ));

        $this->assertNull($file->getUnitId());
        $this->assertTrue($file->esliChernovik());
        $this->assertSame('drafts/file-id-1.zip', $file->getStorageKey());
        $this->assertArrayHasKey('drafts/file-id-1.zip', $this->fileStorage->saved);
        $this->assertSame('drafts/file-id-1.zip', $this->repository->getById('file-id-1')->getStorageKey());
    }

    /**
     * @test
     */
    public function arhiv_proekta_sohranyaetsya_v_papke_proekta(): void
    {
        $content = "PK\x03\x04" . str_repeat('a', 96);

        $file = $this->useCase->handle(new ZagruzitFaylUnita(
            '',
            'volume-db.zip',
            strlen($content),
            null,
            'user-1',
            $this->potok($content),
            'project-1'
        ));

        $this->assertNull($file->getUnitId());
        $this->assertSame('project-1', $file->getProjectId());
        $this->assertFalse($file->esliChernovik());
        $this->assertTrue($file->esliFaylProekta());
        $this->assertSame('projects/project-1/file-id-1.zip', $file->getStorageKey());
        $this->assertArrayHasKey('projects/project-1/file-id-1.zip', $this->fileStorage->saved);
        $this->assertSame(
            'projects/project-1/file-id-1.zip',
            $this->repository->getById('file-id-1')->getStorageKey()
        );
    }

    /**
     * @test
     */
    public function odnovremenno_unit_i_proekt_otklonyaetsya(): void
    {
        $this->expectException(DomainException::class);
        $this->expectExceptionMessage('mediateka.upload.owner_is_ambiguous');

        $content = "PK\x03\x04" . str_repeat('a', 96);

        $this->useCase->handle(new ZagruzitFaylUnita(
            'unit-1',
            'dump.zip',
            strlen($content),
            null,
            'user-1',
            $this->potok($content),
            'project-1'
        ));
    }

    /**
     * @test
     */
    public function imya_fayla_privoditsya_k_pravilam_docker_volume(): void
    {
        $content = "PK\x03\x04" . str_repeat('a', 96);

        $file = $this->useCase->handle(new ZagruzitFaylUnita(
            'unit-1',
            'Дамп БД (v2).ZIP',
            strlen($content),
            null,
            'user-1',
            $this->potok($content)
        ));

        $this->assertSame('damp-bd-v2.zip', $file->getName());
    }

    /**
     * @test
     */
    public function imya_bez_rasshireniya_dopolnyaetsya_zip(): void
    {
        $content = "PK\x03\x04" . str_repeat('a', 96);

        $file = $this->useCase->handle(new ZagruzitFaylUnita(
            'unit-1',
            'Дамп БД',
            strlen($content),
            null,
            'user-1',
            $this->potok($content)
        ));

        $this->assertSame('damp-bd.zip', $file->getName());
    }

    /**
     * @test
     */
    public function chernovik_privyazyvaetsya_k_unitu_bez_kopirovaniya_v_hranilishche(): void
    {
        $content = "PK\x03\x04" . str_repeat('a', 96);
        $file = $this->useCase->handle(new ZagruzitFaylUnita(
            '',
            'dump.zip',
            strlen($content),
            null,
            'user-1',
            $this->potok($content)
        ));

        $privyazanniy = $file->prikrepitKUnitu('unit-7');

        $this->assertSame('unit-7', $privyazanniy->getUnitId());
        $this->assertFalse($privyazanniy->esliChernovik());
        $this->assertSame($file->getStorageKey(), $privyazanniy->getStorageKey());
    }

    private function komanda(string $content, ?int $size = null): ZagruzitFaylUnita
    {
        return new ZagruzitFaylUnita(
            'unit-1',
            'dump.zip',
            $size ?? strlen($content),
            null,
            'user-1',
            $this->potok($content)
        );
    }

    /**
     * @return resource
     */
    private function potok(string $content)
    {
        $stream = fopen('php://temp', 'r+b');
        $this->assertIsResource($stream);
        fwrite($stream, $content);
        rewind($stream);

        return $stream;
    }
}
