<?php

declare(strict_types=1);

namespace App\Notification\Business\Port;

use App\Notification\Business\Model\TipUvedomleniya;

interface NastroikiUvedomleniyRepository
{
    /**
     * @return array<string, bool> type => enabled
     */
    public function findDlyaAccounta(string $accountId): array;

    /**
     * Возвращает получателей, которые отключили указанный тип.
     *
     * @param list<string> $accountIds
     *
     * @return list<string>
     */
    public function findOtkluchennih(TipUvedomleniya $type, array $accountIds): array;

    public function save(string $accountId, TipUvedomleniya $type, bool $enabled, int $now): void;
}
