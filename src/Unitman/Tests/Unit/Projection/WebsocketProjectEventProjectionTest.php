<?php

declare(strict_types=1);

namespace App\Unitman\Tests\Unit\Projection;

use App\Unitman\Business\Command\Project\AddProject;
use App\Unitman\Business\Model\Project;
use App\Unitman\Business\Model\Project\Event\PeremenayaDobavlenaVProekt;
use App\Unitman\Business\Model\Project\Event\ProjectWasAdded;
use App\Unitman\Business\Model\Project\Event\ProjectWasDeleted;
use App\Unitman\Business\Model\Project\Event\ProjectWasDeletedManually;
use App\Unitman\Business\ReadModel\Project\WebsocketProjectEventReadModel;
use App\Unitman\Infra\Jobs\WebsocketProjectEventReadModelJobHandler;
use App\Unitman\Infra\Jobs\ZadachaDlyOcherediService;
use App\Unitman\Infra\Projection\WebsocketProjectEventProjection;
use App\Unitman\Infra\Repository\ZadachaDlyOcherediRepository;
use App\Unitman\Tests\Support\FakeUnitmanSecurityService;
use App\Unitman\Tests\Support\InMemoryProjectRepository;
use App\Utils\EventSauce\AbstractTestCaseWithTransactionWrapper;
use EventSauce\EventSourcing\Message;
use PHPUnit\Framework\Attributes\Test;

/**
 * Проекция websocket-событий проекта: события агрегата Project превращаются
 * в задачи очереди `websocket_project_event` для публикации в Centrifugo.
 */
final class WebsocketProjectEventProjectionTest extends AbstractTestCaseWithTransactionWrapper
{
    private const PROJECT_ID = 'project-1';
    private const AUTHOR_ID = 'author-1';

    private ZadachaDlyOcherediRepository $repository;
    private InMemoryProjectRepository $projectRepository;
    private WebsocketProjectEventProjection $projection;

    public function setUp(): void
    {
        parent::setUp();

        $this->repository = self::$container->get(ZadachaDlyOcherediRepository::class);
        $this->repository->init();

        $this->projectRepository = new InMemoryProjectRepository();
        $this->projectRepository->save($this->sozdatProekt());

        $this->projection = new WebsocketProjectEventProjection(
            self::$container->get(ZadachaDlyOcherediService::class),
            $this->repository,
            $this->projectRepository,
            new FakeUnitmanSecurityService(self::AUTHOR_ID)
        );
    }

    #[Test]
    public function sozdanie_proekta_otpravlyaet_sobitie_sozdan(): void
    {
        $this->projection->handle($this->message(
            new ProjectWasAdded(self::PROJECT_ID, 'repo-1', 'code-1', 'project-1', 'master', 'https://test.ru', memoryLimit: 1000),
            self::AUTHOR_ID
        ));

        $payload = $this->poluchitEdinstvennyiPayload();

        $this->assertSame(WebsocketProjectEventReadModel::SOZDAN, $payload['eventType']);
        $this->assertSame(self::PROJECT_ID, $payload['id']);
        $this->assertSame(self::AUTHOR_ID, $payload['userId']);
        $this->assertSame(self::AUTHOR_ID . '@test.ru', $payload['userEmail']);
        $this->assertSame('project-' . self::PROJECT_ID, $payload['projectName']);
    }

    #[Test]
    public function izmenenie_proekta_otpravlyaet_sobitie_obnovlen(): void
    {
        $this->projection->handle($this->message(
            new PeremenayaDobavlenaVProekt(self::PROJECT_ID, 'string', 'API_URL', 'http://api'),
            self::AUTHOR_ID
        ));

        $payload = $this->poluchitEdinstvennyiPayload();

        $this->assertSame(WebsocketProjectEventReadModel::OBNOVLEN, $payload['eventType']);
        $this->assertSame(self::PROJECT_ID, $payload['id']);
        $this->assertSame(self::AUTHOR_ID, $payload['userId']);
        $this->assertSame('project-' . self::PROJECT_ID, $payload['projectName']);
    }

    #[Test]
    public function udalenie_proekta_otpravlyaet_sobitie_udalen_s_imyaem_proekta(): void
    {
        $this->projection->handle($this->message(new ProjectWasDeleted(self::PROJECT_ID, [])));

        $payload = $this->poluchitEdinstvennyiPayload();

        $this->assertSame(WebsocketProjectEventReadModel::UDALEN, $payload['eventType']);
        $this->assertSame(self::PROJECT_ID, $payload['id']);
        // Имя проекта восстанавливается из событий, поэтому доступно и после удаления.
        $this->assertSame('project-' . self::PROJECT_ID, $payload['projectName']);
    }

    #[Test]
    public function ruchnoe_udalenie_proekta_otpravlyaet_sobitie_udalen(): void
    {
        $this->projection->handle($this->message(new ProjectWasDeletedManually(self::PROJECT_ID), self::AUTHOR_ID));

        $payload = $this->poluchitEdinstvennyiPayload();

        $this->assertSame(WebsocketProjectEventReadModel::UDALEN, $payload['eventType']);
        $this->assertSame(self::AUTHOR_ID, $payload['userId']);
    }

    #[Test]
    public function bez_avtora_v_zagolovke_ispolzuetsya_sistemnyi_polzovatel(): void
    {
        $this->projection->handle($this->message(new ProjectWasDeletedManually(self::PROJECT_ID)));

        $payload = $this->poluchitEdinstvennyiPayload();

        $this->assertSame('system', $payload['userId']);
        $this->assertSame('system@test.ru', $payload['userEmail']);
    }

    #[Test]
    public function sobitie_bez_identifikatora_proekta_vybrasyvaet_oshibku(): void
    {
        $this->expectException(\Exception::class);
        $this->expectExceptionMessage('project.websocket_project_event.not_found_id');

        $this->projection->getIdFromEvent(new \stdClass());
    }

    #[Test]
    public function identifikator_beretsya_iz_polya_project_id(): void
    {
        $this->assertSame(
            self::PROJECT_ID,
            $this->projection->getIdFromEvent(new PeremenayaDobavlenaVProekt(self::PROJECT_ID, 'string', 'CODE', 'value'))
        );
    }

    private function message(object $event, ?string $authorId = null): Message
    {
        return new Message($event, $authorId === null ? [] : [
            WebsocketProjectEventProjection::AUTHOR_HEADER => $authorId,
        ]);
    }

    /**
     * @return array<string, mixed>
     */
    private function poluchitEdinstvennyiPayload(): array
    {
        $rows = self::$db->fetchAllAssociative(
            'SELECT payload FROM ' . ZadachaDlyOcherediRepository::TABLE . ' WHERE queue_name = :queueName',
            ['queueName' => WebsocketProjectEventReadModelJobHandler::QUEUE_NAME]
        );

        $this->assertCount(1, $rows);

        $payload = json_decode((string) $rows[0]['payload'], true);
        $this->assertIsArray($payload);

        return $payload;
    }

    private function sozdatProekt(): Project
    {
        return Project::addProject(
            self::PROJECT_ID,
            new AddProject('repo-1', 'code-1', 'project-' . self::PROJECT_ID, 'master', 'https://test.ru', 1000),
            self::AUTHOR_ID
        );
    }
}
