<?php

namespace App\Runner\Tests\UseCase\Runner;

use App\Runner\Business\Command\UstanovitResultatProverkiRabotosposobnosti;
use App\Runner\Business\Model\RunnerState;
use App\Runner\Business\Model\RunnerState\MemoryInfo;
use App\Runner\Business\Port\CanGenerateGuid;
use App\Runner\Business\UseCase\SozdatDefoltniiRunnerUseCase;
use App\Runner\Business\UseCase\UstanovitResultatProverkiRabotosposobnostiRunneraUseCase;
use App\Runner\Infra\Adapter\MemoryGuidGenerator;
use App\Runner\Infra\Repository\SqlRunnerStateRepository;
use App\Utils\EventSauce\AbstractTestCaseWithTransactionWrapper;
use Ramsey\Uuid\Uuid;

final class SozdatDefoltniiRunnerTest extends AbstractTestCaseWithTransactionWrapper
{
    function test()
    {
        $runnerId = Uuid::uuid7()->toString();

        self::$container->set(CanGenerateGuid::class, new MemoryGuidGenerator([$runnerId]));

        $sut = self::$container->get(SozdatDefoltniiRunnerUseCase::class);
        /** @var $sut SozdatDefoltniiRunnerUseCase*/
        $sut->handle();

        $runnerRepository = self::$container->get(SqlRunnerStateRepository::class);
        /** @var $runnerRepository SqlRunnerStateRepository*/
        $runner = $runnerRepository->getById($runnerId);

        $this->assertEquals('unitman-runner-queue', $runner->getTaskQueue());
        $this->assertEquals(false, $runner->isActive());
        $this->assertEquals(0, $runner->getUnixtimeLastHealth());
        $this->assertFalse($runner->isHealthFresh());


        $ustanovitResultatRabotosposobnosti = self::$container->get(UstanovitResultatProverkiRabotosposobnostiRunneraUseCase::class);
        /** @var $ustanovitResultatRabotosposobnosti UstanovitResultatProverkiRabotosposobnostiRunneraUseCase*/
        $ustanovitResultatRabotosposobnosti->handle(new UstanovitResultatProverkiRabotosposobnosti($runnerId, true));

        $runner = $runnerRepository->getById($runnerId);
        $this->assertEquals(true, $runner->isActive());

        $ustanovitResultatRabotosposobnosti = self::$container->get(UstanovitResultatProverkiRabotosposobnostiRunneraUseCase::class);
        /** @var $ustanovitResultatRabotosposobnosti UstanovitResultatProverkiRabotosposobnostiRunneraUseCase*/
        $ustanovitResultatRabotosposobnosti->handle(new UstanovitResultatProverkiRabotosposobnosti($runnerId, false));

        $runner = $runnerRepository->getById($runnerId);
        $this->assertEquals(false, $runner->isActive());
    }

    function testIsHealthFresh()
    {
        $runner = RunnerState::sozdatRunner('runner-id', 'unitman-runner-queue');

        $this->assertTrue($runner->isActive());
        $this->assertEquals('runner-id', $runner->getId());
        $this->assertEquals('unitman-runner-queue', $runner->getTaskQueue());
        $this->assertEquals(0, $runner->getUnixtimeLastHealth());
        $this->assertFalse($runner->isHealthFresh());

        $now = 1_000_000;

        $runner->ustanovitResultatRabotosposobnosti(
            true,
            [],
            new MemoryInfo(100, 50),
            $now,
        );

        $this->assertEquals($now, $runner->getUnixtimeLastHealth());
        $this->assertTrue($runner->isHealthFresh(180, $now));
        $this->assertTrue($runner->isHealthFresh(180, $now + 180));
        $this->assertFalse($runner->isHealthFresh(180, $now + 181));

        $runner->ustanovitResultatRabotosposobnosti(
            false,
            [],
            new MemoryInfo(100, 50),
        );

        $this->assertFalse($runner->isActive());
        $this->assertEquals($now, $runner->getUnixtimeLastHealth());
    }
}
