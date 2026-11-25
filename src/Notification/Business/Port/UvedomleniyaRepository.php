<?php

declare(strict_types=1);

namespace App\Notification\Business\Port;

use App\Notification\Business\Model\Uvedomlenie;
use App\Notification\Business\ReadModel\UvedomlenieReadModel;

interface UvedomleniyaRepository
{
    /**
     * Сохраняет уведомление, получателей и запись доставки в одной транзакции.
     *
     * @param list<string> $recipientIds
     */
    public function save(Uvedomlenie $uvedomlenie, array $recipientIds, string $channel): void;

    public function findByDedupKey(string $dedupKey): ?Uvedomlenie;

    public function findById(string $id): ?Uvedomlenie;

    /**
     * @return list<UvedomlenieReadModel>
     */
    public function findDlyaPolzovatelya(string $accountId, int $limit, bool $onlyActionable): array;

    public function schetchikNeprochitannih(string $accountId): int;

    public function estPoluchatelem(string $notificationId, string $accountId): bool;

    public function otmetitProchitannim(string $notificationId, string $accountId, int $now): bool;

    public function otmetitVseProchitannimi(string $accountId, int $now): int;

    public function udalitStarie(bool $requiresRead, int $createdBefore): int;

    /**
     * @return list<string>
     */
    public function findPoluchateleyIds(string $notificationId): array;
}
