<?php

declare(strict_types=1);

namespace App\Runner\Business\ReadModel;

use App\Runner\Business\Model\RunnerState\RunnerResources;

/**
 * Состояние ресурсов сервера раннера для администраторов.
 *
 * `blocked` — уровень «чёрный»: ресурсоёмкие операции заблокированы.
 * `healthFresh` — health-push приходил недавно (иначе данные устарели).
 */
final class ResursiRunneraReadModel implements \JsonSerializable
{
    public function __construct(
        public bool $active,
        public bool $healthFresh,
        public int $lastHealthAt,
        public bool $blocked,
        public ?RunnerResources $resources,
    ) {
    }

    public function jsonSerialize(): mixed
    {
        return get_object_vars($this);
    }
}
