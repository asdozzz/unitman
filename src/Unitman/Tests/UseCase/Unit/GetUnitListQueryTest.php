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

final class GetUnitListQueryTest extends AbstractUnitUseCase
{
    function dataProviderGetUnitList()
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
        $unit1 = new SpisokUnitovReadModel('1', '5', 'asd@asd.ru', '001', 'pr1', 'prName1', 'master', Sobran::CODE, false);
        $readModelRepo->insert($unit1);
        $unit2 = new SpisokUnitovReadModel('2', '5', 'asd@asd.ru', '002', 'pr1', 'prName1', 'master', Sobran::CODE, false);
        $readModelRepo->insert($unit2);
        $unit3 = new SpisokUnitovReadModel('3', '6', 'test@asd.ru', '003', 'pr1', 'prName1', 'feature/001', Sobran::CODE, false);
        $readModelRepo->insert($unit3);
        $unit4 = new SpisokUnitovReadModel('4', '6', 'test@asd.ru', '004', 'pr1', 'prName1', 'feature/001', Sobran::CODE, false);
        $readModelRepo->insert($unit4);
        $unit5 = new SpisokUnitovReadModel('5', '7', 'www@asd.ru', '003', 'pr2', 'prName2', 'feature/001', Sobran::CODE, false);
        $readModelRepo->insert($unit5);
        $unit6 = new SpisokUnitovReadModel('6', '7', 'www@asd.ru', '006', 'pr2', 'prName2', 'feature/001', Sobran::CODE, false);
        $readModelRepo->insert($unit6);
        $unit6 = new SpisokUnitovReadModel('7', '7', 'www@asd.ru', '007', 'pr3', 'prName2', 'feature/001', Sobran::CODE, false);
        $readModelRepo->insert($unit6);

        $securityService = $this->getMockBuilder(UnitmanSecurityService::class)->getMock();
        $securityService->expects($this->any())->method('getCurrentUserId')->willReturn($currentUserId);
        self::$container->set(UnitmanSecurityService::class, $securityService);

        $spisokProektovRepo = $this->getMockBuilder(UmeetPoluchatSpisokProektovDlyPolzovatelya::class)->getMock();
        $nastroikiHukaProektov = new ProjectList\NastroikiHukaProekta(false, true, true);
        $spisokProektovRepo->expects($this->any())->method('poluchitSpisokProektovDlyPolzovatelya')->willReturn([
            new ProjectList('pr1', 'repoId', 'pr1Code', 'pr1Name', 'asd', true, ProjectListStateType::BUILD_SUCCESS, $nastroikiHukaProektov),
            new ProjectList('pr2', 'repoId', 'pr2Code', 'pr2Name', 'asd', true, ProjectListStateType::BUILD_SUCCESS, $nastroikiHukaProektov),
        ]);
        self::$container->set(UmeetPoluchatSpisokProektovDlyPolzovatelya::class, $spisokProektovRepo);


        $useCase = self::$container->get(GetUnitListQuery::class);
        /** @var GetUnitListQuery $useCase*/
        $command = new GetUnitList($filter);
        $actual = $useCase->handle($command);

        $this->assertSame($resultIds, array_map(fn(SpisokUnitovReadModel $readModel) => $readModel->id, $actual));
    }
}
