<?php

namespace App\Unitman\Infra\Projection;

use App\Unitman\Business\Model\Unit\Event\SlomaniyUnitUdalen;
use App\Unitman\Business\Model\Unit\Event\UnitSozdan;
use App\Unitman\Business\Model\Unit\Event\UspehUdaleniyaUnitaUstanovlen;
use App\Unitman\Business\Port\Unit\UnitRepository;
use App\Unitman\Business\Port\UnitmanSecurityService;
use App\Unitman\Business\ReadModel\Unit\WebsocketUnitEventReadModel;
use App\Unitman\Business\Utils\UnitmanClassNameMapEnum;
use App\Unitman\Infra\Jobs\WebsocketUnitEventReadModelJobHandler;
use App\Unitman\Infra\Jobs\ZadachaDlyOcherediService;
use App\Unitman\Infra\Repository\ZadachaDlyOcherediRepository;
use App\Utils\EventSauce\AbstractProjection;
use App\Utils\EventSauce\Model\StreamName;
use EventSauce\EventSourcing\Message;


final class WebsocketUnitEventProjection  extends AbstractProjection implements UnitmanProjection
{
    const PROJECTION_NAME = 'websocket_unit_event';

    public function __construct(
        private ZadachaDlyOcherediService $zadachaDlyOcherediService,
        private ZadachaDlyOcherediRepository $repository,
        private UnitRepository $unitRepository,
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

    function getStreamName(): StreamName
    {
        return new StreamName(UnitmanClassNameMapEnum::Unit->value);
    }

    function isSyncProjection(): bool
    {
        return false;
    }

    function isAllowedRebuild(): bool
    {
        return false;
    }

    /** @psalm-suppress ArgumentTypeCoercion*/
    public function handle(Message $message): void
    {
        $event = $message->payload();
        $reflect = new \ReflectionClass($event);
        $eventClass = $reflect->getName();

        match ($eventClass) {
            UspehUdaleniyaUnitaUstanovlen::class => $this->udalen($event),
            SlomaniyUnitUdalen::class => $this->udalenSlomanii($event),
            UnitSozdan::class => $this->sozdan($event),
            default => $this->obnovlen($event)
        };
    }

    public function sozdatZadachu(WebsocketUnitEventReadModel $model): void
    {
        $this->zadachaDlyOcherediService
            ->dobavitZadachuVOchered(WebsocketUnitEventReadModelJobHandler::QUEUE_NAME, $model, 2, 2);
    }

    public function obnovlen(object $fact): void
    {
        $id = $this->getIdFromEvent($fact);
        $unit = $this->unitRepository->getById($id);
        $user = $this->accountAdapter->getUserById($unit->getAuthorId());

        $this->sozdatZadachu(new WebsocketUnitEventReadModel($id, WebsocketUnitEventReadModel::OBNOVLEN, $user->id, $user->email, $unit->getName()));
    }

    public function sozdan(UnitSozdan $fact): void
    {
        $id = $this->getIdFromEvent($fact);
        $unit = $this->unitRepository->getById($id);
        $user = $this->accountAdapter->getUserById($unit->getAuthorId());

        $this->sozdatZadachu(new WebsocketUnitEventReadModel($fact->id, WebsocketUnitEventReadModel::SOZDAN, $user->id, $user->email, $unit->getName()));
    }

    public function udalen(UspehUdaleniyaUnitaUstanovlen $fact): void
    {
        $id = $this->getIdFromEvent($fact);
        $unit = $this->unitRepository->getById($id);
        $user = $this->accountAdapter->getUserById($unit->getAuthorId());

        $this->sozdatZadachu(new WebsocketUnitEventReadModel($fact->unitId, WebsocketUnitEventReadModel::UDALEN, $user->id, $user->email, $unit->getName()));
    }

    public function udalenSlomanii(SlomaniyUnitUdalen $fact): void
    {
        $id = $this->getIdFromEvent($fact);
        $unit = $this->unitRepository->getById($id);
        $user = $this->accountAdapter->getUserById($unit->getAuthorId());

        $this->sozdatZadachu(new WebsocketUnitEventReadModel($fact->unitId, WebsocketUnitEventReadModel::UDALEN, $user->id, $user->email, $unit->getName()));
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
        } elseif (property_exists($fact, 'unitId')) {
            $id = $fact->unitId;
        } else {
            throw new \Exception('unit.websocket_unit_event.not_found_id');
        }
        return $id;
    }
}
