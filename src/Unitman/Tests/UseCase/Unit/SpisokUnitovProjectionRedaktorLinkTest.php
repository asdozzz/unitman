<?php

namespace App\Unitman\Tests\UseCase\Unit;

use App\Unitman\Business\Command\Project\AddProject;
use App\Unitman\Business\Model\Project;
use App\Unitman\Business\Model\Unit\Event\KonfigUnitaUstanovlen;
use App\Unitman\Business\Model\Unit\Event\OshibkaOstanovkiUnitaUstanovlena;
use App\Unitman\Business\Model\Unit\Event\OshibkaPodgotovkiUnitaUstanovlena;
use App\Unitman\Business\Model\Unit\Event\OshibkaSborkiUnitaUstanovlena;
use App\Unitman\Business\Model\Unit\Event\OshibkaZapuskaUnitaUstanovlena;
use App\Unitman\Business\Model\Unit\Event\PodgotovkaUnitaNachalas;
use App\Unitman\Business\Model\Unit\Event\SborkaUnitNachalas;
use App\Unitman\Business\Model\Unit\Event\SbrosPodgotovkiNachalsya;
use App\Unitman\Business\Model\Unit\Event\UspehOstanovkiUnitaUstanovlen;
use App\Unitman\Business\Model\Unit\Event\UspehPodgotovkiUnitaUstanovlen;
use App\Unitman\Business\Model\Unit\Event\UspehSborkiUnitaUstanovlen;
use App\Unitman\Business\Model\Unit\Event\UspehSbrosaPodgotovkiUnitaUstanovlen;
use App\Unitman\Business\Model\Unit\Event\UspehZapuskaUnitaUstanovlen;
use App\Runner\Tests\Support\InMemoryUnitRunnerRepository;
use App\Unitman\Business\Port\Project\ProjectRepository;
use App\Unitman\Business\Port\RunnerPublicAddressProvider;
use App\Unitman\Business\Port\UnitmanSecurityService;
use App\Unitman\Business\ReadModel\Unit\SpisokUnitovReadModel;
use App\Unitman\Infra\Projection\SpisokUnitovProjection;
use App\Unitman\Infra\Repository\Unit\SpisokUnitovRepository;
use EventSauce\EventSourcing\Message;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Test;

/**
 * Жизненный цикл ссылки на read-only редактор в read-модели списка юнитов.
 *
 * Ссылка появляется после успешной подготовки и очищается при любом событии,
 * которое инвалидирует контейнер. Запуск и остановка приложения её не меняют.
 */
final class SpisokUnitovProjectionRedaktorLinkTest extends AbstractUnitUseCase
{
    private const UNIT_ID = 'unit-1';

    private const PROJECT_ID = 'project-1';

    private const PUBLIC_ADDRESS = 'https://runner-1.example.com';

    private SpisokUnitovRepository $repository;

    private SpisokUnitovProjection $projection;

    public function setUp(): void
    {
        parent::setUp();

        $this->repository = self::$container->get(SpisokUnitovRepository::class);
        $this->repository->init();
        $this->repository->truncate();

        $project = Project::addProject(
            self::PROJECT_ID,
            new AddProject('repo-1', 'my-app-code', 'my-app', 'master', memoryLimit: 500),
            'author-1'
        );

        $projectRepository = $this->createMock(ProjectRepository::class);
        $projectRepository->method('getById')->willReturn($project);

        $proxyProvider = $this->createMock(RunnerPublicAddressProvider::class);
        $proxyProvider->method('getPublicAddress')->with('runner-1')->willReturn(self::PUBLIC_ADDRESS);

        $this->projection = new SpisokUnitovProjection(
            $this->repository,
            $projectRepository,
            $this->createMock(UnitmanSecurityService::class),
            new InMemoryUnitRunnerRepository([self::UNIT_ID => 'runner-1']),
            $proxyProvider,
        );
    }

    #[Test]
    function uspeshnaya_podgotovka_zapolnyaet_ssylku_redaktora(): void
    {
        $this->sozdatReadModel();

        $this->podgotovkaUspehna();

        $this->assertSame(
            'https://47000.feature-123.my-app.runner-1.example.com',
            $this->repository->getById(self::UNIT_ID)->redaktorLink
        );
    }

    /**
     * @param callable(string, array): object $sozdatSobitie
     */
    #[Test]
    #[DataProvider('sobitiyaOchistki')]
    function sobitie_ochishchaet_ssylku_redaktora(callable $sozdatSobitie): void
    {
        $this->sozdatReadModel();
        $this->podgotovkaUspehna();
        $this->assertNotNull($this->repository->getById(self::UNIT_ID)->redaktorLink);

        $this->projection->handle(new Message($sozdatSobitie(self::UNIT_ID, $this->prozess())));

        $this->assertNull($this->repository->getById(self::UNIT_ID)->redaktorLink);
    }

    /**
     * @return array<string, array{callable(string, array): object}>
     */
    static function sobitiyaOchistki(): array
    {
        $sobitie = static fn (string $klass) => static fn (string $unitId, array $prozess): object
            => new $klass($unitId, 'job-1', ['commands' => []], $prozess);

        return [
            'подготовка началась' => [$sobitie(PodgotovkaUnitaNachalas::class)],
            'ошибка подготовки' => [$sobitie(OshibkaPodgotovkiUnitaUstanovlena::class)],
            'сброс подготовки начался' => [$sobitie(SbrosPodgotovkiNachalsya::class)],
            'сброс подготовки завершён' => [$sobitie(UspehSbrosaPodgotovkiUnitaUstanovlen::class)],
            'сборка началась' => [
                static fn (string $unitId, array $prozess): object
                    => new SborkaUnitNachalas($unitId, 'job-1', ['commands' => []], 1, $prozess),
            ],
            'ошибка сборки' => [$sobitie(OshibkaSborkiUnitaUstanovlena::class)],
            'сборка завершена' => [$sobitie(UspehSborkiUnitaUstanovlen::class)],
        ];
    }

    #[Test]
    function konfig_s_ws_portom_stroit_ssylki_cherez_proxy(): void
    {
        $this->sozdatReadModel();

        $this->projection->handle(new Message(new KonfigUnitaUstanovlen(self::UNIT_ID, [
            'services' => [
                'web' => [
                    'ports' => [
                        ['port' => 80, 'type' => 'http', 'startUri' => '/test'],
                        ['port' => 8080, 'type' => 'ws', 'startUri' => '/socket'],
                        ['port' => 5043, 'type' => 'tcp'],
                    ],
                ],
            ],
        ])));

        // ws через https-proxy становится wss, tcp остаётся прямым на юнит.
        // assertEquals: после JSON-сериализации порядок ключей в link может отличаться.
        $this->assertEquals(
            [
                [
                    'service' => 'web',
                    'path' => 'https://80.feature-123.my-app.runner-1.example.com/test',
                    'port' => 80,
                    'protocol' => 'http',
                    'startUri' => '/test',
                ],
                [
                    'service' => 'web',
                    'path' => 'wss://8080.feature-123.my-app.runner-1.example.com/socket',
                    'port' => 8080,
                    'protocol' => 'ws',
                    'startUri' => '/socket',
                ],
                [
                    'service' => 'web',
                    'path' => 'tcp://feature-123.my-app:5043',
                    'port' => 5043,
                    'protocol' => 'tcp',
                    'startUri' => null,
                ],
            ],
            $this->repository->getById(self::UNIT_ID)->links
        );
    }

    /**
     * @param callable(string, array): object $sozdatSobitie
     */
    #[Test]
    #[DataProvider('sobitiyaBezIzmeneniya')]
    function zapusk_i_ostanovka_ne_menyayut_ssylku_redaktora(callable $sozdatSobitie): void
    {
        $this->sozdatReadModel();
        $this->podgotovkaUspehna();

        $this->projection->handle(new Message($sozdatSobitie(self::UNIT_ID, $this->prozess())));

        $this->assertSame(
            'https://47000.feature-123.my-app.runner-1.example.com',
            $this->repository->getById(self::UNIT_ID)->redaktorLink
        );
    }

    /**
     * @return array<string, array{callable(string, array): object}>
     */
    static function sobitiyaBezIzmeneniya(): array
    {
        $sobitie = static fn (string $klass) => static fn (string $unitId, array $prozess): object
            => new $klass($unitId, 'job-1', ['commands' => []], $prozess);

        return [
            'успешный запуск' => [$sobitie(UspehZapuskaUnitaUstanovlen::class)],
            'ошибка запуска' => [$sobitie(OshibkaZapuskaUnitaUstanovlena::class)],
            'успешная остановка' => [$sobitie(UspehOstanovkiUnitaUstanovlen::class)],
            'ошибка остановки' => [$sobitie(OshibkaOstanovkiUnitaUstanovlena::class)],
        ];
    }

    private function sozdatReadModel(): void
    {
        $this->repository->insert(new SpisokUnitovReadModel(
            self::UNIT_ID,
            'author-1',
            'author@example.com',
            'feature-123',
            self::PROJECT_ID,
            'my-app',
            'feature/123',
            [],
            []
        ));
    }

    private function podgotovkaUspehna(): void
    {
        $this->projection->handle(new Message(new UspehPodgotovkiUnitaUstanovlen(
            self::UNIT_ID,
            'job-1',
            ['commands' => []],
            $this->prozess()
        )));
    }

    private function prozess(): array
    {
        return [
            'id' => 'prozes-1',
            'userId' => 'author-1',
            'type' => 'PODGOTOVKA',
            'state' => 'SUCCESS',
            'jobs' => [],
        ];
    }
}
