<?php

declare(strict_types=1);

namespace App\Mediateka\Tests\Business;

use App\Mediateka\Business\Command\PodgotovitZagruzkuFayla;
use App\Mediateka\Business\UseCase\PodgotovitZagruzkuFaylaUseCase;
use App\Mediateka\Business\Utils\UploadTokenCodec;
use App\Mediateka\Tests\Support\FakeFileStorage;
use App\Mediateka\Tests\Support\FixedGuidGenerator;
use DomainException;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

/**
 * Подготовка прямой загрузки: presigned PUT URL и подписанный токен.
 */
final class PodgotovitZagruzkuFaylaUseCaseTest extends TestCase
{
    private const MAX_FILE_SIZE = 200 * 1024 * 1024;
    private const PRESIGN_TTL = 600;
    private const SECRET = 'test-secret';

    private FakeFileStorage $fileStorage;
    private UploadTokenCodec $tokenCodec;
    private PodgotovitZagruzkuFaylaUseCase $useCase;

    protected function setUp(): void
    {
        $this->fileStorage = new FakeFileStorage();
        $this->tokenCodec = new UploadTokenCodec(self::SECRET);
        $this->useCase = new PodgotovitZagruzkuFaylaUseCase(
            $this->fileStorage,
            new FixedGuidGenerator('file-id-1'),
            $this->tokenCodec,
            self::MAX_FILE_SIZE,
            self::PRESIGN_TTL
        );
    }

    #[Test]
    public function vozvrashchaet_presigned_url_i_podpisannii_token(): void
    {
        $result = $this->useCase->handle($this->komanda('Дамп БД.zip', 100));

        $this->assertSame('file-id-1', $result->fileId);
        $this->assertSame('units/unit-1/file-id-1.zip', $result->storageKey);
        $this->assertSame(
            'https://storage.test/units/unit-1/file-id-1.zip?X-Amz-Signature=put',
            $result->uploadUrl
        );
        $this->assertSame(['Content-Type' => 'application/zip'], $result->headers);

        $payload = $result->toArray();
        $this->assertSame('PUT', $payload['method']);
        $this->assertSame('file-id-1', $payload['fileId']);
        $this->assertSame('units/unit-1/file-id-1.zip', $payload['storageKey']);
        $this->assertSame('application/zip', $payload['headers']['Content-Type']);
        $this->assertNotSame('', $result->uploadToken);
        $this->assertGreaterThan(time(), $result->expiresAt);

        $this->assertCount(1, $this->fileStorage->presignedPutCalls);
        $this->assertSame('units/unit-1/file-id-1.zip', $this->fileStorage->presignedPutCalls[0]['key']);
        $this->assertSame(100, $this->fileStorage->presignedPutCalls[0]['size']);
        $this->assertSame('application/zip', $this->fileStorage->presignedPutCalls[0]['contentType']);
        $this->assertSame(self::PRESIGN_TTL, $this->fileStorage->presignedPutCalls[0]['ttl']);

        $token = $this->tokenCodec->decode($result->uploadToken);
        $this->assertSame('file-id-1', $token->fileId);
        $this->assertSame('unit-1', $token->unitId);
        $this->assertNull($token->projectId);
        $this->assertSame('damp-bd.zip', $token->name);
        $this->assertSame(100, $token->size);
        $this->assertSame('user-1', $token->uploadedBy);
        $this->assertSame($result->expiresAt, $token->expiresAt);
    }

    #[Test]
    public function arhiv_proekta_kladyotsya_v_papku_proekta(): void
    {
        $result = $this->useCase->handle(new PodgotovitZagruzkuFayla(
            '',
            'project-1',
            'volume-db.zip',
            100,
            'application/zip',
            'user-1'
        ));

        $this->assertSame('projects/project-1/file-id-1.zip', $result->storageKey);
        $this->assertSame('project-1', $this->tokenCodec->decode($result->uploadToken)->projectId);
    }

    #[Test]
    public function chernovik_kladyotsya_v_papku_drafts(): void
    {
        $result = $this->useCase->handle(new PodgotovitZagruzkuFayla(
            '',
            '',
            'dump.zip',
            100,
            'application/zip',
            'user-1'
        ));

        $this->assertSame('drafts/file-id-1.zip', $result->storageKey);
    }

    #[Test]
    public function fayl_rovno_limitu_prinimaetsya(): void
    {
        $result = $this->useCase->handle($this->komanda('dump.zip', self::MAX_FILE_SIZE));

        $this->assertSame(self::MAX_FILE_SIZE, $this->fileStorage->presignedPutCalls[0]['size']);
        $this->assertSame(self::MAX_FILE_SIZE, $this->tokenCodec->decode($result->uploadToken)->size);
    }

    #[Test]
    public function fayl_bolshe_limita_otklonyaetsya(): void
    {
        $this->expectException(DomainException::class);
        $this->expectExceptionMessage('mediateka.upload.file_is_too_large');

        $this->useCase->handle($this->komanda('dump.zip', self::MAX_FILE_SIZE + 1));
    }

    #[Test]
    public function pustoi_fayl_otklonyaetsya(): void
    {
        $this->expectException(DomainException::class);
        $this->expectExceptionMessage('mediateka.upload.file_is_empty');

        $this->useCase->handle($this->komanda('dump.zip', 0));
    }

    #[Test]
    public function odnovremenno_unit_i_proekt_otklonyaetsya(): void
    {
        $this->expectException(DomainException::class);
        $this->expectExceptionMessage('mediateka.upload.owner_is_ambiguous');

        $this->useCase->handle(new PodgotovitZagruzkuFayla(
            'unit-1',
            'project-1',
            'dump.zip',
            100,
            'application/zip',
            'user-1'
        ));
    }

    #[Test]
    public function pustoie_imya_otklonyaetsya(): void
    {
        $this->expectException(DomainException::class);
        $this->expectExceptionMessage('mediateka.upload.name_is_empty');

        $this->useCase->handle($this->komanda('   ', 100));
    }

    #[Test]
    public function slishkom_dlinnoe_imya_otklonyaetsya(): void
    {
        $this->expectException(DomainException::class);
        $this->expectExceptionMessage('mediateka.upload.name_is_too_long');

        $this->useCase->handle($this->komanda(str_repeat('a', 256), 100));
    }

    #[Test]
    public function ne_zip_content_type_otklonyaetsya(): void
    {
        $this->expectException(DomainException::class);
        $this->expectExceptionMessage('mediateka.upload.file_is_not_zip');

        $this->useCase->handle($this->komanda('dump.zip', 100, 'application/octet-stream'));
    }

    #[Test]
    public function pustoi_content_type_trakutuetsya_kak_zip(): void
    {
        $result = $this->useCase->handle($this->komanda('dump.zip', 100, ''));

        $this->assertSame('application/zip', $result->headers['Content-Type']);
    }

    private function komanda(string $name, int $size, string $contentType = 'application/zip'): PodgotovitZagruzkuFayla
    {
        return new PodgotovitZagruzkuFayla(
            'unit-1',
            '',
            $name,
            $size,
            $contentType,
            'user-1'
        );
    }
}
