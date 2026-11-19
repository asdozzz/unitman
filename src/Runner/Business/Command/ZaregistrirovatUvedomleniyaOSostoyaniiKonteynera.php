<?php

declare(strict_types=1);

namespace App\Runner\Business\Command;

use App\Utils\Converter\JsonBodySerializableInterface;

/**
 * Пакет уведомлений раннера о состоянии контейнеров юнитов.
 */
final class ZaregistrirovatUvedomleniyaOSostoyaniiKonteynera implements JsonBodySerializableInterface
{
    /**
     * @param UvedomlenieOSostoyaniiKonteynera[] $notifications
     */
    public function __construct(
        public readonly string $runnerId,
        public readonly array $notifications = [],
    ) {
    }
}
