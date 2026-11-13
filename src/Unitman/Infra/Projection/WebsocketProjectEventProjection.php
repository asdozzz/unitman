<?php

namespace App\Unitman\Infra\Projection;

use App\Unitman\Business\Model\Account;
use App\Unitman\Business\Model\Project\Event\ProjectWasAdded;
use App\Unitman\Business\Model\Project\Event\ProjectWasDeleted;
use App\Unitman\Business\Model\Project\Event\ProjectWasDeletedManually;
use App\Unitman\Business\Port\Project\ProjectRepository;
use App\Unitman\Business\Port\UnitmanSecurityService;
use App\Unitman\Business\ReadModel\Project\WebsocketProjectEventReadModel;
use App\Unitman\Business\Utils\UnitmanClassNameMapEnum;
use App\Unitman\Infra\Jobs\WebsocketProjectEventReadModelJobHandler;
use App\Unitman\Infra\Jobs\ZadachaDlyOcherediService;
use App\Unitman\Infra\Repository\ZadachaDlyOcherediRepository;
use App\Utils\EventSauce\AbstractProjection;
use App\Utils\EventSauce\Model\StreamName;
use EventSauce\EventSourcing\Message;

final class WebsocketProjectEventProjection extends AbstractProjection implements UnitmanProjection
{
    const PROJECTION_NAME = 'websocket_project_event';

    const AUTHOR_HEADER = 'executed_by';

    public function __construct(
        private ZadachaDlyOcherediService $zadachaDlyOcherediService,
        private ZadachaDlyOcherediRepository $repository,
        private ProjectRepository $projectRepository,
        private UnitmanSecurityService $accountAdapter
    )
    {
    }

    function getProjectionName(): string
    {
        return self::PROJECTION_NAME;
    }

    function init(): void
    {
        $this->repository->init();
    }

    function destroy(): void
    {
        $this->repository->destroy();
    }

    function reset(): void
    {
        $this->repository->truncate();
    }

    function resetById(string $id): void
    {

    }

    function getStreamName(): StreamName
    {
        return new StreamName(UnitmanClassNameMapEnum::Project->value);
    }

    function isSyncProjection(): bool
    {
        return false;
    }

    function isAllowedRebuild(): bool
    {
        return false;
    }

    public function handle(Message $message): void
    {
        $event = $message->payload();
        $reflect = new \ReflectionClass($event);
        $eventClass = $reflect->getName();

        match ($eventClass) {
            ProjectWasAdded::class => $this->sozdan($event, $message),
            ProjectWasDeleted::class => $this->udalen($event, $message),
            ProjectWasDeletedManually::class => $this->udalen($event, $message),
            default => $this->obnovlen($event, $message)
        };
    }

    public function sozdatZadachu(WebsocketProjectEventReadModel $model): void
    {
        $this->zadachaDlyOcherediService
            ->dobavitZadachuVOchered(WebsocketProjectEventReadModelJobHandler::QUEUE_NAME, $model, 2, 2);
    }

    public function obnovlen(object $fact, Message $message): void
    {
        $this->sozdatZadachu($this->sozdatModel($fact, WebsocketProjectEventReadModel::OBNOVLEN, $message));
    }

    public function sozdan(object $fact, Message $message): void
    {
        $this->sozdatZadachu($this->sozdatModel($fact, WebsocketProjectEventReadModel::SOZDAN, $message));
    }

    private function udalen(object $fact, Message $message): void
    {
        $this->sozdatZadachu($this->sozdatModel($fact, WebsocketProjectEventReadModel::UDALEN, $message));
    }

    private function sozdatModel(object $fact, string $eventType, Message $message): WebsocketProjectEventReadModel
    {
        $id = $this->getIdFromEvent($fact);
        // Проект читается из событий, поэтому имя доступно и после удаления.
        $project = $this->projectRepository->getById($id);
        $user = $this->poluchitAvtora($message);

        return new WebsocketProjectEventReadModel($id, $eventType, $user->id, $user->email, $project->getName());
    }

    /**
     * Автор события берётся из заголовка сообщения: у проекта нет
     * собственного поля автора, а действия из фоновых процессов
     * (сборка, удаление, очистка) пишутся с пустым author'ом.
     */
    private function poluchitAvtora(Message $message): Account
    {
        $userId = $message->header(self::AUTHOR_HEADER);

        if (is_string($userId) && $userId !== '') {
            try {
                return $this->accountAdapter->getUserById($userId);
            } catch (\Throwable) {
                // Автор мог быть удалён — событие всё равно должно уйти в Centrifugo.
            }
        }

        return $this->accountAdapter->getSystemUser();
    }

    /**
     * @param object $fact
     * @return mixed
     * @throws \Exception
     */
    public function getIdFromEvent(object $fact)
    {
        if (property_exists($fact, 'id')) {
            $id = $fact->id;
        } elseif (property_exists($fact, 'projectId')) {
            $id = $fact->projectId;
        } else {
            throw new \Exception('project.websocket_project_event.not_found_id');
        }
        return $id;
    }
}
