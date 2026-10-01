<?php

namespace App\Unitman\Tests\UseCase\Unit;

use App\Runner\Api\RunnerApiInterface;
use App\Runner\Business\Model\GolangRunner\Unit\ResultatPodgotovkiUnita;
use App\Runner\Business\Model\GolangRunner\Unit\ResultatSbrokiUnita;
use App\Runner\Business\Model\GolangRunner\Unit\ResultatZapuskaUnita;
use App\Unitman\Acl\MemoryRunnerService;
use App\Unitman\Business\Model\Account;
use App\Unitman\Business\Model\Unit\Runner\RunnerJobState;
use App\Unitman\Business\Model\Unit\Runner\RunnerJobType;
use App\Unitman\Business\Model\Unit\UnitProcess\UnitProcessType;
use App\Unitman\Business\Port\CanGeneateGuid;
use App\Unitman\Business\Port\UnitmanSecurityService;
use App\Unitman\Business\UseCase\Unit\ObrabotatProzesiUnitaUseCase;
use App\Unitman\Infra\Adapter\MemoryGuidGenerator;
use App\Unitman\Infra\Repository\Unit\SpisokUnitovRepository;
use App\Utils\Service\TestClockService;
use PHPUnit\Framework\TestCase;
use Psr\Clock\ClockInterface;
use Ramsey\Uuid\Uuid;

final class OtmenaZadachiPoLimituTest extends AbstractUnitUseCase
{
    function test(): void
    {
        $readModelRepo = self::$container->get(SpisokUnitovRepository::class);
        /** @var SpisokUnitovRepository $readModelRepo*/
        $readModelRepo->truncate();

        $unitId = Uuid::uuid7()->toString();
        $prozesSbrokiId = Uuid::uuid7()->toString();

        $userId = Uuid::uuid7()->toString();
        $securityService = $this->getMockBuilder(UnitmanSecurityService::class)->getMock();
        $securityService->expects($this->atLeastOnce())->method('getCurrentUserId')->willReturn($userId);
        $securityService->expects($this->atLeastOnce())->method('getUserById')->willReturn(new Account($userId, 'asd@asd.ru', false));
        //$securityService->expects($this->atLeastOnce())->method('getSystemUser')->willReturn(new Account($userId, 'asd@asd.ru', false));
        self::$container->set(UnitmanSecurityService::class, $securityService);

        $now = new \DateTimeImmutable('2025-10-09 10:00:00');
        $clockService = new TestClockService($now);
        self::$container->set(ClockInterface::class, $clockService);

        $memoryRunner = new MemoryRunnerService();
        $memoryRunner->addResponse(MemoryRunnerService::SBORKA_UNITA, 'SBORKA_UNITA');
        $stepsSuccess = [[
            'Command' => 'command',
            'Response' => 'response',
            'Success' => true,
            'Unixtime' => $now->getTimestamp()
        ]];
        $configText2 = file_get_contents(__DIR__.'/data/config_2.yaml');

        $memoryRunner->addResponse(MemoryRunnerService::SBORKA_UNITA, 'SBORKA_UNITA');
        $memoryRunner->addResponse(MemoryRunnerService::RESULTAT_SBORKI, new ResultatSbrokiUnita(true,$stepsSuccess, $configText2));
        $memoryRunner->addResponse(MemoryRunnerService::PODGOTOVKA_UNITA, 'PODGOTOVKA_UNITA');
        $memoryRunner->addResponse(MemoryRunnerService::RESULTAT_PODGOTOVKI, new ResultatPodgotovkiUnita(true,$stepsSuccess));
        $memoryRunner->addResponse(MemoryRunnerService::ZAPUSK_UNITA, 'ZAPUSK_UNITA');

        self::$container->set(RunnerApiInterface::class, $memoryRunner);

        $guidGenerator = new MemoryGuidGenerator([$unitId, $prozesSbrokiId]);
        self::$container->set(CanGeneateGuid::class, $guidGenerator);

        $this->sozdatUnit($userId, $unitId, 'task-111');

        $useCase = self::$container->get(ObrabotatProzesiUnitaUseCase::class);
        $useCase->handle($unitId);
        $useCase->handle($unitId);
        $useCase->handle($unitId);
        $useCase->handle($unitId);
        $useCase->handle($unitId);

        try {
            $useCase->handle($unitId);
            $this->assertFalse(true, 'Сюда не должны были попасть, ожидается ошибка');
        } catch (\Throwable $e) {
        }


        $spisokUnitovRepo = self::$container->get(SpisokUnitovRepository::class);
        /** @var SpisokUnitovRepository $spisokUnitovRepo */
        $spisokUnitovReadModel = $spisokUnitovRepo->getById($unitId);
        $this->assertEquals(UnitProcessType::SBORKA->value, $spisokUnitovReadModel->prozesi[0]['type']);
        //die("<pre>" . print_r($spisokUnitovReadModel->prozesi, true) . "</pre>");
        $this->assertEquals(RunnerJobType::ZAPUSK->value, $spisokUnitovReadModel->prozesi[0]['jobs'][2]['type']);
        $this->assertEquals(RunnerJobState::PENDING->value, $spisokUnitovReadModel->prozesi[0]['jobs'][2]['state']);

        $now = new \DateTimeImmutable('2025-10-09 10:30:01');
        $clockService->setNow($now);

        $useCase = self::$container->get(ObrabotatProzesiUnitaUseCase::class);
        $useCase->handle($unitId);

        $spisokUnitovRepo = self::$container->get(SpisokUnitovRepository::class);
        /** @var SpisokUnitovRepository $spisokUnitovRepo */
        $spisokUnitovReadModel = $spisokUnitovRepo->getById($unitId);
        $this->assertEquals(UnitProcessType::SBORKA->value, $spisokUnitovReadModel->prozesi[0]['type']);
        $this->assertEquals(RunnerJobType::ZAPUSK->value, $spisokUnitovReadModel->prozesi[0]['jobs'][2]['type']);
        $this->assertEquals(RunnerJobState::CANCLED->value, $spisokUnitovReadModel->prozesi[0]['jobs'][2]['state']);
    }
}
