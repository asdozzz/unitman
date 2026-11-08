<?php

namespace App\Unitman\Tests\UseCase\Unit;

use App\Runner\Api\RunnerApiInterface;
use App\Runner\Business\Model\GolangRunner\Unit\ResultatSbrokiUnita;
use App\Unitman\Acl\MemoryRunnerService;
use App\Unitman\Business\Command\Unit\PoluchitSsylkuPoPomoschniku;
use App\Unitman\Business\Port\CanGeneateGuid;
use App\Unitman\Business\UseCase\Unit\ObrabotatProzesiUnitaUseCase;
use App\Unitman\Business\UseCase\Unit\PoluchitSsylkuPoPomoschnikuQuery;
use App\Unitman\Infra\Adapter\MemoryGuidGenerator;
use Ramsey\Uuid\Uuid;

final class PoluchitSsylkuPoPomoschnikuQueryTest extends AbstractUnitUseCase
{
    function test()
    {
        $unitAId = Uuid::uuid7()->toString();
        $prozesAId = Uuid::uuid7()->toString();
        $unitBId = Uuid::uuid7()->toString();
        $prozesBId = Uuid::uuid7()->toString();
        $unitCId = Uuid::uuid7()->toString();
        $prozesCId = Uuid::uuid7()->toString();
        self::$container->set(CanGeneateGuid::class, new MemoryGuidGenerator([
            $unitAId, $prozesAId,
            $unitBId, $prozesBId,
            $unitCId, $prozesCId,
        ]));

        $userId = Uuid::uuid7()->toString();
        $this->mokaemUspehSecurity($userId);
        $projectIdA = Uuid::uuid7()->toString();
        $projectIdB = Uuid::uuid7()->toString();
        $projectIdC = Uuid::uuid7()->toString();
        $this->sozdatUnit($userId, $unitAId, 'task-456', 'uwin', $projectIdA);
        $this->sozdatUnit($userId, $unitBId, 'task-123', 'uwin', $projectIdB);
        $this->sozdatUnit($userId, $unitCId, 'task-789', 'uwin', $projectIdC);

        $memoryRunner = new MemoryRunnerService();
        $stepsSuccess = [
            [
                'Command' => 'command',
                'Response' => 'response',
                'Success' => true,
                'Unixtime' => 123123123
            ]
        ];
        $configText1 = file_get_contents(__DIR__.'/data/config_1.yaml');
        $configText2 = file_get_contents(__DIR__.'/data/config_2.yaml');
        $configText3 = file_get_contents(__DIR__.'/data/config_3.yaml');

        $memoryRunner->addResponse(MemoryRunnerService::SBORKA_UNITA, 'SBORKA_UNITA');
        $memoryRunner->addResponse(MemoryRunnerService::RESULTAT_SBORKI, new ResultatSbrokiUnita(true, $stepsSuccess, $configText1));
        $memoryRunner->addResponse(MemoryRunnerService::SBORKA_UNITA, 'SBORKA_UNITA');
        $memoryRunner->addResponse(MemoryRunnerService::RESULTAT_SBORKI, new ResultatSbrokiUnita(true, $stepsSuccess, $configText2));
        $memoryRunner->addResponse(MemoryRunnerService::SBORKA_UNITA, 'SBORKA_UNITA');
        $memoryRunner->addResponse(MemoryRunnerService::RESULTAT_SBORKI, new ResultatSbrokiUnita(true, $stepsSuccess, $configText3));
        self::$container->set(RunnerApiInterface::class, $memoryRunner);

        $useCase = self::$container->get(ObrabotatProzesiUnitaUseCase::class);
        $useCase->handle($unitAId);
        $useCase->handle($unitAId);
        $useCase->handle($unitBId);
        $useCase->handle($unitBId);
        $useCase->handle($unitCId);
        $useCase->handle($unitCId);

        $query = self::$container->get(PoluchitSsylkuPoPomoschnikuQuery::class);
        // startUri из конфига ('/asd') больше не подмешивается: без suffix ссылка чистая
        $result = $query->handle(new PoluchitSsylkuPoPomoschniku($projectIdB, 'web', 8080, $unitBId));
        $this->assertEquals(['link' => 'https://8080.task-123.uwin.testcase.ru'], $result);

        // suffix подставляется вместо startUri
        $resultWithSuffix = $query->handle(new PoluchitSsylkuPoPomoschniku($projectIdB, 'web', 8080, $unitBId, '/api/v1'));
        $this->assertEquals(['link' => 'https://8080.task-123.uwin.testcase.ru/api/v1'], $resultWithSuffix);

        // suffix без ведущего слеша нормализуется
        $resultWithSuffixNoSlash = $query->handle(new PoluchitSsylkuPoPomoschniku($projectIdB, 'web', 8080, $unitBId, 'api/v2'));
        $this->assertEquals(['link' => 'https://8080.task-123.uwin.testcase.ru/api/v2'], $resultWithSuffixNoSlash);

        // ws-порт: ссылка строится напрямую на юнит, без proxy host и без wss
        $wsResult = $query->handle(new PoluchitSsylkuPoPomoschniku($projectIdC, 'web', 8080, $unitCId, null, 'ws'));
        $this->assertEquals(['link' => 'ws://task-789.uwin:8080'], $wsResult);

        // ws-порт с суффиксом
        $wsResultWithSuffix = $query->handle(new PoluchitSsylkuPoPomoschniku($projectIdC, 'web', 8080, $unitCId, '/socket', 'ws'));
        $this->assertEquals(['link' => 'ws://task-789.uwin:8080/socket'], $wsResultWithSuffix);

        // ws-порт не находится по протоколу по умолчанию (http)
        try {
            $query->handle(new PoluchitSsylkuPoPomoschniku($projectIdC, 'web', 8080, $unitCId));
            $this->fail('Expected DomainException was not thrown');
        } catch (\DomainException $e) {
            $this->assertEquals('unit.pomoschnik.servis_ne_naiden', $e->getMessage());
        }

        // http-порт не находится по протоколу ws
        try {
            $query->handle(new PoluchitSsylkuPoPomoschniku($projectIdB, 'web', 8080, $unitBId, null, 'ws'));
            $this->fail('Expected DomainException was not thrown');
        } catch (\DomainException $e) {
            $this->assertEquals('unit.pomoschnik.servis_ne_naiden', $e->getMessage());
        }

        try {
            $query->handle(new PoluchitSsylkuPoPomoschniku($projectIdA, 'web', 9999, $unitAId));
            $this->fail('Expected DomainException was not thrown');
        } catch (\DomainException $e) {
            $this->assertEquals('unit.pomoschnik.servis_ne_naiden', $e->getMessage());
        }
    }
}
