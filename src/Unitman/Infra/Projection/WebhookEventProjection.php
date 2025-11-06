<?php

namespace App\Unitman\Infra\Projection;

use App\Unitman\Business\Model\Unit\Event\SlomaniyUnitUdalen;
use App\Unitman\Business\Model\Unit\Event\UspehObnovleniyaUnitaUstanovlen;
use App\Unitman\Business\Model\Unit\Event\UspehOstanovkiUnitaUstanovlen;
use App\Unitman\Business\Model\Unit\Event\UspehPodgotovkiUnitaUstanovlen;
use App\Unitman\Business\Model\Unit\Event\UspehSborkiUnitaUstanovlen;
use App\Unitman\Business\Model\Unit\Event\UspehSbrosaPodgotovkiUnitaUstanovlen;
use App\Unitman\Business\Model\Unit\Event\UspehUdaleniyaUnitaUstanovlen;
use App\Unitman\Business\Model\Unit\Event\UspehZapuskaUnitaUstanovlen;
use App\Unitman\Business\Port\CanGeneateGuid;
use App\Unitman\Business\Port\Project\ProjectRepository;
use App\Unitman\Business\Port\Project\WebhookProjectRepository;
use App\Unitman\Business\Port\Unit\UnitRepository;
use App\Unitman\Business\Port\UnitmanSecurityService;
use App\Unitman\Business\ReadModel\Unit\WebhookEvent;
use App\Unitman\Business\ReadModel\Unit\WebhookEvent\WebhookEventPayload;
use App\Unitman\Business\ReadModel\Unit\WebhookEvent\WebhookEventPayload\WebhookEventPayloadType;
use App\Unitman\Business\Utils\UnitmanClassNameMapEnum;
use App\Unitman\Infra\Jobs\ZadachaDlyOcherediService;
use App\Unitman\Infra\Repository\Unit\SqlWebhookEventRepository;
use App\Utils\EventSauce\AbstractProjection;
use App\Utils\EventSauce\Model\StreamName;
use EventSauce\EventSourcing\Message;
use Psr\Clock\ClockInterface;

final class WebhookEventProjection implements UnitmanProjection
{
    const PROJECTION_NAME = 'webhook_event';

    public function __construct(
        private SqlWebhookEventRepository $webhookEventRepository,
        private UnitRepository            $unitRepository,
        private ProjectRepository         $projectRepository,
        private UnitmanSecurityService    $securityService,
        private ClockInterface            $clock,
        private CanGeneateGuid            $canGeneateGuid,
        private WebhookProjectRepository  $webhookProjectRepository
    )
    {
    }

    function getProjectionName(): string
    {
        return self::PROJECTION_NAME;
    }

    function reset(): void
    {
        $this->webhookEventRepository->truncate();
    }

    function resetById(string $id): void
    {
        $this->webhookEventRepository->resetById($id);
    }

    function init(): void
    {
        $this->webhookEventRepository->init();
    }

    function destroy(): void
    {
        $this->webhookEventRepository->destroy();
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

    public function handle(Message $message): void
    {
        $event = $message->payload();

        $type = null;
        if ($event::class === UspehSborkiUnitaUstanovlen::class) {
            $type = WebhookEventPayloadType::SOBRAN;
        }
        if ($event::class === UspehObnovleniyaUnitaUstanovlen::class) {
            $type = WebhookEventPayloadType::OBNOVLEN;
        }
        if ($event::class === UspehPodgotovkiUnitaUstanovlen::class) {
            $type = WebhookEventPayloadType::PODGOTOVLEN;
        }
        if ($event::class === UspehZapuskaUnitaUstanovlen::class) {
            $type = WebhookEventPayloadType::ZAPUSHEN;
        }
        if ($event::class === UspehOstanovkiUnitaUstanovlen::class) {
            $type = WebhookEventPayloadType::OSTANOVLEN;
        }
        if ($event::class === UspehSbrosaPodgotovkiUnitaUstanovlen::class) {
            $type = WebhookEventPayloadType::SBROSHENA_PODGOTOVKA;
        }
        if ($event::class === UspehUdaleniyaUnitaUstanovlen::class) {
            $type = WebhookEventPayloadType::UDALEN;
        }
        if ($event::class === SlomaniyUnitUdalen::class) {
            $type = WebhookEventPayloadType::UDALEN_VRUCHNUYU;
        }

        if (!empty($type)) {
            $unit = $this->unitRepository->getById($event->unitId);
            $project = $this->projectRepository->getById($unit->getProjectId());
            $account = $this->securityService->getUserById($unit->getAuthorId());
            $payload = new WebhookEventPayload(
                $type,
                new WebhookEventPayload\WebhookEventPayloadAccount($account->id, $account->email),
                new WebhookEventPayload\WebhookEventPayloadProject($project->getId(), $project->getName()),
                new WebhookEventPayload\WebhookEventPayloadUnit($unit->getId(), $unit->getName(), $unit->getBranch()),
                $this->clock->now()->getTimestamp()
            );

            $projectWebhooks = $this->webhookProjectRepository->getAllActiveByProjectId($project->getId());

            foreach ($projectWebhooks as $projectWebhook) {
                $webhookEvent = new WebhookEvent(
                    $this->canGeneateGuid->makeGuid(),
                    $projectWebhook->id,
                    $payload
                );
                $this->webhookEventRepository->insert($webhookEvent);
            }
        }
    }

    function getPriority(): int
    {
        return 10;
    }
}
