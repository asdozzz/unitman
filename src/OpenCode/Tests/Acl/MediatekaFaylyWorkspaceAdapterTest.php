<?php

declare(strict_types=1);

namespace App\OpenCode\Tests\Acl;

use App\Mediateka\Api\MediatekaApi;
use App\Mediateka\Business\Port\CanGenerateGuid;
use App\Mediateka\Business\UseCase\PoluchitSoderzhimoeFajlaQuery;
use App\Mediateka\Business\UseCase\PoluchitSsylkuDlyaFaylaQuery;
use App\Mediateka\Business\UseCase\UdaliFajlyWorkspaceUseCase;
use App\Mediateka\Business\UseCase\ZaregistrirovatFajlHodaUseCase;
use App\Mediateka\Business\Utils\DopuskaemyeFajlyHoda;
use App\Mediateka\Tests\Support\FakeFileStorage;
use App\Mediateka\Tests\Support\InMemoryFaylUnitaRepository;
use App\OpenCode\Acl\MediatekaFaylyWorkspaceAdapter;
use App\OpenCode\Business\ReadModel\FajlHodaReadModel;
use DomainException;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

/**
 * ACL-адаптер файлов хода: 1:1 делегирование в MediatekaApi и конвертация
 * `FaylUnita` в доменную read-модель OpenCode.
 */
final class MediatekaFaylyWorkspaceAdapterTest extends TestCase
{
    private InMemoryFaylUnitaRepository $repository;
    private FakeFileStorage $fileStorage;
    private MediatekaFaylyWorkspaceAdapter $adapter;

    protected function setUp(): void
    {
        $this->repository = new InMemoryFaylUnitaRepository();
        $this->fileStorage = new FakeFileStorage();
        $this->adapter = new MediatekaFaylyWorkspaceAdapter($this->mediatekaApi());
    }

    #[Test]
    public function registraciya_fajla_hoda_vozvrashchaet_read_model(): void
    {
        $file = $this->adapter->zaregistrirovatFajlHoda(
            'ws-1',
            'account-1',
            'turn-1',
            'plan.md',
            'text/markdown',
            '# План',
            12345,
        );

        self::assertSame('file-id-1', $file->fileId);
        self::assertSame('turn-1', $file->turnId);
        self::assertSame('ws-1', $file->workspaceId);
        self::assertSame('plan.md', $file->name);
        self::assertSame(10, $file->size);
        self::assertSame('text/markdown', $file->contentType);
        self::assertSame(12345, $file->createdAt);
        self::assertTrue($file->podderzhivaetProsmotr());
        self::assertSame(
            [
                'fileId' => 'file-id-1',
                'name' => 'plan.md',
                'size' => 10,
                'contentType' => 'text/markdown',
            ],
            $file->toVlozhenie(),
        );
    }

    #[Test]
    public function poisk_fajla_hoda_vozvrashchaet_null_dlya_chuzhogo_hoda(): void
    {
        $file = $this->zaregistrirovat('plan.md', '# План', 'turn-1');

        self::assertNull($this->adapter->poluchitFajlHoda('turn-2', $file->fileId));
        self::assertNull($this->adapter->poluchitFajlHoda('turn-1', 'unknown'));

        $najdennyj = $this->adapter->poluchitFajlHoda('turn-1', $file->fileId);

        self::assertInstanceOf(FajlHodaReadModel::class, $najdennyj);
        self::assertSame($file->fileId, $najdennyj->fileId);
        self::assertSame('plan.md', $najdennyj->name);
    }

    #[Test]
    public function statistika_fajlov_hoda_peredayotsya_bez_izmenenij(): void
    {
        $this->zaregistrirovat('one.md', '12345', 'turn-1');
        $this->zaregistrirovat('two.md', '1234567', 'turn-1');

        self::assertSame(
            ['count' => 2, 'totalSize' => 12],
            $this->adapter->poluchitStatistikuFajlovHoda('turn-1'),
        );
    }

    #[Test]
    public function soderzhimoe_fajla_peredayotsya_bez_izmenenij(): void
    {
        $file = $this->zaregistrirovat('note.txt', 'привет', 'turn-1');

        $storageKey = $this->repository->getById($file->fileId)->getStorageKey();
        $this->fileStorage->downloadContent[$storageKey] = 'привет';

        self::assertSame(
            'привет',
            $this->adapter->poluchitSoderzhimoeFajla($file->fileId, 100),
        );
    }

    #[Test]
    public function ssylka_est_tolko_dlya_fajla_etogo_hoda(): void
    {
        $file = $this->zaregistrirovat('plan.md', '# План', 'turn-1');

        $storageKey = $this->repository->getById($file->fileId)->getStorageKey();

        self::assertNull($this->adapter->poluchitSsylkuDlyaFajlaHoda('turn-2', $file->fileId));
        self::assertSame(
            'https://storage.test/' . ltrim($storageKey, '/'),
            $this->adapter->poluchitSsylkuDlyaFajlaHoda('turn-1', $file->fileId),
        );
    }

    #[Test]
    public function udalenie_fajlov_workspace_ubiraet_ikh_iz_reestra(): void
    {
        $this->zaregistrirovat('plan.md', '# План', 'turn-1');

        $this->adapter->udalitFajlyWorkspace('ws-1');

        self::assertSame(0, $this->repository->kolichestvo());
    }

    #[Test]
    public function domain_exception_mediateki_prokhodit_bez_izmenenij(): void
    {
        $this->expectException(DomainException::class);
        $this->expectExceptionMessage('mediateka.file.type_is_forbidden');

        $this->adapter->zaregistrirovatFajlHoda(
            'ws-1',
            'account-1',
            'turn-1',
            'script.php',
            'application/octet-stream',
            '<?php echo 1;',
            12345,
        );
    }

    private function zaregistrirovat(string $name, string $content, string $turnId): FajlHodaReadModel
    {
        return $this->adapter->zaregistrirovatFajlHoda(
            'ws-1',
            'account-1',
            $turnId,
            $name,
            'text/plain',
            $content,
            12345,
        );
    }

    /**
     * Собирает MediatekaApi из реальных use case'ов поверх in-memory портов.
     *
     * Класс `MediatekaApi` финальный и имеет 15 зависимостей; для теста нужны
     * только шесть файловых методов, поэтому остальные свойства не инициализируются.
     */
    private function mediatekaApi(): MediatekaApi
    {
        $reflection = new \ReflectionClass(MediatekaApi::class);
        /** @var MediatekaApi $api */
        $api = $reflection->newInstanceWithoutConstructor();

        $guid = new class implements CanGenerateGuid {
            private int $next = 1;

            public function makeGuid(): string
            {
                return 'file-id-' . $this->next++;
            }
        };

        $this->setProperty($api, 'faylUnitaRepository', $this->repository);
        $this->setProperty($api, 'zaregistrirovatFajlHodaUseCase', new ZaregistrirovatFajlHodaUseCase(
            $this->repository,
            $this->fileStorage,
            $guid,
            new DopuskaemyeFajlyHoda(1000),
        ));
        $this->setProperty($api, 'poluchitSoderzhimoeFajlaQuery', new PoluchitSoderzhimoeFajlaQuery(
            $this->repository,
            $this->fileStorage,
        ));
        $this->setProperty($api, 'poluchitSsylkuDlyaFaylaQuery', new PoluchitSsylkuDlyaFaylaQuery(
            $this->repository,
            $this->fileStorage,
        ));
        $this->setProperty($api, 'udaliFajlyWorkspaceUseCase', new UdaliFajlyWorkspaceUseCase(
            $this->repository,
            $this->fileStorage,
        ));

        return $api;
    }

    private function setProperty(MediatekaApi $api, string $name, object $value): void
    {
        $property = new \ReflectionProperty(MediatekaApi::class, $name);
        $property->setValue($api, $value);
    }
}
