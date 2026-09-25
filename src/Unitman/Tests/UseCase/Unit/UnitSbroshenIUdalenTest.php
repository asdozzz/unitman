<?php

namespace App\Unitman\Tests\UseCase\Unit;

use App\Runner\Api\RunnerApiInterface;
use App\Runner\Business\Model\GolangRunner\Unit\ResultatPodgotovkiUnita;
use App\Runner\Business\Model\GolangRunner\Unit\ResultatProverkiKonteineraUnita;
use App\Runner\Business\Model\GolangRunner\Unit\ResultatSbrokiUnita;
use App\Runner\Business\Model\GolangRunner\Unit\ResultatZapuskaUnita;
use App\Unitman\Acl\MemoryRunnerService;
use App\Unitman\Business\Model\Runner\ResultatUdaleniyaUnita;
use App\Unitman\Business\Model\Unit\Runner\RunnerJobState;
use App\Unitman\Business\Model\Unit\Runner\RunnerJobType;
use App\Unitman\Business\Model\Unit\UnitProcess\UnitProcessType;
use App\Unitman\Business\Port\CanGeneateGuid;
use App\Unitman\Business\UseCase\Unit\DobavitProzesUdaleniyaUseCase;
use App\Unitman\Business\UseCase\Unit\ObrabotatProzesiUnitaUseCase;
use App\Unitman\Business\UseCase\Unit\ProveritKonteinerUnitaUseCase;
use App\Unitman\Business\UseCase\Unit\UdalitNeaktivnieUnitiUseCase;
use App\Unitman\Business\UseCase\Unit\UdalitUnitUseCase;
use App\Unitman\Infra\Adapter\MemoryGuidGenerator;
use App\Unitman\Infra\Repository\Unit\SpisokUnitovRepository;
use App\Utils\Service\TestClockService;
use Psr\Clock\ClockInterface;
use Ramsey\Uuid\Uuid;

final class UnitSbroshenIUdalenTest extends AbstractUnitUseCase
{
    function test() {
        $readModelRepo = self::$container->get(SpisokUnitovRepository::class);
        /** @var SpisokUnitovRepository $readModelRepo*/
        $readModelRepo->truncate();

        $unitId = Uuid::uuid7()->toString();
        $prozesSbrokiId = Uuid::uuid7()->toString();
        $prozesUdaleniyaId = Uuid::uuid7()->toString();

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
        $memoryRunner->addResponse(MemoryRunnerService::PROVERKA_UNITA, new ResultatProverkiKonteineraUnita(false));
        $memoryRunner->addResponse(MemoryRunnerService::UDALENIE_UNITA, 'UDALENIE_UNITA');
        $memoryRunner->addResponse(MemoryRunnerService::RESULTAT_UDALENIYA, new ResultatUdaleniyaUnita(true,$stepsSuccess));

        self::$container->set(RunnerApiInterface::class, $memoryRunner);

        $now = new \DateTimeImmutable('2025-10-09 10:00:00');
        $clockService = new TestClockService($now);
        self::$container->set(ClockInterface::class, $clockService);

        $guidGenerator = new MemoryGuidGenerator([$unitId, $prozesSbrokiId, $prozesUdaleniyaId]);
        self::$container->set(CanGeneateGuid::class, $guidGenerator);

        $this->sozdatUnit($userId, $unitId, 'task-111');

        $useCase = self::$container->get(ObrabotatProzesiUnitaUseCase::class);
        $useCase->handle($unitId);
        $useCase->handle($unitId);
        $useCase->handle($unitId);
        $useCase->handle($unitId);
        $useCase->handle($unitId);
        $useCase->handle($unitId);

        $spisokUnitovRepo = self::$container->get(SpisokUnitovRepository::class);
        /** @var SpisokUnitovRepository $spisokUnitovRepo */
        $spisokUnitovReadModel = $spisokUnitovRepo->getById($unitId);
        $this->assertEquals(true, $spisokUnitovReadModel->zapushen);

        $useCase = self::$container->get(ProveritKonteinerUnitaUseCase::class);
        $useCase->handle($unitId);

        $spisokUnitovReadModel = $spisokUnitovRepo->getById($unitId);
        $this->assertEquals(false, $spisokUnitovReadModel->zapushen);

        $useCase = self::$container->get(DobavitProzesUdaleniyaUseCase::class);
        $useCase->handle($unitId);

        $spisokUnitovReadModel = $spisokUnitovRepo->getById($unitId);
        $this->assertEquals(UnitProcessType::UDALENIE->value, $spisokUnitovReadModel->prozesi[0]['type']);
        $this->assertEquals(RunnerJobType::UDALENIE->value, $spisokUnitovReadModel->prozesi[0]['jobs'][0]['type']);
    }
}
