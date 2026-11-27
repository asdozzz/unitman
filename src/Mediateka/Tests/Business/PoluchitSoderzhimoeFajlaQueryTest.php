<?php

declare(strict_types=1);

namespace App\Mediateka\Tests\Business;

use App\Mediateka\Business\Model\FaylUnita;
use App\Mediateka\Business\UseCase\PoluchitSoderzhimoeFajlaQuery;
use App\Mediateka\Tests\Support\FakeFileStorage;
use App\Mediateka\Tests\Support\InMemoryFaylUnitaRepository;
use DomainException;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

/**
 * Чтение содержимого файла: отсутствие, превышение лимита,
 * недопустимая кодировка и успешный путь.
 */
final class PoluchitSoderzhimoeFajlaQueryTest extends TestCase
{
    private InMemoryFaylUnitaRepository $repository;
    private FakeFileStorage $fileStorage;
    private PoluchitSoderzhimoeFajlaQuery $query;

    protected function setUp(): void
    {
        $this->repository = new InMemoryFaylUnitaRepository();
        $this->fileStorage = new FakeFileStorage();
        $this->query = new PoluchitSoderzhimoeFajlaQuery($this->repository, $this->fileStorage);
    }

    #[Test]
    public function otsutstvuyushchij_fajl_otklonyaetsya(): void
    {
        $this->expectException(DomainException::class);
        $this->expectExceptionMessage('mediateka.file.not_found');

        $this->query->handle('missing', 100);
    }

    #[Test]
    public function prevyshenie_max_bytes_otklonyaetsya(): void
    {
        $this->sohranit('file-1', 'ten-bytes!', 10);

        $this->expectException(DomainException::class);
        $this->expectExceptionMessage('mediateka.file.too_large');

        $this->query->handle('file-1', 5);
    }

    #[Test]
    public function nevalidnyj_utf8_otklonyaetsya(): void
    {
        $this->sohranit('file-1', "\xC3\x28", 2);

        $this->expectException(DomainException::class);
        $this->expectExceptionMessage('mediateka.file.content_is_invalid');

        $this->query->handle('file-1', 100);
    }

    #[Test]
    public function schityvaet_soderzhimoe_s_ogranicheniem(): void
    {
        $this->sohranit('file-1', 'hello', 5);

        $content = $this->query->handle('file-1', 100);

        $this->assertSame('hello', $content);
        $this->assertSame('objects/file-1.txt', $this->fileStorage->lastDownload['key']);
        $this->assertSame(100, $this->fileStorage->lastDownload['maxBytes']);
    }

    private function sohranit(string $fileId, string $content, int $size): void
    {
        $key = 'objects/' . $fileId . '.txt';

        $this->repository->save(new FaylUnita(
            $fileId,
            null,
            'note.txt',
            $size,
            'text/plain',
            $key,
            'account-1',
            1000,
            null,
            'ws-1',
            FaylUnita::KIND_WORKSPACE,
            null,
            'turn-1',
        ));

        $this->fileStorage->downloadContent[$key] = $content;
    }
}
