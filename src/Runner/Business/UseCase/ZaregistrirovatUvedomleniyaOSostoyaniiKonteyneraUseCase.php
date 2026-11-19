<?php

declare(strict_types=1);

namespace App\Runner\Business\UseCase;

use App\Runner\Business\Command\UvedomlenieOSostoyaniiKonteynera;
use App\Runner\Business\Command\ZaregistrirovatUvedomleniyaOSostoyaniiKonteynera;
use App\Runner\Business\Port\UmeetObnovlyatSostoyanieKonteyneraUnita;
use Symfony\Component\HttpKernel\Exception\BadRequestHttpException;

final class ZaregistrirovatUvedomleniyaOSostoyaniiKonteyneraUseCase
{
    public function __construct(
        private UmeetObnovlyatSostoyanieKonteyneraUnita $stateUpdater,
    ) {
    }

    public function handle(ZaregistrirovatUvedomleniyaOSostoyaniiKonteynera $command): void
    {
        if ($command->notifications === []) {
            throw new BadRequestHttpException('runner.unit_state_payload_empty');
        }

        foreach ($command->notifications as $notification) {
            $notification = $this->normalizovat($notification);

            $this->stateUpdater->obnovit(
                unitId: $notification->unitId,
                mismatch: $notification->eventType === 'mismatch',
                expectedState: $notification->expectedState,
                actualState: $notification->actualState,
                detectedAt: $notification->detectedAt,
            );
        }
    }

    /**
     * Приводит элемент пакета к доменному DTO.
     *
     * Сериализатор (`opcache.save_comments=0`) не извлекает вложенный тип из
     * phpdoc-контракта, поэтому элементы `notifications` могут прийти обычными
     * массивами. Контракт JSON при этом не меняется.
     */
    private function normalizovat(mixed $notification): UvedomlenieOSostoyaniiKonteynera
    {
        if ($notification instanceof UvedomlenieOSostoyaniiKonteynera) {
            return $notification;
        }

        if (!is_array($notification)) {
            throw new BadRequestHttpException('runner.unit_state_payload_invalid');
        }

        return new UvedomlenieOSostoyaniiKonteynera(
            unitId: (string) ($notification['unitId'] ?? ''),
            projectId: (string) ($notification['projectId'] ?? ''),
            unitName: (string) ($notification['unitName'] ?? ''),
            projectName: (string) ($notification['projectName'] ?? ''),
            expectedState: (string) ($notification['expectedState'] ?? ''),
            actualState: (string) ($notification['actualState'] ?? ''),
            eventType: (string) ($notification['eventType'] ?? ''),
            detectedAt: (int) ($notification['detectedAt'] ?? 0),
        );
    }
}
