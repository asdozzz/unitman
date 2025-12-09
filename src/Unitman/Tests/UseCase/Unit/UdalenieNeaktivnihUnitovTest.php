<?php

namespace App\Unitman\Tests\UseCase\Unit;

use App\Runner\Api\RunnerApiInterface;
use App\Runner\Business\Command\NachatPodgotovkuUnita;
use App\Runner\Business\Model\GolangRunner\Unit\ResultatDeistviyaUnita;
use App\Runner\Business\Model\GolangRunner\Unit\ResultatOstanovkiUnita;
use App\Runner\Business\Model\GolangRunner\Unit\ResultatPodgotovkiUnita;
use App\Runner\Business\Model\GolangRunner\Unit\ResultatSbrokiUnita;
use App\Runner\Business\Model\GolangRunner\Unit\ResultatSbrosaPodgotovkiUnita;
use App\Runner\Business\Model\GolangRunner\Unit\ResultatUdaleniyaUnita;
use App\Runner\Business\Model\GolangRunner\Unit\ResultatZapuskaUnita;
use App\Unitman\Acl\MemoryRunnerService;
use App\Unitman\Business\Command\Unit\SozdatUnit;
use App\Unitman\Business\Model\Unit\UnitProcess\UnitProcessType;
use App\Unitman\Business\Model\Unit\VariableValue;
use App\Unitman\Business\Port\CanGeneateGuid;
use App\Unitman\Business\Port\Project\ProjectRepository;
use App\Unitman\Business\Port\Unit\UnitRepository;
use App\Unitman\Business\ReadModel\Unit\SpisokUnitovReadModel;
use App\Unitman\Business\UseCase\Unit\ObrabotatProzesiUnitaUseCase;
use App\Unitman\Business\UseCase\Unit\SozdatUnitUseCase;
use App\Unitman\Business\UseCase\Unit\UdalitNeaktivnieUnitiUseCase;
use App\Unitman\Infra\Adapter\MemoryGuidGenerator;
use App\Unitman\Infra\Repository\Unit\SpisokUnitovRepository;
use App\Utils\Service\TestClockService;
use Psr\Clock\ClockInterface;
use Ramsey\Uuid\Uuid;

final class UdalenieNeaktivnihUnitovTest extends AbstractUnitUseCase
{
    function test()
    {
        $readModelRepo = self::$container->get(SpisokUnitovRepository::class);
        /** @var SpisokUnitovRepository $readModelRepo*/
        $readModelRepo->truncate();

        $unitId = Uuid::uuid7()->toString();
        $unitId2 = Uuid::uuid7()->toString();
        $unitId3 = Uuid::uuid7()->toString();
        $unitId4 = Uuid::uuid7()->toString();
        $prozesSbrokiId = Uuid::uuid7()->toString();
        $prozesSbrokiId2 = Uuid::uuid7()->toString();
        $prozesSbrokiId3 = Uuid::uuid7()->toString();
        $prozesSbrokiId4 = Uuid::uuid7()->toString();
        $prozesUdaleniyaId = Uuid::uuid7()->toString();
        $prozesUdaleniyaId2 = Uuid::uuid7()->toString();

        $userId = Uuid::uuid7()->toString();
        $this->mokaemUspehSecurity($userId);

        $memoryRunner = new MemoryRunnerService();
        $memoryRunner->addResponse(MemoryRunnerService::SBORKA_UNITA, 'SBORKA_UNITA');
         $stepsSuccess = [[
            'Command' => 'command',
            'Response' => 'response',
            'Success' => true,
            'Unixtime' => 123123123
        ]];
        $configText2 = file_get_contents(__DIR__.'/data/config_2.yaml');

        $memoryRunner->addResponse(MemoryRunnerService::SBORKA_UNITA, 'SBORKA_UNITA');
        $memoryRunner->addResponse(MemoryRunnerService::RESULTAT_SBORKI, new ResultatSbrokiUnita(true,$stepsSuccess, $configText2));
        $memoryRunner->addResponse(MemoryRunnerService::PODGOTOVKA_UNITA, 'PODGOTOVKA_UNITA');
        $memoryRunner->addResponse(MemoryRunnerService::RESULTAT_PODGOTOVKI, new ResultatPodgotovkiUnita(true,$stepsSuccess));
        $memoryRunner->addResponse(MemoryRunnerService::ZAPUSK_UNITA, 'ZAPUSK_UNITA');
        $memoryRunner->addResponse(MemoryRunnerService::RESULTAT_ZAPUSKA, new ResultatZapuskaUnita(true,$stepsSuccess));

        $memoryRunner->addResponse(MemoryRunnerService::SBORKA_UNITA, 'SBORKA_UNITA');
        $memoryRunner->addResponse(MemoryRunnerService::RESULTAT_SBORKI, new ResultatSbrokiUnita(true,$stepsSuccess, $configText2));
        $memoryRunner->addResponse(MemoryRunnerService::PODGOTOVKA_UNITA, 'PODGOTOVKA_UNITA');
        $memoryRunner->addResponse(MemoryRunnerService::RESULTAT_PODGOTOVKI, new ResultatPodgotovkiUnita(true,$stepsSuccess));
        $memoryRunner->addResponse(MemoryRunnerService::ZAPUSK_UNITA, 'ZAPUSK_UNITA');
        $memoryRunner->addResponse(MemoryRunnerService::RESULTAT_ZAPUSKA, new ResultatZapuskaUnita(true,$stepsSuccess));

        $memoryRunner->addResponse(MemoryRunnerService::SBORKA_UNITA, 'SBORKA_UNITA');
        $memoryRunner->addResponse(MemoryRunnerService::RESULTAT_SBORKI, new ResultatSbrokiUnita(true,$stepsSuccess, $configText2));
        $memoryRunner->addResponse(MemoryRunnerService::PODGOTOVKA_UNITA, 'PODGOTOVKA_UNITA');
        $memoryRunner->addResponse(MemoryRunnerService::RESULTAT_PODGOTOVKI, new ResultatPodgotovkiUnita(true,$stepsSuccess));
        $memoryRunner->addResponse(MemoryRunnerService::ZAPUSK_UNITA, 'ZAPUSK_UNITA');
        $memoryRunner->addResponse(MemoryRunnerService::RESULTAT_ZAPUSKA, new ResultatZapuskaUnita(true,$stepsSuccess));

        self::$container->set(RunnerApiInterface::class, $memoryRunner);

        $now = new \DateTimeImmutable('2025-10-09 10:00:00');
        $clockService = new TestClockService($now);
        self::$container->set(ClockInterface::class, $clockService);

        $guidGenerator = new MemoryGuidGenerator([$unitId, $prozesSbrokiId, $unitId2, $prozesSbrokiId2, $unitId3, $prozesSbrokiId3, $unitId4, $prozesSbrokiId4, $prozesUdaleniyaId, $prozesUdaleniyaId2]);
        self::$container->set(CanGeneateGuid::class, $guidGenerator);

        $this->sozdatUnit($userId, $unitId, 'task-111');

        $useCase = self::$container->get(ObrabotatProzesiUnitaUseCase::class);
        $useCase->handle($unitId);
        $useCase->handle($unitId);
        $useCase->handle($unitId);
        $useCase->handle($unitId);
        $useCase->handle($unitId);
        $useCase->handle($unitId);

        $now = new \DateTimeImmutable('2025-10-10 15:00:00');
        $clockService->setNow($now);

        $this->sozdatUnit($userId, $unitId2, 'task-222');

        $useCase = self::$container->get(ObrabotatProzesiUnitaUseCase::class);
        $useCase->handle($unitId2);
        $useCase->handle($unitId2);
        $useCase->handle($unitId2);
        $useCase->handle($unitId2);
        $useCase->handle($unitId2);
        $useCase->handle($unitId2);

        $now = new \DateTimeImmutable('2025-10-11 00:00:00');
        $clockService->setNow($now);

        $this->sozdatUnit($userId, $unitId3, 'task-333');

        $useCase = self::$container->get(ObrabotatProzesiUnitaUseCase::class);
        $useCase->handle($unitId3);
        $useCase->handle($unitId3);
        $useCase->handle($unitId3);
        $useCase->handle($unitId3);
        $useCase->handle($unitId3);
        $useCase->handle($unitId);

        $this->sozdatUnit($userId, $unitId4, 'task-444');

        $now = new \DateTimeImmutable('2025-10-24 15:00:01');
        $clockService->setNow($now);

        $useCase = self::$container->get(UdalitNeaktivnieUnitiUseCase::class);
        $useCase->handle(60*60*24*14);

        $spisokUnitovRepo = self::$container->get(SpisokUnitovRepository::class);
        /** @var SpisokUnitovRepository $spisokUnitovRepo */
        $spisokUnitovReadModel = $spisokUnitovRepo->getById($unitId);
        $this->assertEquals(UnitProcessType::UDALENIE->value, $spisokUnitovReadModel->prozesi[1]['type']);
        /** @var SpisokUnitovReadModel $spisokUnitovReadModel*/
        $spisokUnitovReadModel2 = $spisokUnitovRepo->getById($unitId2);
        $this->assertEquals(UnitProcessType::UDALENIE->value, $spisokUnitovReadModel2->prozesi[1]['type']);
        /** @var SpisokUnitovReadModel $spisokUnitovReadModel2*/
        $spisokUnitovReadModel3 = $spisokUnitovRepo->getById($unitId3);
        /** @var SpisokUnitovReadModel $spisokUnitovReadModel3*/
        $this->assertEquals(1, count($spisokUnitovReadModel3->prozesi));
        $spisokUnitovReadModel4 = $spisokUnitovRepo->getById($unitId3);
        /** @var SpisokUnitovReadModel $spisokUnitovReadModel4*/
        $this->assertEquals(1, count($spisokUnitovReadModel4->prozesi));
    }
}
