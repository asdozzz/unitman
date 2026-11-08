<?php

namespace App\Unitman\Tests\UseCase\Unit;

use App\Unitman\Business\Command\Unit\GetUnitList;
use App\Unitman\Business\Command\Unit\GetUnitList\GetUnitListFilter;
use App\Unitman\Business\Model\Unit\State\Sobran;
use App\Unitman\Business\Port\Project\UmeetPoluchatSpisokProektovDlyPolzovatelya;
use App\Unitman\Business\Port\UnitmanSecurityService;
use App\Unitman\Business\ReadModel\ProjectList;
use App\Unitman\Business\ReadModel\ProjectList\ProjectListStateType;
use App\Unitman\Business\ReadModel\Unit\SpisokUnitovReadModel;
use App\Unitman\Business\UseCase\Unit\GetUnitListQuery;
use App\Unitman\Infra\Repository\Unit\SpisokUnitovRepository;
use App\Unitman\Infra\Repository\Unit\SvyaziUnitovRepository;

final class GetUnitListQueryTest extends AbstractUnitUseCase
{
    static function dataProviderGetUnitList()
    {
        yield [new GetUnitListFilter(true), '6', ['4', '3']];
        yield [new GetUnitListFilter(true), '7', ['6', '5']];
        yield [new GetUnitListFilter(name: '001'), '6', ['1']];
        yield [new GetUnitListFilter(name: '01'), '6', ['1']];
        yield [new GetUnitListFilter(name: '002'), '6', ['2']];
        yield [new GetUnitListFilter(name: '00'), '6', ['6', '5','4','3','2','1']];
        yield [new GetUnitListFilter(branch: 'master'), '6', ['2', '1']];
        yield [new GetUnitListFilter(branch: 'ster'), '6', ['2', '1']];
        yield [new GetUnitListFilter(branch:  'feature/001'), '6', ['6', '5','4','3']];
        yield [new GetUnitListFilter(branch:  'feature'), '6', ['6', '5','4','3']];
        yield [new GetUnitListFilter(branch:  '001'), '6', ['6', '5','4','3']];
        yield [new GetUnitListFilter(projectId:  'pr1'), '6', ['4', '3','2','1']];
        yield [new GetUnitListFilter(), '6', ['6', '5','4','3','2','1']];

        // Фильтр по unitIds расширяется связанными юнитами в обе стороны.
        // Связи: 3 -> 5 (pr2), 5 -> 2 (pr1), 7 (pr3) -> 1 (pr1).
        yield 'сам юнит и его связь' => [new GetUnitListFilter(unitIds: ['3']), '6', ['5', '3']];
        yield 'связь в обе стороны' => [new GetUnitListFilter(unitIds: ['5']), '6', ['5', '3', '2']];
        yield 'два юнита в фильтре' => [new GetUnitListFilter(unitIds: ['3', '4']), '6', ['5', '4', '3']];
        yield 'входящая связь' => [new GetUnitListFilter(unitIds: ['2']), '6', ['5', '2']];
        yield 'юнит без связей' => [new GetUnitListFilter(unitIds: ['6']), '6', ['6']];
        yield 'источник в недоступном проекте' => [new GetUnitListFilter(unitIds: ['7']), '6', ['1']];
        yield 'только переданный юнит' => [new GetUnitListFilter(unitIds: ['1']), '6', ['1']];
    }

    /**
     * @test
     * @dataProvider dataProviderGetUnitList
     * */
    function handle(GetUnitListFilter $filter, string $currentUserId, array $resultIds): void
    {
        $readModelRepo = self::$container->get(SpisokUnitovRepository::class);
        /** @var SpisokUnitovRepository $readModelRepo*/
        $readModelRepo->truncate();
        $unit1 = new SpisokUnitovReadModel('1', '5', 'asd@asd.ru', '001', 'pr1', 'prName1', 'master', [], []);
        $readModelRepo->insert($unit1);
        $unit2 = new SpisokUnitovReadModel('2', '5', 'asd@asd.ru', '002', 'pr1', 'prName1', 'master', [], []);
        $readModelRepo->insert($unit2);
        $unit3 = new SpisokUnitovReadModel('3', '6', 'test@asd.ru', '003', 'pr1', 'prName1', 'feature/001', [], []);
        $readModelRepo->insert($unit3);
        $unit4 = new SpisokUnitovReadModel('4', '6', 'test@asd.ru', '004', 'pr1', 'prName1', 'feature/001', [], []);
        $readModelRepo->insert($unit4);
        $unit5 = new SpisokUnitovReadModel('5', '7', 'www@asd.ru', '003', 'pr2', 'prName2', 'feature/001', [], []);
        $readModelRepo->insert($unit5);
        $unit6 = new SpisokUnitovReadModel('6', '7', 'www@asd.ru', '006', 'pr2', 'prName2', 'feature/001', [], []);
        $readModelRepo->insert($unit6);
        $unit6 = new SpisokUnitovReadModel('7', '7', 'www@asd.ru', '007', 'pr3', 'prName2', 'feature/001', [], []);
        $readModelRepo->insert($unit6);

        $svyaziRepo = self::$container->get(SvyaziUnitovRepository::class);
        /** @var SvyaziUnitovRepository $svyaziRepo */
        $svyaziRepo->init();
        $svyaziRepo->truncate();
        $svyaziRepo->replaceForUnit('3', [['variableId' => 'VAR_1', 'targetUnitId' => '5', 'projectId' => 'pr2']]);
        $svyaziRepo->replaceForUnit('5', [['variableId' => 'VAR_1', 'targetUnitId' => '2', 'projectId' => 'pr1']]);
        $svyaziRepo->replaceForUnit('7', [['variableId' => 'VAR_1', 'targetUnitId' => '1', 'projectId' => 'pr1']]);

        $securityService = $this->getMockBuilder(UnitmanSecurityService::class)->getMock();
        $securityService->expects($this->any())->method('getCurrentUserId')->willReturn($currentUserId);
        self::$container->set(UnitmanSecurityService::class, $securityService);

        $spisokProektovRepo = $this->getMockBuilder(UmeetPoluchatSpisokProektovDlyPolzovatelya::class)->getMock();
        $nastroikiHukaProektov = ['avtosozdanie' => false, 'avtoobnovlenie' => true, 'avtoudalenie' => true];
        $spisokProektovRepo->expects($this->any())->method('poluchitSpisokProektovDlyPolzovatelya')->willReturn([
            ['id' => 'pr1', 'repoId' => 'repoId', 'code' => 'pr1Code', 'name' => 'pr1Name', 'mainBranch' => 'asd', 'isActive' => true, 'state' => ProjectListStateType::BUILD_SUCCESS->value, 'nastroikiHukaProektov' => $nastroikiHukaProektov],
            ['id' => 'pr2', 'repoId' => 'repoId', 'code' => 'pr2Code', 'name' => 'pr2Name', 'mainBranch' => 'asd', 'isActive' => true, 'state' => ProjectListStateType::BUILD_SUCCESS->value, 'nastroikiHukaProektov' => $nastroikiHukaProektov],
        ]);
        self::$container->set(UmeetPoluchatSpisokProektovDlyPolzovatelya::class, $spisokProektovRepo);


        $useCase = self::$container->get(GetUnitListQuery::class);
        /** @var GetUnitListQuery $useCase*/
        $command = new GetUnitList($filter);
        $actual = $useCase->handle($command);

        $this->assertSame($resultIds, array_map(fn(array $readModel) => $readModel['id'], $actual));
    }
}
