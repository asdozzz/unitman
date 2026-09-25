<?php

namespace App\Unitman\Tests\UseCase\Unit;

use App\Runner\Api\RunnerApiInterface;
use App\Runner\Business\Model\GolangRunner\Unit\ResultatPodgotovkiUnita;
use App\Runner\Business\Model\GolangRunner\Unit\ResultatSbrokiUnita;
use App\Runner\Business\Model\GolangRunner\Unit\ResultatUdaleniyaUnita;
use App\Unitman\Business\Model\Unit\Runner\RunnerJobState;
use App\Unitman\Business\Model\Unit\Runner\RunnerJobType;
use App\Unitman\Business\Model\Unit\UnitProcess\UnitProcessState;
use App\Unitman\Business\Port\CanGeneateGuid;
use App\Unitman\Business\UseCase\Unit\DobavitProzesUdaleniyaUseCase;
use App\Unitman\Business\UseCase\Unit\ObrabotatProzesiUnitaUseCase;
use App\Unitman\Acl\MemoryRunnerService;
use App\Unitman\Infra\Adapter\MemoryGuidGenerator;
use App\Unitman\Infra\Repository\Unit\ProzesUnitaRepository;
use App\Unitman\Infra\Repository\Unit\SpisokUnitovRepository;
use Ramsey\Uuid\Uuid;

final class OshibkaPodgotovkiIUdalenieTest extends AbstractUnitUseCase
{
    function test()
    {
        $unitId = Uuid::uuid7()->toString();
        $prozesSborkiId = Uuid::uuid7()->toString();
        $prozesUdaleniyaId = Uuid::uuid7()->toString();
        self::$container->set(CanGeneateGuid::class, new MemoryGuidGenerator([$unitId, $prozesSborkiId, $prozesUdaleniyaId]));

        $userId = Uuid::uuid7()->toString();
        $this->mokaemUspehSecurity($userId);
        $this->sozdatUnit($userId, $unitId);

        $memoryRunner = new MemoryRunnerService();
        $memoryRunner->addResponse(MemoryRunnerService::SBORKA_UNITA, 'SBORKA_UNITA');
        $stepsFail = [[
            'Command' => 'command',
            'Response' => 'response',
            'Success' => false,
            'Unixtime' => 123123123
        ]];
        $stepsSuccess = [[
            'Command' => 'command',
            'Response' => 'response',
            'Success' => true,
            'Unixtime' => 123123123
        ]];
        $configText2 = file_get_contents(__DIR__.'/data/config_2.yaml');
        $memoryRunner->addResponse(MemoryRunnerService::RESULTAT_SBORKI, new ResultatSbrokiUnita(true, $stepsSuccess, $configText2));
        $memoryRunner->addResponse(MemoryRunnerService::PODGOTOVKA_UNITA, 'PODGOTOVKA_UNITA');
        $memoryRunner->addResponse(MemoryRunnerService::RESULTAT_PODGOTOVKI, new ResultatPodgotovkiUnita(false, $stepsFail));
        $memoryRunner->addResponse(MemoryRunnerService::UDALENIE_UNITA, 'UDALENIE_UNITA');
        $memoryRunner->addResponse(MemoryRunnerService::RESULTAT_UDALENIYA, new ResultatUdaleniyaUnita(true, $stepsSuccess));
        self::$container->set(RunnerApiInterface::class, $memoryRunner);
        $spisokUnitovRepo = self::$container->get(SpisokUnitovRepository::class);
        /** @var  $spisokUnitovRepo SpisokUnitovRepository*/


        $useCase = self::$container->get(ObrabotatProzesiUnitaUseCase::class);
        $useCase->handle($unitId);
        $useCase->handle($unitId);
        $useCase->handle($unitId);
        $useCase->handle($unitId);

        $spisokUnitovReadModel = $spisokUnitovRepo->getById($unitId);
        $this->assertEquals(UnitProcessState::ERROR->value, $spisokUnitovReadModel->prozesi[0]['state']);
        $this->assertEquals(RunnerJobState::ERROR->value, $spisokUnitovReadModel->prozesi[0]['jobs'][1]['state']);
        $this->assertEquals(true, $spisokUnitovReadModel->error);

        $useCase = self::$container->get(DobavitProzesUdaleniyaUseCase::class);
        $useCase->handle($unitId);

        $spisokUnitovReadModel = $spisokUnitovRepo->getById($unitId);
        $this->assertEquals(UnitProcessState::ZADACHI_DOBAVLENI->value, $spisokUnitovReadModel->prozesi[0]['state']);
        $this->assertEquals(RunnerJobState::NEW->value, $spisokUnitovReadModel->prozesi[0]['jobs'][0]['state']);
        $this->assertEquals(RunnerJobType::UDALENIE->value, $spisokUnitovReadModel->prozesi[0]['jobs'][0]['type']);

        $useCase = self::$container->get(ObrabotatProzesiUnitaUseCase::class);
        $useCase->handle($unitId);

        $spisokUnitovReadModel = $spisokUnitovRepo->getById($unitId);
        $this->assertEquals(UnitProcessState::PENDING->value, $spisokUnitovReadModel->prozesi[0]['state']);
        $this->assertEquals(RunnerJobState::PENDING->value, $spisokUnitovReadModel->prozesi[0]['jobs'][0]['state']);

        $useCase->handle($unitId);

        $spisokUnitovReadModel = $spisokUnitovRepo->findById($unitId);
        $this->assertTrue(empty($spisokUnitovReadModel));

        $prozesUnitaRepo = self::$container->get(ProzesUnitaRepository::class);
        /** @var $prozesUnitaRepo ProzesUnitaRepository*/
        $prozesi = $prozesUnitaRepo->poluchitProzesiPoIdUnita($unitId);

        $this->assertEquals(2, count($prozesi));
    }
}
