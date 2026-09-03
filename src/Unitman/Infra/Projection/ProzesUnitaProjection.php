<?php

namespace App\Unitman\Infra\Projection;

use App\Unitman\Business\Model\Unit\Event\DobavlenProzesVUnit;

use App\Unitman\Business\Model\Unit\Event\UnitSozdan;
use App\Unitman\Business\Model\Unit\Event\UnitSozdanSystemoi;
use App\Unitman\Business\ReadModel\Unit\ProzesUnita;
use App\Unitman\Business\Utils\UnitmanClassNameMapEnum;
use App\Unitman\Infra\Repository\Unit\ProzesUnitaRepository;
use App\Utils\EventSauce\AbstractProjection;
use App\Utils\EventSauce\Model\StreamName;
use EventSauce\EventSourcing\Message;

final class ProzesUnitaProjection extends AbstractProjection implements UnitmanProjection
{
    const PROJECTION_NAME = 'prozesi_unitov';

    public function __construct(private ProzesUnitaRepository $repository)
    {
    }

    function isSyncProjection(): bool
    {
        return false;
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
        $this->repository->resetById($id);
    }

    function getStreamName(): StreamName
    {
        return new StreamName(UnitmanClassNameMapEnum::Unit->value);
    }

    /** @psalm-suppress ArgumentTypeCoercion*/
    public function handle(Message $message): void
    {
        $event = $message->payload();
        $reflect = new \ReflectionClass($event);
        $eventClass = $reflect->getName();

        match ($eventClass) {
            DobavlenProzesVUnit::class => $this->dobavit($event),
            default => $this->obnovlen($event)
        };
    }

    private function obnovlen(object $event): void
    {
        if (isset($event->prozess)) {
            $jobs = [];

            foreach ($event->prozess['jobs'] as $jobData) {
                $steps = [];
                foreach ($jobData['steps'] as $stepData) {
                    $steps[] = new ProzesUnita\ShagZadachiUnita(
                        $stepData['command'],
                        $stepData['response'],
                        $stepData['success'],
                        $stepData['unixtime'],
                    );
                }

                $jobs[] = new ProzesUnita\ZadachaProzesaUnita(
                    $jobData['id'],
                    $jobData['type'],
                    $jobData['state'],
                    $steps
                );
            }

            $model = new ProzesUnita(
                $event->prozess['id'],
                $event->unitId,
                $event->prozess['userId'],
                $event->prozess['type'],
                $event->prozess['state'],
                $jobs
            );

            $this->repository->update($model);
        }
    }

    private function dobavit(object $event): void
    {
        $jobs = [];

        foreach ($event->prozes['jobs'] as $jobData) {
            $steps = [];
            foreach ($jobData['steps'] as $stepData) {
                $steps[] = new ProzesUnita\ShagZadachiUnita(
                    $stepData['command'],
                    $stepData['response'],
                    $stepData['success'],
                    $stepData['unixtime'],
                );
            }

            $jobs[] = new ProzesUnita\ZadachaProzesaUnita(
                $jobData['id'],
                $jobData['type'],
                $jobData['state'],
                $steps
            );
        }

        $model = new ProzesUnita(
            $event->prozes['id'],
            $event->unitId,
            $event->prozes['userId'],
            $event->prozes['type'],
            $event->prozes['state'],
            $jobs
        );
        $this->repository->insert($model);
    }
}
