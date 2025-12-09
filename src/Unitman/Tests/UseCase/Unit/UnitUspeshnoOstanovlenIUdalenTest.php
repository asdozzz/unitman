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
use App\Unitman\Business\Command\Unit\UdalitSlomaniyUnit;
use App\Unitman\Business\Command\Unit\VipolnitDeistviye;
use App\Unitman\Business\Model\Unit\Runner\RunnerJobState;
use App\Unitman\Business\Model\Unit\Runner\RunnerJobType;
use App\Unitman\Business\Model\Unit\State\StateUserCommand;
use App\Unitman\Business\Model\Unit\UnitProcess\UnitProcessState;
use App\Unitman\Business\Model\Unit\UnitProcess\UnitProcessType;
use App\Unitman\Business\Port\CanGeneateGuid;
use App\Unitman\Business\ReadModel\Unit\ProzesUnita;
use App\Unitman\Business\ReadModel\Unit\ProzesUnitaBezShagov;
use App\Unitman\Business\ReadModel\Unit\SpisokUnitovReadModel;
use App\Unitman\Business\UseCase\Unit\DobavitProzesObnovleniyaUseCase;
use App\Unitman\Business\UseCase\Unit\DobavitProzesSborkiUseCase;
use App\Unitman\Business\UseCase\Unit\DobavitProzesUdaleniyaUseCase;
use App\Unitman\Business\UseCase\Unit\DobavitProzesVipolneniyaDeistviyaUseCase;
use App\Unitman\Business\UseCase\Unit\ObrabotatProzesiUnitaUseCase;
use App\Unitman\Business\UseCase\Unit\UdalitSlomaniyUnitUseCase;
use App\Unitman\Acl\MemoryRunnerService;
use App\Unitman\Infra\Adapter\MemoryGuidGenerator;
use App\Unitman\Infra\Adapter\RamseyGuidGenerator;
use App\Unitman\Infra\Repository\Unit\OcheredUnitovRepository;
use App\Unitman\Infra\Repository\Unit\ProzesUnitaRepository;
use App\Unitman\Infra\Repository\Unit\SpisokUnitovRepository;
use App\Utils\Service\TestClockService;
use Psr\Clock\ClockInterface;
use Ramsey\Uuid\Uuid;

final class UnitUspeshnoOstanovlenIUdalenTest extends AbstractUnitUseCase
{
    function test()
    {
        $now = new \DateTimeImmutable('2025-12-09 17:47:13');
        $clockService = new TestClockService($now);
        self::$container->set(ClockInterface::class, $clockService);

        $unitId = Uuid::uuid7()->toString();
        $prozesSbrokiId = Uuid::uuid7()->toString();
        $prozesDeistviyaId = Uuid::uuid7()->toString();
        $prozesUdaleniyaId = Uuid::uuid7()->toString();
        self::$container->set(CanGeneateGuid::class, new MemoryGuidGenerator([$unitId, $prozesSbrokiId, $prozesDeistviyaId, $prozesUdaleniyaId]));
        $unitName = 'task-123';
        $projectName = 'uwin';
        $projectId = Uuid::uuid7()->toString();

        $userId = Uuid::uuid7()->toString();
        $this->mokaemUspehSecurity($userId);
        $this->sozdatUnit($userId, $unitId, $unitName, $projectName, $projectId);

        $ocheredUnitovRepository = self::$container->get(OcheredUnitovRepository::class);
        /** @var OcheredUnitovRepository $ocheredUnitovRepository */
        $unitIzOcheredi = $ocheredUnitovRepository->getByUnitId($unitId);
        $this->assertEquals($unitIzOcheredi->lastUpdate, $now);

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
        $commands = [
            'cp -f .env.runner .env',
            'mkdir -p config/jwt',
            'openssl genpkey -out config/jwt/private.pem -aes256 -algorithm rsa -pkeyopt rsa_keygen_bits:4096 -pass pass:my_password',
            'openssl pkey -in config/jwt/private.pem -out config/jwt/public.pem -pubout -passin pass:my_password',
        ];
        $variables = [
            ['Id' => 'DICTIONARY_USER', 'Value' => 'qweqwe', 'Type' => 'string'],
            ['Id' => 'DICTIONARY_SERVICE', 'Value' => 'http://v1.dict.ru', 'Type' => 'collection'],
        ];
        $caches = [
            ['ServiceName' => 'web', 'Keys' => ['composer.lock'], 'Paths' => ['vendor']]
        ];
        $expectedParams = new NachatPodgotovkuUnita(
            $projectId,
            $projectName,
            $unitId,
            $unitName,
            'http://oauth2:tok@repoUrl/projectCode.git',
            $commands,
            $variables,
            $caches,
            new NachatPodgotovkuUnita\ContainerSettings('3000m')
        );
        $callback = fn(array $args) => $this->assertEquals($expectedParams, $args[1]);
        $configText2 = file_get_contents(__DIR__.'/data/config_2.yaml');

        $memoryRunner->addResponse(MemoryRunnerService::SBORKA_UNITA, 'SBORKA_UNITA');
        $memoryRunner->addResponse(MemoryRunnerService::RESULTAT_SBORKI, new ResultatSbrokiUnita(true,$stepsSuccess, $configText2));
        $memoryRunner->addResponse(MemoryRunnerService::PODGOTOVKA_UNITA, 'PODGOTOVKA_UNITA', $callback);
        $memoryRunner->addResponse(MemoryRunnerService::RESULTAT_PODGOTOVKI, new ResultatPodgotovkiUnita(true,$stepsSuccess));
        $memoryRunner->addResponse(MemoryRunnerService::ZAPUSK_UNITA, 'ZAPUSK_UNITA');
        $memoryRunner->addResponse(MemoryRunnerService::RESULTAT_ZAPUSKA, new ResultatZapuskaUnita(true,$stepsSuccess));
        $memoryRunner->addResponse(MemoryRunnerService::DEISTVIE_UNITA, 'DEISTVIE_UNITA');
        $memoryRunner->addResponse(MemoryRunnerService::RESULTAT_DEISTVIYA, new ResultatDeistviyaUnita(true,$stepsSuccess));
        $memoryRunner->addResponse(MemoryRunnerService::OSTANOVKA_UNITA, 'OSTANOVKA_UNITA');
        $memoryRunner->addResponse(MemoryRunnerService::RESULTAT_OSTANOVKI, new ResultatOstanovkiUnita(true,$stepsSuccess));
        $memoryRunner->addResponse(MemoryRunnerService::SBROS_PODGOTOVKI_UNITA, 'SBROS_PODGOTOVKI_UNITA');
        $memoryRunner->addResponse(MemoryRunnerService::RESULTAT_SBROSA_PODGOTOVKI, new ResultatSbrosaPodgotovkiUnita(true,$stepsSuccess));
        $memoryRunner->addResponse(MemoryRunnerService::UDALENIE_UNITA, 'UDALENIE_UNITA');
        $memoryRunner->addResponse(MemoryRunnerService::RESULTAT_UDALENIYA, new ResultatUdaleniyaUnita(false,$stepsFail));
        self::$container->set(RunnerApiInterface::class, $memoryRunner);

        $spisokUnitovRepo = self::$container->get(SpisokUnitovRepository::class);
        /** @var SpisokUnitovRepository $spisokUnitovRepo */
        $spisokUnitovReadModel = $spisokUnitovRepo->getById($unitId);

        $this->assertEquals(UnitProcessState::ZADACHI_DOBAVLENI->value, $spisokUnitovReadModel->prozesi[0]['state']);
        $this->assertEquals(RunnerJobState::NEW->value, $spisokUnitovReadModel->prozesi[0]['jobs'][0]['state']);
        $this->assertEquals(RunnerJobState::NEW->value, $spisokUnitovReadModel->prozesi[0]['jobs'][2]['state']);

        $now = new \DateTimeImmutable('2025-12-09 17:52:13');
        $clockService->setNow($now);

        $useCase = self::$container->get(ObrabotatProzesiUnitaUseCase::class);
        $useCase->handle($unitId);

        $unitIzOcheredi = $ocheredUnitovRepository->getByUnitId($unitId);
        $this->assertEquals($unitIzOcheredi->lastUpdate, $now);

        $spisokUnitovReadModel = $spisokUnitovRepo->getById($unitId);
        /** @var SpisokUnitovReadModel $spisokUnitovReadModel*/

        $this->assertEquals(UnitProcessState::PENDING->value, $spisokUnitovReadModel->prozesi[0]['state']);
        $this->assertEquals(RunnerJobState::PENDING->value, $spisokUnitovReadModel->prozesi[0]['jobs'][0]['state']);
        $this->assertEquals(RunnerJobState::NEW->value, $spisokUnitovReadModel->prozesi[0]['jobs'][2]['state']);

        $now = new \DateTimeImmutable('2025-12-09 17:52:43');
        $clockService->setNow($now);

        $useCase = self::$container->get(ObrabotatProzesiUnitaUseCase::class);
        $useCase->handle($unitId);

        $unitIzOcheredi = $ocheredUnitovRepository->getByUnitId($unitId);
        $this->assertEquals($unitIzOcheredi->lastUpdate, $now);

        $spisokUnitovReadModel = $spisokUnitovRepo->getById($unitId);
        /** @var SpisokUnitovReadModel $spisokUnitovReadModel*/
        $this->assertEquals(UnitProcessState::PENDING->value, $spisokUnitovReadModel->prozesi[0]['state']);
        $this->assertEquals(RunnerJobState::SUCCESS->value, $spisokUnitovReadModel->prozesi[0]['jobs'][0]['state']);
        $this->assertEquals(RunnerJobState::NEW->value, $spisokUnitovReadModel->prozesi[0]['jobs'][2]['state']);

        $this->assertEquals($spisokUnitovReadModel->commands, [
            StateUserCommand::nachatUdalenie->value,
            StateUserCommand::nachatObnovlenie->value,
            StateUserCommand::zapolnitPeremenie->value,
        ]);

        $this->assertEquals($spisokUnitovReadModel->links,
            [
                ['service' => 'web', 'protocol' => 'http', 'port' => 80,'path' => 'https://80.task-123.uwin.testcase.ru', 'startUri' => null],
                ['service' => 'web', 'protocol' => 'http', 'port' => 8080,'path' => 'https://8080.task-123.uwin.testcase.ru/asd', 'startUri' => '/asd'],
                ['service' => 'web', 'protocol' => 'tcp', 'port' => 5043,'path' => 'tcp://task-123.uwin:5043', 'startUri' => null]
            ],
        );

        $now = new \DateTimeImmutable('2025-12-09 17:52:53');
        $clockService->setNow($now);

        $useCase = self::$container->get(ObrabotatProzesiUnitaUseCase::class);
        $useCase->handle($unitId);

        $unitIzOcheredi = $ocheredUnitovRepository->getByUnitId($unitId);
        $this->assertEquals($unitIzOcheredi->lastUpdate, $now);

        $spisokUnitovReadModel = $spisokUnitovRepo->getById($unitId);
        /** @var SpisokUnitovReadModel $spisokUnitovReadModel*/
        $this->assertEquals(UnitProcessState::PENDING->value, $spisokUnitovReadModel->prozesi[0]['state']);
        $this->assertEquals(RunnerJobState::PENDING->value, $spisokUnitovReadModel->prozesi[0]['jobs'][1]['state']);
        $this->assertEquals(RunnerJobState::NEW->value, $spisokUnitovReadModel->prozesi[0]['jobs'][2]['state']);

        $now = new \DateTimeImmutable('2025-12-09 17:53:10');
        $clockService->setNow($now);

        $useCase = self::$container->get(ObrabotatProzesiUnitaUseCase::class);
        $useCase->handle($unitId);

        $unitIzOcheredi = $ocheredUnitovRepository->getByUnitId($unitId);
        $this->assertEquals($unitIzOcheredi->lastUpdate, $now);

        $spisokUnitovReadModel = $spisokUnitovRepo->getById($unitId);
        /** @var SpisokUnitovReadModel $spisokUnitovReadModel*/
        $this->assertEquals(UnitProcessState::PENDING->value, $spisokUnitovReadModel->prozesi[0]['state']);
        $this->assertEquals(RunnerJobState::SUCCESS->value, $spisokUnitovReadModel->prozesi[0]['jobs'][1]['state']);
        $this->assertEquals(RunnerJobState::NEW->value, $spisokUnitovReadModel->prozesi[0]['jobs'][2]['state']);

        $now = new \DateTimeImmutable('2025-12-09 17:53:20');
        $clockService->setNow($now);

        $useCase = self::$container->get(ObrabotatProzesiUnitaUseCase::class);
        $useCase->handle($unitId);

        $unitIzOcheredi = $ocheredUnitovRepository->getByUnitId($unitId);
        $this->assertEquals($unitIzOcheredi->lastUpdate, $now);

        $spisokUnitovReadModel = $spisokUnitovRepo->getById($unitId);
        /** @var SpisokUnitovReadModel $spisokUnitovReadModel*/
        $this->assertEquals(UnitProcessState::PENDING->value, $spisokUnitovReadModel->prozesi[0]['state']);
        $this->assertEquals(RunnerJobState::SUCCESS->value, $spisokUnitovReadModel->prozesi[0]['jobs'][1]['state']);
        $this->assertEquals(RunnerJobState::PENDING->value, $spisokUnitovReadModel->prozesi[0]['jobs'][2]['state']);

        $now = new \DateTimeImmutable('2025-12-09 17:53:30');
        $clockService->setNow($now);

        $useCase = self::$container->get(ObrabotatProzesiUnitaUseCase::class);
        $useCase->handle($unitId);

        $unitIzOcheredi = $ocheredUnitovRepository->getByUnitId($unitId);
        $this->assertEquals($unitIzOcheredi->lastUpdate, $now);

        $spisokUnitovReadModel = $spisokUnitovRepo->getById($unitId);
        /** @var SpisokUnitovReadModel $spisokUnitovReadModel*/

        $this->assertEquals(UnitProcessState::SUCCESS->value, $spisokUnitovReadModel->prozesi[0]['state']);
        $this->assertEquals(RunnerJobState::SUCCESS->value, $spisokUnitovReadModel->prozesi[0]['jobs'][1]['state']);
        $this->assertEquals(RunnerJobState::SUCCESS->value, $spisokUnitovReadModel->prozesi[0]['jobs'][2]['state']);
        $this->assertEquals(true, $spisokUnitovReadModel->zapushen);

        $useCase = self::$container->get(DobavitProzesVipolneniyaDeistviyaUseCase::class);
        $useCase->handle(new VipolnitDeistviye($unitId, 'php-console',['BIN_CONSOLE' => 'test']));

        $spisokUnitovReadModel = $spisokUnitovRepo->getById($unitId);
        $this->assertEquals(UnitProcessState::ZADACHI_DOBAVLENI->value, $spisokUnitovReadModel->prozesi[1]['state']);
        $this->assertEquals(RunnerJobState::NEW->value, $spisokUnitovReadModel->prozesi[1]['jobs'][0]['state']);

        $now = new \DateTimeImmutable('2025-12-09 17:53:40');
        $clockService->setNow($now);

        $useCase = self::$container->get(ObrabotatProzesiUnitaUseCase::class);
        $useCase->handle($unitId);

        $unitIzOcheredi = $ocheredUnitovRepository->getByUnitId($unitId);
        $this->assertEquals($unitIzOcheredi->lastUpdate, $now);

        $spisokUnitovReadModel = $spisokUnitovRepo->getById($unitId);
        /** @var SpisokUnitovReadModel $spisokUnitovReadModel*/
        $this->assertEquals(UnitProcessState::PENDING->value, $spisokUnitovReadModel->prozesi[1]['state']);
        $this->assertEquals(RunnerJobState::PENDING->value, $spisokUnitovReadModel->prozesi[1]['jobs'][0]['state']);

        $now = new \DateTimeImmutable('2025-12-09 17:53:50');
        $clockService->setNow($now);

        $useCase = self::$container->get(ObrabotatProzesiUnitaUseCase::class);
        $useCase->handle($unitId);

        $unitIzOcheredi = $ocheredUnitovRepository->getByUnitId($unitId);
        $this->assertEquals($unitIzOcheredi->lastUpdate, $now);

        $spisokUnitovReadModel = $spisokUnitovRepo->getById($unitId);
        /** @var SpisokUnitovReadModel $spisokUnitovReadModel*/

        $this->assertEquals(UnitProcessState::SUCCESS->value, $spisokUnitovReadModel->prozesi[1]['state']);
        $this->assertEquals(RunnerJobState::SUCCESS->value, $spisokUnitovReadModel->prozesi[1]['jobs'][0]['state']);
        $this->assertEquals(true, $spisokUnitovReadModel->zapushen);

        $useCase = self::$container->get(DobavitProzesUdaleniyaUseCase::class);
        $useCase->handle($unitId);

        $spisokUnitovReadModel = $spisokUnitovRepo->getById($unitId);
        /** @var SpisokUnitovReadModel $spisokUnitovReadModel*/

        $this->assertEquals(UnitProcessState::ZADACHI_DOBAVLENI->value, $spisokUnitovReadModel->prozesi[2]['state']);
        $this->assertEquals(RunnerJobType::OSTANOVKA->value, $spisokUnitovReadModel->prozesi[2]['jobs'][0]['type']);
        $this->assertEquals(RunnerJobState::NEW->value, $spisokUnitovReadModel->prozesi[2]['jobs'][0]['state']);
        $this->assertEquals(RunnerJobState::NEW->value, $spisokUnitovReadModel->prozesi[2]['jobs'][2]['state']);

        $now = new \DateTimeImmutable('2025-12-09 17:54:00');
        $clockService->setNow($now);

        $useCase = self::$container->get(ObrabotatProzesiUnitaUseCase::class);
        $useCase->handle($unitId);

        $spisokUnitovReadModel = $spisokUnitovRepo->getById($unitId);
        /** @var SpisokUnitovReadModel $spisokUnitovReadModel*/
        $this->assertEquals(UnitProcessState::PENDING->value, $spisokUnitovReadModel->prozesi[2]['state']);
        $this->assertEquals(RunnerJobType::OSTANOVKA->value, $spisokUnitovReadModel->prozesi[2]['jobs'][0]['type']);
        $this->assertEquals(RunnerJobState::PENDING->value, $spisokUnitovReadModel->prozesi[2]['jobs'][0]['state']);
        $this->assertEquals(RunnerJobState::NEW->value, $spisokUnitovReadModel->prozesi[2]['jobs'][2]['state']);

        $useCase->handle($unitId);

        $spisokUnitovReadModel = $spisokUnitovRepo->getById($unitId);
        /** @var SpisokUnitovReadModel $spisokUnitovReadModel*/
        $this->assertEquals(UnitProcessState::PENDING->value, $spisokUnitovReadModel->prozesi[2]['state']);
        $this->assertEquals(RunnerJobType::OSTANOVKA->value, $spisokUnitovReadModel->prozesi[2]['jobs'][0]['type']);
        $this->assertEquals(RunnerJobState::SUCCESS->value, $spisokUnitovReadModel->prozesi[2]['jobs'][0]['state']);
        $this->assertEquals(RunnerJobState::NEW->value, $spisokUnitovReadModel->prozesi[2]['jobs'][2]['state']);
        $this->assertEquals(false, $spisokUnitovReadModel->zapushen);

        $useCase->handle($unitId);

        $spisokUnitovReadModel = $spisokUnitovRepo->getById($unitId);
        /** @var SpisokUnitovReadModel $spisokUnitovReadModel*/
        $this->assertEquals(UnitProcessState::PENDING->value, $spisokUnitovReadModel->prozesi[2]['state']);
        $this->assertEquals(RunnerJobState::PENDING->value, $spisokUnitovReadModel->prozesi[2]['jobs'][1]['state']);
        $this->assertEquals(RunnerJobState::NEW->value, $spisokUnitovReadModel->prozesi[2]['jobs'][2]['state']);

        $useCase->handle($unitId);

        $spisokUnitovReadModel = $spisokUnitovRepo->getById($unitId);
        /** @var SpisokUnitovReadModel $spisokUnitovReadModel*/
        $this->assertEquals(UnitProcessState::PENDING->value, $spisokUnitovReadModel->prozesi[2]['state']);
        $this->assertEquals(RunnerJobState::SUCCESS->value, $spisokUnitovReadModel->prozesi[2]['jobs'][1]['state']);
        $this->assertEquals(RunnerJobState::NEW->value, $spisokUnitovReadModel->prozesi[2]['jobs'][2]['state']);

        $useCase->handle($unitId);

        $spisokUnitovReadModel = $spisokUnitovRepo->getById($unitId);
        /** @var SpisokUnitovReadModel $spisokUnitovReadModel*/
        $this->assertEquals(UnitProcessState::PENDING->value, $spisokUnitovReadModel->prozesi[2]['state']);
        $this->assertEquals(RunnerJobState::SUCCESS->value, $spisokUnitovReadModel->prozesi[2]['jobs'][1]['state']);
        $this->assertEquals(RunnerJobState::PENDING->value, $spisokUnitovReadModel->prozesi[2]['jobs'][2]['state']);

        $useCase->handle($unitId);

        $spisokUnitovReadModel = $spisokUnitovRepo->getById($unitId);
        /** @var SpisokUnitovReadModel $spisokUnitovReadModel*/
        $this->assertEquals(UnitProcessState::ERROR->value, $spisokUnitovReadModel->prozesi[2]['state']);
        $this->assertEquals(RunnerJobState::SUCCESS->value, $spisokUnitovReadModel->prozesi[2]['jobs'][1]['state']);
        $this->assertEquals(RunnerJobState::ERROR->value, $spisokUnitovReadModel->prozesi[2]['jobs'][2]['state']);

        $useCase = self::$container->get(UdalitSlomaniyUnitUseCase::class);
        $useCase->handle(new UdalitSlomaniyUnit($unitId));

        $spisokUnitovReadModel = $spisokUnitovRepo->findById($unitId);
        $this->assertTrue(empty($spisokUnitovReadModel));

        $unitIzOcheredi = $ocheredUnitovRepository->findByUnitId($unitId);
        $this->assertTrue(empty($unitIzOcheredi));

        $prozesUnitaRepo = self::$container->get(ProzesUnitaRepository::class);
        /** @var $prozesUnitaRepo ProzesUnitaRepository*/
        $prozesi = $prozesUnitaRepo->poluchitProzesiPoIdUnita($unitId);

        $tipiProzesov  = array_map(fn(ProzesUnitaBezShagov $prozesUnita) => $prozesUnita->type, $prozesi);
        $this->assertEquals([
            UnitProcessType::UDALENIE->value,
            UnitProcessType::DEISTVIE->value,
            UnitProcessType::SBORKA->value,
        ], $tipiProzesov);
    }
}
