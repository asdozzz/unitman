<?php

namespace App\Unitman\Tests\UseCase\Proekt;

use App\Unitman\Business\Command\Project\Webhook\IzmenitUrlWebhooka;
use App\Unitman\Business\Command\Project\Webhook\SozdatWebhookProekta;
use App\Unitman\Business\Port\CanGeneateGuid;
use App\Unitman\Business\Port\Project\WebhookProjectRepository;
use App\Unitman\Business\Port\UnitmanSecurityService;
use App\Unitman\Business\UseCase\Project\Webhook\DobavitWebhookProektaUseCase;
use App\Unitman\Business\UseCase\Project\Webhook\IzmenitUrlWebhookaUseCase;
use App\Unitman\Business\UseCase\Project\Webhook\OtkluchitWebhookProektaUseCase;
use App\Unitman\Business\UseCase\Project\Webhook\UdalitWebhookProektaUseCase;
use App\Unitman\Business\UseCase\Project\Webhook\VkluchitWebhookProektaUseCase;
use App\Unitman\Infra\Adapter\MemoryGuidGenerator;
use App\Unitman\Tests\UseCase\Unit\AbstractUnitUseCase;
use Ramsey\Uuid\Uuid;

final class WebhookProektaUseCaseTest extends AbstractUnitUseCase
{
    /**
     * @test
     * */
    function sozdan_i_udalen()
    {
        $securityService = $this->getMockBuilder(UnitmanSecurityService::class)->getMock();
        $securityService->expects($this->any())->method('isAdmin')->willReturn(true);
        self::$container->set(UnitmanSecurityService::class, $securityService);

        $webhookId = Uuid::uuid7()->toString();
        $webhookIdDouble = Uuid::uuid7()->toString();
        $projectId = Uuid::uuid7()->toString();
        self::$container->set(CanGeneateGuid::class, new MemoryGuidGenerator([$webhookId, $webhookIdDouble]));

        $useCase = self::$container->get(DobavitWebhookProektaUseCase::class);
        $useCase->handle(new SozdatWebhookProekta($projectId,'http://asd.ru'));

        $repository = self::$container->get(WebhookProjectRepository::class);
        /* @var $repository WebhookProjectRepository*/
        $readModel = $repository->getById($webhookId);
        $this->assertEquals('http://asd.ru', $readModel->getUrl());
        $this->assertEquals(true, $readModel->isActive());
        $this->assertEquals(false, $readModel->isDeleted());
        $this->assertEquals($projectId, $readModel->projectId);

        $useCase = self::$container->get(OtkluchitWebhookProektaUseCase::class);
        $useCase->handle($webhookId);

        $readModel = $repository->getById($webhookId);
        $this->assertEquals(false, $readModel->isActive());

        $useCase = self::$container->get(VkluchitWebhookProektaUseCase::class);
        $useCase->handle($webhookId);

        $readModel = $repository->getById($webhookId);
        $this->assertEquals(true, $readModel->isActive());

        $useCase = self::$container->get(IzmenitUrlWebhookaUseCase::class);
        $useCase->handle(new IzmenitUrlWebhooka($webhookId, 'http://asd2.com'));

        $readModel = $repository->getById($webhookId);
        $this->assertEquals('http://asd2.com', $readModel->getUrl());

        $this->expectExceptionMessage('proekt.webhook.double');
        $useCase = self::$container->get(DobavitWebhookProektaUseCase::class);
        $useCase->handle(new SozdatWebhookProekta($projectId,'http://asd2.com'));

        $useCase = self::$container->get(UdalitWebhookProektaUseCase::class);
        $useCase->handle($webhookId);

        $readModel = $repository->getById($webhookId);
        $this->assertEquals(true, $readModel->isDeleted());
        $this->assertEquals(false, $readModel->isActive());

        $useCase = self::$container->get(DobavitWebhookProektaUseCase::class);
        $useCase->handle(new SozdatWebhookProekta($projectId,'http://asd2.com'));
    }
}
