<?php

namespace App\Unitman\Tests\UseCase\Unit;

use App\Unitman\Business\Command\Unit\SobratUnit;
use App\Unitman\Business\Command\Unit\UdalitUnit;
use App\Unitman\Business\Command\Unit\UstanovitResultatSborkiUnita;
use App\Unitman\Business\Command\Unit\UstanovitResultatUdaleniya;
use App\Unitman\Business\Model\Runner\JobId;
use App\Unitman\Business\Model\Runner\ResultatSborkiUnita;
use App\Unitman\Business\Model\Runner\ResultatUdaleniyaUnita;
use App\Unitman\Business\Model\Unit\State\StateUserCommand;
use App\Unitman\Business\Port\RunnerService;
use App\Unitman\Business\UseCase\Unit\SobratUnitUseCase;
use App\Unitman\Business\UseCase\Unit\UdalitUnitUseCase;
use App\Unitman\Business\UseCase\Unit\UstanovitResultatSborkiUnitaUseCase;
use App\Unitman\Business\UseCase\Unit\UstanovitResultatUdaleniyaUseCase;
use App\Unitman\Infra\Adapter\MemoryRunnerService;
use App\Unitman\Infra\Repository\Unit\SpisokUnitovRepository;
use Ramsey\Uuid\Uuid;

final class OshibkaSborkiIUdalenieTest extends AbstractUnitUseCase
{
    function test()
    {
        $unitId = Uuid::uuid7()->toString();
        $this->sozdatUnit($unitId);

        $memoryRunner = new MemoryRunnerService();
        $memoryRunner->addResponse(MemoryRunnerService::SBORKA_UNITA, new JobId('SBORKA_UNITA'));
        $memoryRunner->addResponse(MemoryRunnerService::RESULTAT_SBORKI, new ResultatSborkiUnita(false, 'text_ot_runnera_oshibka_sborki'));
        $memoryRunner->addResponse(MemoryRunnerService::UDALENIE_UNITA, new JobId('UDALENIE_UNITA'));
        $memoryRunner->addResponse(MemoryRunnerService::RESULTAT_UDALENIYA, new ResultatUdaleniyaUnita(true, 'text_ot_runnera_oshibka_sborki'));
        self::$container->set(RunnerService::class, $memoryRunner);

        $useCase = self::$container->get(SobratUnitUseCase::class);
        $useCase->handle(new SobratUnit($unitId));

        $useCase = self::$container->get(UstanovitResultatSborkiUnitaUseCase::class);
        $useCase->handle(new UstanovitResultatSborkiUnita($unitId));

        $spisokUnitovRepo = self::$container->get(SpisokUnitovRepository::class);

        $spisokUnitovReadModel = $spisokUnitovRepo->getById($unitId);
        $this->assertEquals($spisokUnitovReadModel->waitResultFromRunner, false);
        $this->assertEquals($spisokUnitovReadModel->textOtRunnera, 'text_ot_runnera_oshibka_sborki');
        $this->assertEquals($spisokUnitovReadModel->state, json_encode([
            'code' => 'OSHIBKA_SBORKI',
            'commands' => [
                'nachatUdalenie',
                'nachatSborku'
            ]
        ]));

        $useCase = self::$container->get(UdalitUnitUseCase::class);
        $useCase->handle(new UdalitUnit($unitId));

        $useCase = self::$container->get(UstanovitResultatUdaleniyaUseCase::class);
        $useCase->handle(new UstanovitResultatUdaleniya($unitId));

        $spisokUnitovReadModel = $spisokUnitovRepo->findById($unitId);
        $this->assertTrue(empty($spisokUnitovReadModel));
    }
}
