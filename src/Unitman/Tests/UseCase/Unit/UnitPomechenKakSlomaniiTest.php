<?php

namespace App\Unitman\Tests\UseCase\Unit;

use App\Runner\Api\RunnerApiInterface;
use App\Runner\Business\Model\GolangRunner\Unit\ResultatPodgotovkiUnita;
use App\Runner\Business\Model\GolangRunner\Unit\ResultatProverkiKonteineraUnita;
use App\Runner\Business\Model\GolangRunner\Unit\ResultatSbrokiUnita;
use App\Runner\Business\Model\GolangRunner\Unit\ResultatZapuskaUnita;
use App\Unitman\Acl\MemoryRunnerService;
use App\Unitman\Business\Command\Unit\PometitKakSlomanii;
use App\Unitman\Business\Model\Runner\ResultatUdaleniyaUnita;
use App\Unitman\Business\Model\Unit\Runner\RunnerJobState;
use App\Unitman\Business\Model\Unit\Runner\RunnerJobType;
use App\Unitman\Business\Model\Unit\State\StateUserCommand;
use App\Unitman\Business\Model\Unit\UnitProcess\UnitProcessState;
use App\Unitman\Business\Model\Unit\UnitProcess\UnitProcessType;
use App\Unitman\Business\Port\CanGeneateGuid;
use App\Unitman\Business\UseCase\Unit\DobavitProzesUdaleniyaUseCase;
use App\Unitman\Business\UseCase\Unit\ObrabotatProzesiUnitaUseCase;
use App\Unitman\Business\UseCase\Unit\PometitKakSlomaniiUseCase;
use App\Unitman\Business\UseCase\Unit\ProveritKonteinerUnitaUseCase;
use App\Unitman\Business\UseCase\Unit\UdalitNeaktivnieUnitiUseCase;
use App\Unitman\Business\UseCase\Unit\UdalitUnitUseCase;
use App\Unitman\Infra\Adapter\MemoryGuidGenerator;
use App\Unitman\Infra\Repository\Unit\SpisokUnitovRepository;
use App\Utils\Service\TestClockService;
use Psr\Clock\ClockInterface;
use Ramsey\Uuid\Uuid;

class UnitPomechenKakSlomaniiTest extends AbstractUnitUseCase
{
    /**
     * @test
     * */
    function kogda_jdem_resultat_udaleniya() {
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
            'Unixtime' => time()
        ]];
        $configText2 = file_get_contents(__DIR__.'/data/config_2.yaml');

        $memoryRunner->addResponse(MemoryRunnerService::SBORKA_UNITA, 'SBORKA_UNITA');
        $memoryRunner->addResponse(MemoryRunnerService::RESULTAT_SBORKI, new ResultatSbrokiUnita(true,$stepsSuccess, $configText2));
        $memoryRunner->addResponse(MemoryRunnerService::PODGOTOVKA_UNITA, 'PODGOTOVKA_UNITA');
        $memoryRunner->addResponse(MemoryRunnerService::RESULTAT_PODGOTOVKI, new ResultatPodgotovkiUnita(true,$stepsSuccess));
        $memoryRunner->addResponse(MemoryRunnerService::ZAPUSK_UNITA, 'ZAPUSK_UNITA');
        $memoryRunner->addResponse(MemoryRunnerService::RESULTAT_ZAPUSKA, new ResultatZapuskaUnita(true,$stepsSuccess));
        $memoryRunner->addResponse(MemoryRunnerService::OSTANOVKA_UNITA, 'OSTANOVKA_UNITA');

        self::$container->set(RunnerApiInterface::class, $memoryRunner);

        $guidGenerator = new MemoryGuidGenerator([$unitId, $prozesSbrokiId, $prozesUdaleniyaId]);
        self::$container->set(CanGeneateGuid::class, $guidGenerator);

        $this->sozdatUnit($userId, $unitId, 'task-111');

        $obrabotkaUseCase = self::$container->get(ObrabotatProzesiUnitaUseCase::class);
        $obrabotkaUseCase->handle($unitId);
        $obrabotkaUseCase->handle($unitId);
        $obrabotkaUseCase->handle($unitId);
        $obrabotkaUseCase->handle($unitId);
        $obrabotkaUseCase->handle($unitId);
        $obrabotkaUseCase->handle($unitId);

        $udalenieUseCase = self::$container->get(DobavitProzesUdaleniyaUseCase::class);
        $udalenieUseCase->handle($unitId);

        try {
            $obrabotkaUseCase->handle($unitId);
            $this->assertFalse(true, 'Сюда мы не должны были попасть');
        } catch (\Throwable) {

        }

        $spisokUnitovRepo = self::$container->get(SpisokUnitovRepository::class);
        $spisokUnitovReadModel = $spisokUnitovRepo->getById($unitId);
        $this->assertEquals(UnitProcessType::UDALENIE->value, $spisokUnitovReadModel->prozesi[0]['type']);
        $this->assertEquals(RunnerJobType::OSTANOVKA->value, $spisokUnitovReadModel->prozesi[0]['jobs'][0]['type']);
        $this->assertEquals(RunnerJobState::PENDING->value, $spisokUnitovReadModel->prozesi[0]['jobs'][0]['state']);

        $sut = self::$container->get(PometitKakSlomaniiUseCase::class);
        $sut->handle(new PometitKakSlomanii($unitId));

        $spisokUnitovRepo = self::$container->get(SpisokUnitovRepository::class);
        $spisokUnitovReadModel = $spisokUnitovRepo->getById($unitId);
        $this->assertEquals(UnitProcessType::UDALENIE->value, $spisokUnitovReadModel->prozesi[0]['type']);
        $this->assertEquals(UnitProcessState::CANCLED->value, $spisokUnitovReadModel->prozesi[0]['state']);

        $this->assertEquals(RunnerJobType::OSTANOVKA->value, $spisokUnitovReadModel->prozesi[0]['jobs'][0]['type']);
        $this->assertEquals(RunnerJobState::CANCLED->value, $spisokUnitovReadModel->prozesi[0]['jobs'][0]['state']);
        $this->assertEquals([StateUserCommand::udalitVruchnuyu->value], $spisokUnitovReadModel->commands);
    }
}
