<?php

namespace App\Unitman\Tests\UseCase\Unit;

use App\Unitman\Business\Command\Unit\SozdatUnit;
use App\Unitman\Business\Model\Account;
use App\Unitman\Business\Model\Unit\Runner\RunnerJobType;
use App\Unitman\Business\Model\Unit\UnitProcess\UnitProcessType;
use App\Unitman\Business\Model\Unit\VariableValue;
use App\Unitman\Business\Model\Unit\PeremennayaSvyaz;
use App\Unitman\Business\Port\CanGeneateGuid;
use App\Unitman\Business\Port\MediatekaService;
use App\Unitman\Business\Port\Project\ProjectRepository;
use App\Unitman\Business\Port\Project\UmeetPoluchatSpisokProektovDlyPolzovatelya;
use App\Unitman\Business\Port\Unit\UnitRepository;
use App\Unitman\Business\Port\UnitmanSecurityService;
use App\Unitman\Business\Port\VolumeType\VolumeTypeFileRepository;
use App\Unitman\Business\UseCase\Unit\ProverkaFaylovVolumov;
use App\Unitman\Business\UseCase\Unit\ProverkaSvyazeyPeremenih;
use App\Unitman\Business\UseCase\Unit\SozdatUnitSystemoiUseCase;
use App\Unitman\Business\UseCase\Unit\SozdatUnitUseCase;
use App\Unitman\Infra\Adapter\MemoryGuidGenerator;
use App\Unitman\Infra\Repository\Unit\ProzesUnitaRepository;
use App\Unitman\Infra\Repository\Unit\SpisokUnitovRepository;
use App\Unitman\Infra\Repository\Unit\SvyaziUnitovRepository;
use Ramsey\Uuid\Uuid;

final class SozdanieUnitaTest extends AbstractUnitUseCase
{
    function test()
    {
        $unitId = Uuid::uuid7()->toString();
        $prozesId = Uuid::uuid7()->toString();
        $unitId2 = Uuid::uuid7()->toString();
        $unitId3 = Uuid::uuid7()->toString();
        $userId = Uuid::uuid7()->toString();
        $securityService = $this->mokaemUspehSecurity($userId);
        $projectId = Uuid::uuid7()->toString();

        $guidGenerator = new MemoryGuidGenerator([$projectId]);
        self::$container->set(CanGeneateGuid::class, $guidGenerator);

        $project = $this->stubProekta($projectId, $userId);
        $projectRepository = self::$container->get(ProjectRepository::class);
        /** @var $projectRepository ProjectRepository*/
        $projectRepository->save($project);

        $spisokUnitovRepo = self::$container->get(SpisokUnitovRepository::class);
        /** @var SpisokUnitovRepository $spisokUnitovRepo */

        $unitRepo = self::$container->get(UnitRepository::class);
        /** @var $unitRepo UnitRepository*/

        /** @var MediatekaService $mediatekaService */
        $mediatekaService = $this->getMockBuilder(MediatekaService::class)->getMock();
        $volumeTypeFileRepository = $this->getMockBuilder(VolumeTypeFileRepository::class)->getMock();

        $useCase2 = new SozdatUnitUseCase(
            $spisokUnitovRepo,
            $projectRepository,
            $securityService,
            new MemoryGuidGenerator([$unitId,$prozesId,$unitId2,$unitId3]),
            $unitRepo,
            $mediatekaService,
            new ProverkaFaylovVolumov($mediatekaService, $volumeTypeFileRepository),
            new ProverkaSvyazeyPeremenih($spisokUnitovRepo),
            $this->stubDostupnieProekti([$projectId])
        );
        $useCase2->handle(new SozdatUnit(
            $projectId,
            'unit-1',
            'feature/123',
            [
                new SozdatUnit\ZnacheniePeremenoi('VARIABLE_1', 'VALUE_1', 'string'),
                new SozdatUnit\ZnacheniePeremenoi('VARIABLE_2', 'VALUE_2', 'integer')
            ],
            500
        ));

        $unit = $unitRepo->getById($unitId);
        $this->assertEquals([
            new VariableValue('VARIABLE_1', 'VALUE_1', 'string'),
            new VariableValue('VARIABLE_2', 'VALUE_2', 'integer'),
        ],$unit->poluchitZnacheniyaPeremenih());

        $prozessRepo = self::$container->get(ProzesUnitaRepository::class);
        /** @var $prozessRepo ProzesUnitaRepository*/

        $prozesi = $prozessRepo->poluchitProzesiPoIdUnita($unitId);

        $this->assertEquals(UnitProcessType::SBORKA->value, $prozesi[0]->type);
        $this->assertEquals(RunnerJobType::SBORKA->value, $prozesi[0]->jobs[0]->type);
        $this->assertEquals(RunnerJobType::PODGOTOVKA->value, $prozesi[0]->jobs[1]->type);
        $this->assertEquals(RunnerJobType::ZAPUSK->value, $prozesi[0]->jobs[2]->type);

        $this->expectExceptionMessage('unit.name_already_exists');
        $useCase2->handle(new SozdatUnit(
            $projectId,
            'unit-1',
            'feature/222'
        ));

        $this->expectExceptionMessage('unit.name_invalid');
        $useCase2->handle(new SozdatUnit(
            $projectId,
            'unit 1',
            'feature/123'
        ));

        $this->expectExceptionMessage('unit.name_invalid');
        $useCase2->handle(new SozdatUnit(
            $projectId,
            'мультирасчет 23',
            'feature/123'
        ));
    }

    /**
     * Тома из запроса на создание юнита связываются с медиатекой по `fileId`.
     * Тома без файла (только каталог) медиатеку не затрагивают.
     *
     * @test
     * */
    function toma_privyazivayutsya_k_mediateke_po_fileId()
    {
        $unitId = Uuid::uuid7()->toString();
        $prozesId = Uuid::uuid7()->toString();
        $userId = Uuid::uuid7()->toString();
        $securityService = $this->mokaemUspehSecurity($userId);
        $projectId = Uuid::uuid7()->toString();

        $guidGenerator = new MemoryGuidGenerator([$projectId]);
        self::$container->set(CanGeneateGuid::class, $guidGenerator);

        $project = $this->stubProekta($projectId, $userId);
        $projectRepository = self::$container->get(ProjectRepository::class);
        /** @var $projectRepository ProjectRepository*/
        $projectRepository->save($project);

        $spisokUnitovRepo = self::$container->get(SpisokUnitovRepository::class);
        /** @var SpisokUnitovRepository $spisokUnitovRepo */

        $unitRepo = self::$container->get(UnitRepository::class);
        /** @var $unitRepo UnitRepository*/

        $mediatekaService = $this->getMockBuilder(MediatekaService::class)->getMock();
        $mediatekaService->expects($this->once())
            ->method('poluchitVladelcevFaylov')
            ->with(['file-1', 'file-2'])
            ->willReturn([
                'file-1' => ['unitId' => null, 'projectId' => null],
                'file-2' => ['unitId' => null, 'projectId' => null],
            ]);
        $mediatekaService->expects($this->once())
            ->method('prikrepitFaylyKUnitu')
            ->with(['file-1', 'file-2'], $unitId);

        $volumeTypeFileRepository = $this->getMockBuilder(VolumeTypeFileRepository::class)->getMock();
        $volumeTypeFileRepository->expects($this->once())
            ->method('najtiKodyTipovPoFaylam')
            ->with($projectId, ['file-1', 'file-2'])
            ->willReturn([]);

        $useCase = new SozdatUnitUseCase(
            $spisokUnitovRepo,
            $projectRepository,
            $securityService,
            new MemoryGuidGenerator([$unitId, $prozesId]),
            $unitRepo,
            $mediatekaService,
            new ProverkaFaylovVolumov($mediatekaService, $volumeTypeFileRepository),
            new ProverkaSvyazeyPeremenih($spisokUnitovRepo),
            $this->stubDostupnieProekti([$projectId])
        );

        $useCase->handle(new SozdatUnit(
            $projectId,
            'unit-with-volumes',
            'feature/123',
            [],
            3000,
            [
                new SozdatUnit\VolumeZapros('/var/www/db', 'БД', 'file-1'),
                new SozdatUnit\VolumeZapros('/var/www/web', 'Фронт', 'file-2'),
                new SozdatUnit\VolumeZapros('/var/www/logs', 'Логи'),
            ]
        ));

        $unit = $unitRepo->getById($unitId);
        $volumes = $unit->poluchitVolumeUnita();

        $this->assertCount(3, $volumes);
        $this->assertSame('file-1', $volumes[0]->getFileId());
        $this->assertSame('file-2', $volumes[1]->getFileId());
        $this->assertNull($volumes[2]->getFileId());
    }

    /**
     * @test
     * */
    function avtosozdanie()
    {
        $unitId = Uuid::uuid7()->toString();
        $prozesId = Uuid::uuid7()->toString();
        $userId = Uuid::uuid7()->toString();
        $securityService = $this->getMockBuilder(UnitmanSecurityService::class)->getMock();
        $account = new Account($userId, 'asd@asd.ru', true);
        $securityService->expects($this->atLeastOnce())->method('getUserById')->willReturn($account);
        $securityService->expects($this->atLeastOnce())->method('getSystemUser')->willReturn($account);
        self::$container->set(UnitmanSecurityService::class, $securityService);
        $projectId = Uuid::uuid7()->toString();

        $guidGenerator = new MemoryGuidGenerator([$projectId]);
        self::$container->set(CanGeneateGuid::class, $guidGenerator);

        $project = $this->stubProekta($projectId, $userId);
        $projectRepository = self::$container->get(ProjectRepository::class);
        /** @var $projectRepository ProjectRepository*/
        $projectRepository->save($project);

        $spisokUnitovRepo = self::$container->get(SpisokUnitovRepository::class);
        /** @var SpisokUnitovRepository $spisokUnitovRepo */

        $unitRepo = self::$container->get(UnitRepository::class);
        /** @var $unitRepo UnitRepository*/

        $useCase2 = new SozdatUnitSystemoiUseCase($spisokUnitovRepo, $projectRepository, $securityService, new MemoryGuidGenerator([$unitId, $prozesId]), $unitRepo);
        $useCase2->handle(
            $projectId,
            'feature/123'
        );

        $prozessRepo = self::$container->get(ProzesUnitaRepository::class);
        /** @var $prozessRepo ProzesUnitaRepository*/

        $prozesi = $prozessRepo->poluchitProzesiPoIdUnita($unitId);
        $this->assertEquals(UnitProcessType::SBORKA->value, $prozesi[0]->type);
        $this->assertEquals(RunnerJobType::SBORKA->value, $prozesi[0]->jobs[0]->type);
        $this->assertEquals(RunnerJobType::PODGOTOVKA->value, $prozesi[0]->jobs[1]->type);
        $this->assertEquals(RunnerJobType::ZAPUSK->value, $prozesi[0]->jobs[2]->type);
    }

    /**
     * Связь переменной с юнитом, выбранным через хелпер, фиксируется при
     * создании юнита: в агрегате, в read-модели списка и в проекции связей.
     *
     * @test
     * */
    function svyazi_peredayutsya_v_read_model_i_proekciyu()
    {
        $targetUnitId = Uuid::uuid7()->toString();
        $targetProzesId = Uuid::uuid7()->toString();
        $unitId = Uuid::uuid7()->toString();
        $prozesId = Uuid::uuid7()->toString();
        $userId = Uuid::uuid7()->toString();
        $projectId = Uuid::uuid7()->toString();
        $securityService = $this->mokaemUspehSecurity($userId);

        $project = $this->stubProekta($projectId, $userId);
        $projectRepository = self::$container->get(ProjectRepository::class);
        /** @var ProjectRepository $projectRepository */
        $projectRepository->save($project);

        $spisokUnitovRepo = self::$container->get(SpisokUnitovRepository::class);
        /** @var SpisokUnitovRepository $spisokUnitovRepo */
        $unitRepo = self::$container->get(UnitRepository::class);
        /** @var UnitRepository $unitRepo */

        $mediatekaService = $this->getMockBuilder(MediatekaService::class)->getMock();
        $volumeTypeFileRepository = $this->getMockBuilder(VolumeTypeFileRepository::class)->getMock();

        $useCase = new SozdatUnitUseCase(
            $spisokUnitovRepo,
            $projectRepository,
            $securityService,
            new MemoryGuidGenerator([$targetUnitId, $targetProzesId, $unitId, $prozesId]),
            $unitRepo,
            $mediatekaService,
            new ProverkaFaylovVolumov($mediatekaService, $volumeTypeFileRepository),
            new ProverkaSvyazeyPeremenih($spisokUnitovRepo),
            $this->stubDostupnieProekti([$projectId])
        );

        $useCase->handle(new SozdatUnit($projectId, 'target-unit', 'feature/123', [], 500));
        $useCase->handle(new SozdatUnit(
            $projectId,
            'source-unit',
            'feature/123',
            [
                new SozdatUnit\ZnacheniePeremenoi('ORDER_SERVICE', $targetUnitId, 'unit', $targetUnitId),
            ],
            500
        ));

        $sourceUnit = $unitRepo->getById($unitId);
        $this->assertEquals(
            [new PeremennayaSvyaz('ORDER_SERVICE', $targetUnitId)],
            $sourceUnit->poluchitSvyaziPeremenih()
        );

        $sourceReadModel = $spisokUnitovRepo->getById($unitId);
        $this->assertSame(
            $this->uporyadochennieSvyazi([
                [
                    'variableId' => 'ORDER_SERVICE',
                    'targetUnitId' => $targetUnitId,
                    'projectId' => '',
                    'service' => '',
                    'port' => 0,
                    'suffix' => null,
                    'type' => 'http',
                ],
            ]),
            $this->uporyadochennieSvyazi($sourceReadModel->svyazi)
        );

        $svyaziRepo = self::$container->get(SvyaziUnitovRepository::class);
        /** @var SvyaziUnitovRepository $svyaziRepo */
        $svyaziRepo->init();
        $this->assertSame([
            [
                'variableId' => 'ORDER_SERVICE',
                'targetUnitId' => $targetUnitId,
                'projectId' => $projectId,
            ],
        ], $svyaziRepo->poluchitPoIdUnita($unitId));
    }

    /**
     * Postgres jsonb не сохраняет порядок ключей, поэтому сравниваем наборы
     * ключей независимо от порядка.
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
