<?php

declare(strict_types=1);

namespace App\Mediateka\Tests\Business;

use App\Mediateka\Business\Model\FaylUnita;
use App\Mediateka\Business\UseCase\ZaregistrirovatFajlHodaUseCase;
use App\Mediateka\Business\Utils\DopuskaemyeFajlyHoda;
use App\Mediateka\Tests\Support\FakeFileStorage;
use App\Mediateka\Tests\Support\FixedGuidGenerator;
use App\Mediateka\Tests\Support\InMemoryFaylUnitaRepository;
use DomainException;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

/**
 * Регистрация артефакта хода: нормализация имени, проверка содержимого,
 * идемпотентность по паре (turnId, name) и владелец workspace.
 */
final class ZaregistrirovatFajlHodaUseCaseTest extends TestCase
{
    private InMemoryFaylUnitaRepository $repository;
    private FakeFileStorage $fileStorage;
    private ZaregistrirovatFajlHodaUseCase $useCase;

    protected function setUp(): void
    {
        $this->repository = new InMemoryFaylUnitaRepository();
        $this->fileStorage = new FakeFileStorage();
        $this->useCase = new ZaregistrirovatFajlHodaUseCase(
            $this->repository,
            $this->fileStorage,
            new FixedGuidGenerator('file-id-1'),
            new DopuskaemyeFajlyHoda(1000),
        );
    }

    #[Test]
    public function imya_normalizuetsya_i_mime_beretsya_iz_rasshireniya(): void
    {
        $file = $this->zaregistrirovat('docs/../Report.MD', '# Report');

        $this->assertSame('Report.md', $file->getName());
        $this->assertSame('text/markdown', $file->getMimeType());
        $this->assertSame('text/markdown', $this->fileStorage->saved[$file->getStorageKey()]['contentType']);
    }

    #[Test]
    public function zapreshchennoe_rasshirenie_otklonyaetsya(): void
    {
        $this->expectException(DomainException::class);
        $this->expectExceptionMessage('mediateka.file.type_is_forbidden');

        $this->zaregistrirovat('script.php', '<?php echo 1;');
    }

    #[Test]
    public function nevalidnyj_utf8_otklonyaetsya(): void
    {
        $this->expectException(DomainException::class);
        $this->expectExceptionMessage('mediateka.file.content_is_invalid');

        $this->zaregistrirovat('note.txt', "\xC3\x28");
    }

    #[Test]
    public function nul_bajt_v_soderzhimom_otklonyaetsya(): void
    {
        $this->expectException(DomainException::class);
        $this->expectExceptionMessage('mediateka.file.content_is_invalid');

        $this->zaregistrirovat('note.txt', "a\0b");
    }

    #[Test]
    public function pustoe_soderzhimoe_otklonyaetsya(): void
    {
        $this->expectException(DomainException::class);
        $this->expectExceptionMessage('mediateka.workspace.file_is_empty');

        $this->zaregistrirovat('note.txt', '');
    }

    #[Test]
    public function prevyshenie_limita_fajla_otklonyaetsya(): void
    {
        $this->expectException(DomainException::class);
        $this->expectExceptionMessage('mediateka.workspace.file_is_too_large');

        $this->zaregistrirovat('note.txt', str_repeat('a', 1001));
    }

    #[Test]
    public function povtornaya_registraciya_vozvrashchaet_tot_zhe_fajl(): void
    {
        $first = $this->zaregistrirovat('note.md', '# one');
        $second = $this->zaregistrirovat('note.md', '# two');

        $this->assertSame($first->getId(), $second->getId());
        $this->assertSame($first->getStorageKey(), $second->getStorageKey());
        $this->assertSame(1, $this->repository->kolichestvo());
        $this->assertCount(1, $this->fileStorage->saved);
    }

    #[Test]
    public function fajl_privyazyvaetsya_k_workspace_i_hodu(): void
    {
        $file = $this->zaregistrirovat('note.md', '# hi', 'ws-1', 'turn-1', 12345);

        $this->assertSame(FaylUnita::KIND_WORKSPACE, $file->getKind());
        $this->assertSame('ws-1', $file->getWorkspaceId());
        $this->assertSame('turn-1', $file->getOpencodeTurnId());
        $this->assertTrue($file->esliFajlHoda());
        $this->assertSame(12345, $file->getAppliedAt());
        $this->assertSame(12345, $file->getCreatedAt());
    }

    #[Test]
    public function klyuch_hranilishcha_soderzhit_workspace(): void
    {
        $this->zaregistrirovat('note.md', '# hi', 'ws-1');

        $keys = array_keys($this->fileStorage->saved);
        $this->assertCount(1, $keys);
        $this->assertStringStartsWith('workspaces/ws-1/', $keys[0]);
    }

    private function zaregistrirovat(
        string $name,
        string $content,
        string $workspaceId = 'ws-1',
        string $turnId = 'turn-1',
        int $now = 1000,
    ): FaylUnita {
        return $this->useCase->handle(
            $workspaceId,
            'account-1',
            $turnId,
            $name,
            'text/plain',
            $content,
            $now,
        );
    }
}
