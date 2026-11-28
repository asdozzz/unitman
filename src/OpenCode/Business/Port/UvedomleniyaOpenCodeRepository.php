<?php

declare(strict_types=1);

namespace App\OpenCode\Business\Port;

use App\OpenCode\Business\ReadModel\UvedomlenieONovomSoobsheniiOpenCode;

interface UvedomleniyaOpenCodeRepository
{
    /**
     * Идемпотентно сохраняет уведомление: уникальна пара
     * `(turn_id, account_id)` — один ход рассылается нескольким получателям,
     * но повторное завершение не дублирует строку конкретному получателю.
     *
     * @return bool true только если строка была вставлена
     */
    public function save(UvedomlenieONovomSoobsheniiOpenCode $notification): bool;

    /**
     * @return list<UvedomlenieONovomSoobsheniiOpenCode>
     */
    public function findNeprochitannie(string $accountId, int $limit): array;

    /**
     * Атомарно переводит непрочитанные уведомления аккаунта в прочитанные.
     *
     * @param list<string> $ids
     *
     * @return list<string> идентификаторы, реально переведённые в прочитанные
     */
    public function zahvatitNeprochitannie(string $accountId, array $ids, int $now): array;
}
