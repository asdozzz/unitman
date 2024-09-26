<?php

namespace App\Unitman\Infra\Projection;

use App\Unitman\Business\Model\Unit\Event\OshibkaOstanovkiUnitaUstanovlena;
use App\Unitman\Business\Model\Unit\Event\SlomaniyUnitUdalen;
use App\Unitman\Business\Model\Unit\Event\UnitSozdan;
use App\Unitman\Business\Model\Unit\Event\UnitSozdanSystemoi;
use App\Unitman\Business\Model\Unit\Event\UspehOstanovkiUnitaUstanovlen;
use App\Unitman\Business\Model\Unit\Event\UspehUdaleniyaUnitaUstanovlen;
use App\Unitman\Business\Model\Unit\Event\UspehZapuskaUnitaUstanovlen;
use App\Unitman\Business\Port\Project\ProjectRepository;
use App\Unitman\Business\Port\Unit\UnitRepository;
use App\Unitman\Business\Port\UnitmanSecurityService;
use App\Unitman\Business\ReadModel\Dashboard\StatistikaPoProektu;
use App\Unitman\Business\Utils\UnitmanClassNameMapEnum;
use App\Unitman\Infra\Repository\Unit\StatistikaPoProektuRepository;
use App\Utils\EventSauce\AbstractProjection;
use App\Utils\EventSauce\Model\StreamName;
use EventSauce\EventSourcing\Message;

final class StatistikaPoProektuProjection extends AbstractProjection implements UnitmanProjection
{
    const PROJECTION_NAME = 'statistika_po_proektu';

    public function __construct(
        private StatistikaPoProektuRepository $statistikaPoProektuRepository,
        private UnitRepository $unitRepository,
        private ProjectRepository $projectRepository
    )
    {
    }
    function getProjectionName(): string
    {
        return self::PROJECTION_NAME;
    }

    function reset(): void
    {
        $this->statistikaPoProektuRepository->truncate();
    }

    function init(): void
    {
        $this->statistikaPoProektuRepository->init();
    }

    function destroy(): void
    {
        $this->statistikaPoProektuRepository->destroy();
    }

    function getStreamName(): StreamName
    {
        return new StreamName(UnitmanClassNameMapEnum::Unit->value);
    }

    function isSyncProjection(): bool
    {
        return false;
    }

    public function handle(Message $message): void
    {
        $event = $message->payload();

        match (get_class($event)) {
            UnitSozdan::class,UnitSozdanSystemoi::class => $this->sozdan($event),
            UspehUdaleniyaUnitaUstanovlen::class,SlomaniyUnitUdalen::class => $this->udalen($event),
            default => function () {}
        };
    }

    private function sozdan(UnitSozdan|UnitSozdanSystemoi $event): void
    {
        $callback = function (StatistikaPoProektu $readModel): void {
            $readModel->total++;
            $readModel->active++;
        };

        $this->updateStat($event->projectId, $callback);
    }

    private function udalen(UspehUdaleniyaUnitaUstanovlen|SlomaniyUnitUdalen $event): void
    {
        $unit = $this->unitRepository->getById($event->unitId);
        $callback = function (StatistikaPoProektu $readModel): void {
            $readModel->active--;
            $readModel->deleted++;
        };

        $this->updateStat($unit->getProjectId(), $callback);
    }

    /**
     * @param string $projectId
     * @param \Closure(StatistikaPoProektu):void $callback
     * @return void
     */
    private function updateStat(string $projectId, \Closure $callback): void
    {
        $readModel = $this->statistikaPoProektuRepository->findById($projectId);

        if (empty($readModel)) {
            $project = $this->projectRepository->getById($projectId);
            $readModel = new StatistikaPoProektu($projectId, $project->getName());
            $callback($readModel);
            $this->statistikaPoProektuRepository->insert($readModel);
        } else {
            $callback($readModel);
            $this->statistikaPoProektuRepository->update($readModel);
        }
    }

}
