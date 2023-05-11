<?php

namespace App\Unitman\Tests\UseCase\Unit;

use App\Unitman\Business\Command\Unit\SobratUnit;
use App\Unitman\Business\Command\Unit\UdalitUnit;
use App\Unitman\Business\Command\Unit\UstanovitOshibkuSborkiUnita;
use App\Unitman\Business\Command\Unit\UstanovitUspehUdaleniya;
use App\Unitman\Business\Model\Unit\State\StateUserCommand;
use App\Unitman\Business\Port\RunnerService;
use App\Unitman\Business\UseCase\Unit\SobratUnitUseCase;
use App\Unitman\Business\UseCase\Unit\UdalitUnitUseCase;
use App\Unitman\Business\UseCase\Unit\UstanovitOshibkuSborkiUnitaUseCase;
use App\Unitman\Business\UseCase\Unit\UstanovitUspehSborkiUnitaUseCase;
use App\Unitman\Business\UseCase\Unit\UstanovitUspehUdaleniyaUseCase;
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
        $memoryRunner->addResponse(MemoryRunnerService::SBORKA_UNITA, 'SBORKA_UNITA');
        $memoryRunner->addResponse(MemoryRunnerService::UDALENIE_UNITA, 'UDALENIE_UNITA');
        self::$container->set(RunnerService::class, $memoryRunner);

        $useCase = self::$container->get(SobratUnitUseCase::class);
        $useCase->handle(new SobratUnit($unitId));

        $useCase = self::$container->get(UstanovitOshibkuSborkiUnitaUseCase::class);
        $useCase->handle(new UstanovitOshibkuSborkiUnita($unitId, 'text_ot_runnera_oshibka_sborki'));

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

        $useCase = self::$container->get(UstanovitUspehUdaleniyaUseCase::class);
        $useCase->handle(new UstanovitUspehUdaleniya($unitId, 'text_ot_runner_uspeh_udaleniya'));

        $spisokUnitovReadModel = $spisokUnitovRepo->findById($unitId);
        $this->assertTrue(empty($spisokUnitovReadModel));
    }
}
