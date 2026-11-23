<?php

namespace App\Unitman\Business\ReadModel;

use App\Unitman\Business\Model\Project\StatusVetki;

/**
 * Снимок ветки, полученный от Git-провайдера.
 *
 * Для `ACTIVE` заполнен `posledniyKommitUnixtime` — по нему polling решает,
 * обновился ли код. Для `DELETED`/`MERGED` время коммита не нужно.
 */
final readonly class SostoyanieVetki
{
    public function __construct(
        public string $name,
        public StatusVetki $status,
        public ?int $posledniyKommitUnixtime = null,
    ) {
    }
}
