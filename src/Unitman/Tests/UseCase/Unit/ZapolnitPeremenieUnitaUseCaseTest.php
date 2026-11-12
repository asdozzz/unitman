<?php

namespace App\Unitman\Tests\UseCase\Unit;

use App\Runner\Api\RunnerApiInterface;
use App\Runner\Business\Model\GolangRunner\Unit\ResultatSbrokiUnita;
use App\Unitman\Acl\MemoryRunnerService;
use App\Unitman\Business\Command\Unit\SozdatUnit;
use App\Unitman\Business\Command\Unit\ZapolnitPeremenieUnita;
use App\Unitman\Business\Command\Unit\ZapolnitPeremenieUnita\SvyazPeremenoi;
use App\Unitman\Business\Model\Unit\Event\KonfigUnitaUstanovlen;
use App\Unitman\Business\Model\Repo\RepoType;
use App\Unitman\Business\Model\Unit\PeremennayaSvyaz;
use App\Unitman\Business\Port\MediatekaService;
use App\Unitman\Business\Port\Project\ProjectRepository;
use App\Unitman\Business\Port\Repo\RepoRepository;
use App\Unitman\Business\Port\Project\UmeetPoluchatSpisokProektovDlyPolzovatelya;
use App\Unitman\Business\Port\Unit\UnitRepository;
use App\Unitman\Business\Port\VolumeType\VolumeTypeFileRepository;
use App\Unitman\Business\UseCase\Unit\ObrabotatProzesiUnitaUseCase;
use App\Unitman\Business\UseCase\Unit\ProverkaFaylovVolumov;
use App\Unitman\Business\UseCase\Unit\ProverkaSvyazeyPeremenih;
use App\Unitman\Business\UseCase\Unit\SozdatUnitUseCase;
use App\Unitman\Business\UseCase\Unit\ZapolnitPeremenieUnitaUseCase;
use App\Unitman\Infra\Adapter\MemoryGuidGenerator;
use App\Unitman\Infra\Projection\SvyaziUnitovProjection;
use App\Unitman\Infra\Repository\Unit\SpisokUnitovRepository;
use App\Unitman\Infra\Repository\Unit\SvyaziUnitovRepository;
use App\Unitman\Infra\Service\RebuildService;
use EventSauce\EventSourcing\Message;
use PHPUnit\Framework\Attributes\Test;
use Ramsey\Uuid\Uuid;
use Symfony\Component\Yaml\Yaml;

/**
 * Связи переменных с юнитами, выбранными через хелпер, при заполнении
 * переменных существующего юнита.
 *
 * Доменную валидацию значений покрывает {@see \App\Unitman\Tests\Unit\Unit\ZapolnitPeremenieUnitaTest},
 * здесь проверяется сквозной сценарий: агрегат, read-модель списка юнитов
 * и проекция `svyazi_unitov`.
 */
final class ZapolnitPeremenieUnitaUseCaseTest extends AbstractUnitUseCase
{
    /**
     * Связь фиксируется в агрегате, в read-модели списка и в проекции.
     */
    #[Test]
    function svyazi_fiksiruyutsya_pri_zapolnenii_peremenih()
    {
        $stend = $this->stend();

        $stend['zapolnit']->handle(new ZapolnitPeremenieUnita(
            $stend['unitId'],
            $this->values($stend['targetUnitId']),
            [],
            [new SvyazPeremenoi('ORDER_SERVICE', $stend['targetUnitId'])]
        ));

        $this->assertEquals(
            [new PeremennayaSvyaz('ORDER_SERVICE', $stend['targetUnitId'])],
            $stend['unitRepo']->getById($stend['unitId'])->poluchitSvyaziPeremenih()
        );

        $readModel = $stend['spisokRepo']->getById($stend['unitId']);
        $this->assertSame(
            $this->uporyadochennieSvyazi([
                [
                    'variableId' => 'ORDER_SERVICE',
                    'targetUnitId' => $stend['targetUnitId'],
                    'projectId' => '',
                    'service' => '',
                    'port' => 0,
                    'suffix' => null,
                    'type' => 'http',
                ],
            ]),
            $this->uporyadochennieSvyazi($readModel->svyazi)
        );

        $this->assertSame([
            [
                'variableId' => 'ORDER_SERVICE',
                'targetUnitId' => $stend['targetUnitId'],
                'projectId' => $stend['projectId'],
            ],
        ], $stend['svyaziRepo']->poluchitPoIdUnita($stend['unitId']));
    }

    /**
     * Пустой список связей означает «связи не меняются».
     */
    #[Test]
    function pustoy_spisok_svyazey_sohranyaet_tekushie()
    {
        $stend = $this->stend();

        $stend['zapolnit']->handle(new ZapolnitPeremenieUnita(
            $stend['unitId'],
            $this->values($stend['targetUnitId']),
            [],
            [new SvyazPeremenoi('ORDER_SERVICE', $stend['targetUnitId'])]
        ));

        $stend['zapolnit']->handle(new ZapolnitPeremenieUnita(
            $stend['unitId'],
            $this->values($stend['targetUnitId'])
        ));

        $this->assertEquals(
            [new PeremennayaSvyaz('ORDER_SERVICE', $stend['targetUnitId'])],
            $stend['unitRepo']->getById($stend['unitId'])->poluchitSvyaziPeremenih()
        );

        $this->assertSame([
            [
                'variableId' => 'ORDER_SERVICE',
                'targetUnitId' => $stend['targetUnitId'],
                'projectId' => $stend['projectId'],
            ],
        ], $stend['svyaziRepo']->poluchitPoIdUnita($stend['unitId']));
    }

    /**
     * Непустой список связей полностью заменяет набор связей юнита.
     */
    #[Test]
    function neupustoy_spisok_svyazey_zamenyaet_nabor()
    {
        $stend = $this->stend();

        $stend['zapolnit']->handle(new ZapolnitPeremenieUnita(
            $stend['unitId'],
            $this->values($stend['targetUnitId']),
            [],
            [new SvyazPeremenoi('ORDER_SERVICE', $stend['targetUnitId'])]
        ));

        $stend['zapolnit']->handle(new ZapolnitPeremenieUnita(
            $stend['unitId'],
            $this->values($stend['target2UnitId']),
            [],
            [new SvyazPeremenoi('ORDER_SERVICE', $stend['target2UnitId'])]
        ));

        $this->assertEquals(
            [new PeremennayaSvyaz('ORDER_SERVICE', $stend['target2UnitId'])],
            $stend['unitRepo']->getById($stend['unitId'])->poluchitSvyaziPeremenih()
        );

        $this->assertSame([
            [
                'variableId' => 'ORDER_SERVICE',
                'targetUnitId' => $stend['target2UnitId'],
                'projectId' => $stend['projectId'],
            ],
        ], $stend['svyaziRepo']->poluchitPoIdUnita($stend['unitId']));
    }

    #[Test]
    function zapret_ssylki_na_sebya()
    {
        $stend = $this->stend(false);

        try {
            $stend['zapolnit']->handle(new ZapolnitPeremenieUnita(
                $stend['unitId'],
                $this->values($stend['unitId']),
                [],
                [new SvyazPeremenoi('ORDER_SERVICE', $stend['unitId'])]
            ));
            $this->fail('Expected DomainException was not thrown');
        } catch (\DomainException $e) {
            $this->assertEquals('unit.svyaz.self_link_is_forbidden', $e->getMessage());
        }
    }

    #[Test]
    function zapret_ssylki_na_nedostupniy_proekt()
    {
        $stend = $this->stend(false, []);

        try {
            $stend['zapolnit']->handle(new ZapolnitPeremenieUnita(
                $stend['unitId'],
                $this->values($stend['targetUnitId']),
                [],
                [new SvyazPeremenoi('ORDER_SERVICE', $stend['targetUnitId'])]
            ));
            $this->fail('Expected DomainException was not thrown');
        } catch (\DomainException $e) {
            $this->assertEquals('unit.svyaz.target_unit_project_is_not_available', $e->getMessage());
        }
    }

    #[Test]
    function oshibka_pri_otsutstvuyuschem_celevom_unite()
    {
        $stend = $this->stend(false);
        $neizvestniyUnitId = Uuid::uuid7()->toString();

        try {
            $stend['zapolnit']->handle(new ZapolnitPeremenieUnita(
                $stend['unitId'],
                $this->values($neizvestniyUnitId),
                [],
                [new SvyazPeremenoi('ORDER_SERVICE', $neizvestniyUnitId)]
            ));
            $this->fail('Expected exception was not thrown');
        } catch (\Exception $e) {
            $this->assertEquals('unit.spisok_unitov.unit_not_found', $e->getMessage());
        }
    }

    /**
     * При смене конфига связи переменных без хелпера отбрасываются.
     */
    #[Test]
    function svyazi_ubirautsya_pri_rekonciliacii_s_konfigom_bez_helpera()
    {
        $stend = $this->stend();

        $stend['zapolnit']->handle(new ZapolnitPeremenieUnita(
            $stend['unitId'],
            $this->values($stend['targetUnitId']),
            [],
            [new SvyazPeremenoi('ORDER_SERVICE', $stend['targetUnitId'])]
        ));

        $this->assertSame([
            [
                'variableId' => 'ORDER_SERVICE',
                'targetUnitId' => $stend['targetUnitId'],
                'projectId' => $stend['projectId'],
            ],
        ], $stend['svyaziRepo']->poluchitPoIdUnita($stend['unitId']));

        $configBezHelpera = Yaml::parseFile(__DIR__.'/data/config_2.yaml');
        $event = new Message(new KonfigUnitaUstanovlen($stend['unitId'], $configBezHelpera));

        /** @var SvyaziUnitovProjection $svyaziProjection */
        $svyaziProjection = self::$container->get(SvyaziUnitovProjection::class);
        $svyaziProjection->handle($event);

        $this->assertSame([], $stend['svyaziRepo']->poluchitPoIdUnita($stend['unitId']));

        /** @var \App\Unitman\Infra\Projection\SpisokUnitovProjection $spisokProjection */
        $spisokProjection = self::$container->get(\App\Unitman\Infra\Projection\SpisokUnitovProjection::class);
        $spisokProjection->handle($event);

        $this->assertSame([], $stend['spisokRepo']->getById($stend['unitId'])->svyazi);
    }

    /**
     * Пересборка проекции по id юнита восстанавливает связи из события.
     */
    #[Test]
    function peresborka_proekcii_vosstanavlivaet_svyazi()
    {
        $stend = $this->stend();

        $stend['zapolnit']->handle(new ZapolnitPeremenieUnita(
            $stend['unitId'],
            $this->values($stend['targetUnitId']),
            [],
            [new SvyazPeremenoi('ORDER_SERVICE', $stend['targetUnitId'])]
        ));

        $stend['svyaziRepo']->deleteByUnit($stend['unitId']);
        $this->assertSame([], $stend['svyaziRepo']->poluchitPoIdUnita($stend['unitId']));

        /** @var RebuildService $rebuildService */
        $rebuildService = self::$container->get(RebuildService::class);
        $rebuildService->handle(SvyaziUnitovProjection::CODE, $stend['unitId']);

        $this->assertSame([
            [
                'variableId' => 'ORDER_SERVICE',
                'targetUnitId' => $stend['targetUnitId'],
                'projectId' => $stend['projectId'],
            ],
        ], $stend['svyaziRepo']->poluchitPoIdUnita($stend['unitId']));
    }

    /**
     * @param string[]|null $dostupnieProekti null — доступен проект стенда,
     *                                         [] — не доступен ни один проект
     */
    private function stend(bool $sobrat = true, ?array $dostupnieProekti = null): array
    {
        $userId = Uuid::uuid7()->toString();
        $securityService = $this->mokaemUspehSecurity($userId);

        $projectId = Uuid::uuid7()->toString();
        $targetUnitId = Uuid::uuid7()->toString();
        $targetProzesId = Uuid::uuid7()->toString();
        $target2UnitId = Uuid::uuid7()->toString();
        $target2ProzesId = Uuid::uuid7()->toString();
        $unitId = Uuid::uuid7()->toString();
        $prozesId = Uuid::uuid7()->toString();

        if ($dostupnieProekti === null) {
            $dostupnieProekti = [$projectId];
        }

        // Репозиторий нужен RunnerAdapter для построения storage-URL при сборке
        // (иначе сборка молча отменяется и конфиг не устанавливается).
        $repoId = Uuid::uuid7()->toString();
        $repo = $this->stubRepo($repoId, RepoType::GITLAB, 'repoName', 'http://repoUrl');
        $repo->accessConfirm();
        /** @var RepoRepository $repoRepository */
        $repoRepository = self::$container->get(RepoRepository::class);
        $repoRepository->save($repo);

        $project = $this->stubProekta($projectId, $userId, 'uwin', $repoId);
        $projectRepository = self::$container->get(ProjectRepository::class);
        /** @var ProjectRepository $projectRepository */
        $projectRepository->save($project);

        $spisokUnitovRepo = self::$container->get(SpisokUnitovRepository::class);
        /** @var SpisokUnitovRepository $spisokUnitovRepo */
        $unitRepo = self::$container->get(UnitRepository::class);
        /** @var UnitRepository $unitRepo */
        $svyaziRepo = self::$container->get(SvyaziUnitovRepository::class);
        /** @var SvyaziUnitovRepository $svyaziRepo */
        $svyaziRepo->init();

        $mediatekaService = $this->getMockBuilder(MediatekaService::class)->getMock();
        $volumeTypeFileRepository = $this->getMockBuilder(VolumeTypeFileRepository::class)->getMock();
        $stubDostupnieProekti = $this->stubDostupnieProekti($dostupnieProekti);

        $zapolnitUseCase = new ZapolnitPeremenieUnitaUseCase(
            $unitRepo,
            $projectRepository,
            $securityService,
            new MemoryGuidGenerator([]),
            $mediatekaService,
            new ProverkaFaylovVolumov($mediatekaService, $volumeTypeFileRepository),
            new ProverkaSvyazeyPeremenih($spisokUnitovRepo),
            $stubDostupnieProekti
        );

        $sozdatUseCase = new SozdatUnitUseCase(
            $spisokUnitovRepo,
            $projectRepository,
            $securityService,
            new MemoryGuidGenerator([
                $targetUnitId,
                $targetProzesId,
                $target2UnitId,
                $target2ProzesId,
                $unitId,
                $prozesId,
            ]),
            $unitRepo,
            $mediatekaService,
            new ProverkaFaylovVolumov($mediatekaService, $volumeTypeFileRepository),
            new ProverkaSvyazeyPeremenih($spisokUnitovRepo),
            $stubDostupnieProekti
        );

        $sozdatUseCase->handle(new SozdatUnit($projectId, 'target-unit-1', 'feature/123', memoryLimit: 300));
        $sozdatUseCase->handle(new SozdatUnit($projectId, 'target-unit-2', 'feature/123', memoryLimit: 300));
        $sozdatUseCase->handle(new SozdatUnit($projectId, 'source-unit', 'feature/123', memoryLimit: 300));

        if ($sobrat) {
            $this->sobratUnit($unitId);
        }

        return [
            'userId' => $userId,
            'projectId' => $projectId,
            'unitId' => $unitId,
            'targetUnitId' => $targetUnitId,
            'target2UnitId' => $target2UnitId,
            'unitRepo' => $unitRepo,
            'spisokRepo' => $spisokUnitovRepo,
            'svyaziRepo' => $svyaziRepo,
            'zapolnit' => $zapolnitUseCase,
        ];
    }

    private function sobratUnit(string $unitId): void
    {
        $memoryRunner = new MemoryRunnerService();
        $stepsSuccess = [
            [
                'Command' => 'command',
                'Response' => 'response',
                'Success' => true,
                'Unixtime' => 123123123,
            ],
        ];

        $memoryRunner->addResponse(MemoryRunnerService::SBORKA_UNITA, 'SBORKA_UNITA');
        $memoryRunner->addResponse(
            MemoryRunnerService::RESULTAT_SBORKI,
            new ResultatSbrokiUnita(true, $stepsSuccess, file_get_contents(__DIR__.'/data/config_1.yaml'))
        );
        self::$container->set(RunnerApiInterface::class, $memoryRunner);

        /** @var ObrabotatProzesiUnitaUseCase $obrabotat */
        $obrabotat = self::$container->get(ObrabotatProzesiUnitaUseCase::class);
        $obrabotat->handle($unitId);
        $obrabotat->handle($unitId);
    }

    /**
     * Значения покрывают все переменные `data/config_1.yaml`.
     *
     * @return array<string, string>
     */
    private function values(string $targetUnitId): array
    {
        return [
            'AUTH_TOKEN' => 'token',
            'DICTIONARY_SERVICE' => 'http://v1.dict.ru',
            'ORDER_SERVICE' => $targetUnitId,
        ];
    }

    /**
     * PostgreSQL `jsonb` не сохраняет порядок ключей, поэтому сравнение
     * read-модели выполняем без учёта порядка.
     *
     * @param array<int, array<string, mixed>> $svyazi
     * @return array<int, array<string, mixed>>
     */
    private function uporyadochennieSvyazi(array $svyazi): array
    {
        return array_map(static function (array $svyaz): array {
            ksort($svyaz);

            return $svyaz;
        }, $svyazi);
    }

    /**
     * @param string[] $projectIds
     */
    private function stubDostupnieProekti(array $projectIds): UmeetPoluchatSpisokProektovDlyPolzovatelya
    {
        $stub = $this->getMockBuilder(UmeetPoluchatSpisokProektovDlyPolzovatelya::class)->getMock();
        $stub->expects($this->any())
            ->method('poluchitSpisokProektovDlyPolzovatelya')
            ->willReturn(array_map(
                static fn(string $projectId): array => ['id' => $projectId],
                $projectIds
            ));

        return $stub;
    }
}
