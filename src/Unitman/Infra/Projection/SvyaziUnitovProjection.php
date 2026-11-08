<?php

declare(strict_types=1);

namespace App\Unitman\Infra\Projection;

use App\Unitman\Business\Model\Unit\Event\KonfigUnitaUstanovlen;
use App\Unitman\Business\Model\Unit\Event\PeremenieUnitaZapolneni;
use App\Unitman\Business\Model\Unit\Event\SlomaniyUnitUdalen;
use App\Unitman\Business\Model\Unit\Event\UnitSozdan;
use App\Unitman\Business\Model\Unit\Event\UspehUdaleniyaUnitaUstanovlen;
use App\Unitman\Business\Utils\SvyaziPeremenihUtil;
use App\Unitman\Business\Utils\UnitmanClassNameMapEnum;
use App\Unitman\Infra\Repository\Unit\SvyaziUnitovRepository;
use App\Utils\EventSauce\AbstractProjection;
use App\Utils\EventSauce\Model\StreamName;
use EventSauce\EventSourcing\Message;

/**
 * Проекция связей переменных конфига с юнитами, выбранными через хелпер.
 *
 * Пишет только в `svyazi_unitov`; read-модель списка юнитов обновляется
 * отдельно в {@see SpisokUnitovProjection}.
 */
final class SvyaziUnitovProjection extends AbstractProjection implements UnitmanProjection
{
    const CODE = 'svyazi_unitov';

    public function __construct(private SvyaziUnitovRepository $repository)
    {
    }

    public function getPriority(): int
    {
        return 900;
    }

    function isSyncProjection(): bool
    {
        return true;
    }

    function getProjectionName(): string
    {
        return self::CODE;
    }

    function getStreamName(): StreamName
    {
        return new StreamName(UnitmanClassNameMapEnum::Unit->value);
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
        $this->repository->resetById($id);
    }

    /**
     * Проекция получает все события потока юнита, поэтому необработанные
     * события пропускаются молча (в отличие от базовой реализации).
     */
    public function handle(Message $message): void
    {
        $event = $message->payload();

        match (true) {
            $event instanceof UnitSozdan => $this->repository->replaceForUnit($event->id, $event->svyazi),
            $event instanceof PeremenieUnitaZapolneni => $this->repository->replaceForUnit($event->unitId, $event->svyazi),
            $event instanceof KonfigUnitaUstanovlen => $this->soglasovatSKonfigom($event),
            $event instanceof UspehUdaleniyaUnitaUstanovlen,
            $event instanceof SlomaniyUnitUdalen => $this->repository->deleteByUnit($event->unitId),
            default => null,
        };
    }

    /**
     * Убирает связи переменных, у которых хелпер больше не настроен,
     * и проставляет `project_id` цели из хелпера.
     */
    private function soglasovatSKonfigom(KonfigUnitaUstanovlen $event): void
    {
        $pomoschniki = SvyaziPeremenihUtil::poluchitPomoschnikiPoPeremennim($event->configUnita);
        $tekushie = $this->repository->poluchitPoIdUnita($event->unitId);

        $svyazi = [];
        foreach ($tekushie as $svyaz) {
            if (!isset($pomoschniki[$svyaz['variableId']])) {
                continue;
            }

            $svyazi[] = [
                'variableId' => $svyaz['variableId'],
                'targetUnitId' => $svyaz['targetUnitId'],
                'projectId' => $pomoschniki[$svyaz['variableId']]['projectId'],
            ];
        }

        $this->repository->replaceForUnit($event->unitId, $svyazi);
    }
}
