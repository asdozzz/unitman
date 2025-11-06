<?php

namespace App\Unitman\Tests\UseCase\Unit;

use App\Runner\Api\RunnerApiInterface;
use App\Runner\Business\Model\GolangRunner\Unit\ResultatPodgotovkiUnita;
use App\Runner\Business\Model\GolangRunner\Unit\ResultatSbrokiUnita;
use App\Runner\Business\Model\GolangRunner\Unit\ResultatZapuskaUnita;
use App\Unitman\Acl\MemoryRunnerService;
use App\Unitman\Business\Command\Project\Webhook\SozdatWebhookProekta;
use App\Unitman\Business\Command\Unit\SozdatUnit;
use App\Unitman\Business\Model\Repo\RepoType;
use App\Unitman\Business\Port\CanGeneateGuid;
use App\Unitman\Business\Port\Project\ProjectRepository;
use App\Unitman\Business\Port\Project\WebhookProjectRepository;
use App\Unitman\Business\Port\Repo\RepoRepository;
use App\Unitman\Business\Port\Unit\WebhookEvent\WebhookEventRepository;
use App\Unitman\Business\ReadModel\Unit\WebhookEvent\WebhookEventPayload\WebhookEventPayloadType;
use App\Unitman\Business\ReadModel\Unit\WebhookEvent\WebhookEventResponse;
use App\Unitman\Business\UseCase\Project\Webhook\DobavitWebhookProektaUseCase;
use App\Unitman\Business\UseCase\Unit\ObrabotatProzesiUnitaUseCase;
use App\Unitman\Business\UseCase\Unit\SozdatUnitUseCase;
use App\Unitman\Business\UseCase\Unit\WebhookEvent\OtpravitEventNaWebhookUseCase;
use App\Unitman\Infra\Adapter\MemoryGuidGenerator;
use App\Unitman\Infra\BackgroundJob\WebhookEvent\WebhookEventSender;
use App\Unitman\Infra\Repository\Unit\SpisokUnitovRepository;
use App\Utils\Service\TestClockService;
use Psr\Clock\ClockInterface;
use Ramsey\Uuid\Uuid;
use Symfony\Component\HttpClient\MockHttpClient;
use Symfony\Component\HttpClient\Response\MockResponse;
use Symfony\Contracts\HttpClient\HttpClientInterface;

final class WebhookTest extends AbstractUnitUseCase
{
    /**
     * @test
     * */
    function otptrav_v_ochered()
    {
        $readModelRepo = self::$container->get(SpisokUnitovRepository::class);
        /** @var SpisokUnitovRepository $readModelRepo*/
        $readModelRepo->truncate();

        $unitId = Uuid::uuid7()->toString();
        $prozesSbrokiId = Uuid::uuid7()->toString();
        $webhookPrId = Uuid::uuid7()->toString();
        $webhookPrId2 = Uuid::uuid7()->toString();

        $webhookEvent1 = Uuid::uuid7()->toString();
        $webhookEvent2 = Uuid::uuid7()->toString();
        $webhookEvent3 = Uuid::uuid7()->toString();
        $webhookEvent4 = Uuid::uuid7()->toString();
        $webhookEvent5 = Uuid::uuid7()->toString();
        $webhookEvent6 = Uuid::uuid7()->toString();

        $guidGenerator = new MemoryGuidGenerator([
            $webhookPrId,
            $webhookPrId2,
            $unitId,
            $prozesSbrokiId,
            $webhookEvent1,
            $webhookEvent2,
            $webhookEvent3,
            $webhookEvent4,
            $webhookEvent5,
            $webhookEvent6
        ]);
        self::$container->set(CanGeneateGuid::class, $guidGenerator);

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

        self::$container->set(RunnerApiInterface::class, $memoryRunner);

        $now = new \DateTimeImmutable('2025-10-09 10:00:00');
        $clockService = new TestClockService($now);
        self::$container->set(ClockInterface::class, $clockService);

        $repoId = Uuid::uuid7()->toString();

        $repo = $this->stubRepo($repoId, RepoType::GITLAB, 'repoName', 'http://repoUrl');
        $repo->accessConfirm();

        $repoRepository = self::$container->get(RepoRepository::class);
        /** @var $repoRepository RepoRepository*/
        $repoRepository->save($repo);

        $projectId = Uuid::uuid7()->toString();

        $project = $this->stubProekta($projectId, $userId, 'testcase', $repoId);
        $projectRepository = self::$container->get(ProjectRepository::class);
        /** @var $projectRepository ProjectRepository*/
        $projectRepository->save($project);

        $useCase = self::$container->get(DobavitWebhookProektaUseCase::class);
        /** @var $useCase DobavitWebhookProektaUseCase*/
        $useCase->handle(new SozdatWebhookProekta($projectId, 'http://asd.ru'));
        $useCase->handle(new SozdatWebhookProekta($projectId, 'http://test.ru'));

        $useCase2 = self::$container->get(SozdatUnitUseCase::class);
        /** @var $useCase2 SozdatUnitUseCase*/
        //new SozdatUnitUseCase($spisokUnitovRepo, $projectRepository, $securityService, $guidGenerator, $unitRepo);
        $expectedId = $useCase2->handle(new SozdatUnit(
            $projectId,
            '001',
            'feature/123',
            [],
            3000
        ));

        $this->assertEquals($expectedId, $unitId);

        $useCase = self::$container->get(ObrabotatProzesiUnitaUseCase::class);
        /** @var $useCase ObrabotatProzesiUnitaUseCase*/
        $useCase->handle($unitId);
        $useCase->handle($unitId);
        $useCase->handle($unitId);
        $useCase->handle($unitId);
        $useCase->handle($unitId);
        $useCase->handle($unitId);

        $webhookEventRepo = self::$container->get(WebhookEventRepository::class);
        /** @var WebhookEventRepository $webhookEventRepo*/

        $models = $webhookEventRepo->findAllByWebhookId($webhookPrId);
        $this->assertEquals(3 , count($models));
        $this->assertEquals(WebhookEventPayloadType::SOBRAN, $models[0]->payload->type);
        $this->assertEquals(WebhookEventPayloadType::ZAPUSHEN, $models[2]->payload->type);
        $this->assertEquals(0, $models[2]->getSentInQueue());

        $models = $webhookEventRepo->findAllByWebhookId($webhookPrId2);
        $this->assertEquals(3 , count($models));
        $this->assertEquals(WebhookEventPayloadType::SOBRAN, $models[0]->payload->type);
        $this->assertEquals(WebhookEventPayloadType::ZAPUSHEN, $models[2]->payload->type);
        $this->assertEquals(0, $models[2]->getSentInQueue());

        $sender = self::$container->get(WebhookEventSender::class);
        /** @var $sender WebhookEventSender*/
        $sender->run();

        $models = $webhookEventRepo->findAllByWebhookId($webhookPrId);
        $this->assertEquals(1 , $models[0]->getSentInQueue());
        $this->assertEquals(1 , $models[2]->getSentInQueue());

        $models = $webhookEventRepo->findAllByWebhookId($webhookPrId2);
        $this->assertEquals(1 , $models[0]->getSentInQueue());
        $this->assertEquals(1 , $models[2]->getSentInQueue());

        $webhookProjectRepo = self::$container->get(WebhookProjectRepository::class);
        /** @var $webhookProjectRepo WebhookProjectRepository*/

        $requests = [];

        $expectedRequests = [
            function ($method, $url, $options) use ($webhookEvent1, &$requests): MockResponse {
               // $this->assertEquals('POST', $method);
               // $this->assertEquals('https://asd.ru', $url);
               // $this->assertEquals($webhookEvent1, $options['json']->id);
                $requests[] = [$method, $url, $options];
                return new MockResponse('resp1',['http_code' => 200]);
            },
            function ($method, $url, $options) use ($webhookEvent2, &$requests): MockResponse {
               // $this->assertEquals('POST', $method);
               // $this->assertEquals('https://asd.ru', $url);
               // $this->assertEquals($webhookEvent2, $options['json']->id);
                $requests[] = [$method, $url, $options];
                return new MockResponse('resp2',['http_code' => 200]);
            },
            function ($method, $url, $options) use ($webhookEvent3, &$requests): MockResponse {
               // $this->assertEquals('POST', $method);
               // $this->assertEquals('https://asd.ru', $url);
               // $this->assertEquals($webhookEvent3, $options['json']->id);
                $requests[] = [$method, $url, $options];
                return new MockResponse('error',['http_code' => 500]);
            },
            function ($method, $url, $options) use ($webhookEvent4, &$requests): MockResponse {
               // $this->assertEquals('POST', $method);
               // $this->assertEquals('https://test.ru', $url);
               // $this->assertEquals($webhookEvent4, $options['json']->id);
                $requests[] = [$method, $url, $options];
                return new MockResponse('resp4',['http_code' => 200]);
            },
        ];

        $httpClient = new MockHttpClient($expectedRequests);

        $useCase = new OtpravitEventNaWebhookUseCase($webhookEventRepo, $webhookProjectRepo, $httpClient);
        $useCase->handle($webhookEvent1);
        $useCase->handle($webhookEvent2, true);
        $useCase->handle($webhookEvent3);
        $useCase->handle($webhookEvent4);

        $event = $webhookEventRepo->getById($webhookEvent1);
        $this->assertEquals(new WebhookEventResponse(200, 'resp1') , $event->getResponse());
        $event2 = $webhookEventRepo->getById($webhookEvent2);
        $this->assertEquals(new WebhookEventResponse(200, 'resp2') , $event2->getResponse());
        $event3 = $webhookEventRepo->getById($webhookEvent3);
        $this->assertEquals(null , $event3->getResponse());
        $this->assertEquals('HTTP 500 returned for "http://asd.ru/".' , $event3->getError());

        $event4 = $webhookEventRepo->getById($webhookEvent4);
        $this->assertEquals(new WebhookEventResponse(200, 'resp4') , $event4->getResponse());

        $this->assertEquals('POST', $requests[0][0]);
        $this->assertEquals('http://asd.ru/', $requests[0][1]);
        $this->assertEquals('POST', $requests[1][0]);
        $this->assertEquals('http://test.ru/', $requests[1][1]);
        $this->assertEquals('POST', $requests[2][0]);
        $this->assertEquals('http://asd.ru/', $requests[2][1]);
        $this->assertEquals('POST', $requests[3][0]);
        $this->assertEquals('http://test.ru/', $requests[3][1]);
    }
}
