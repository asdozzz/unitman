<?php

declare(strict_types=1);

namespace App\OpenCode\Business\Utils;

use App\OpenCode\Business\Model\SostoyanieUnita;

/**
 * Решение 15.7-а: ход по юниту не read-only только тогда, когда владелец
 * сессии — автор юнита или прикреплённый редактор, редактирование включено,
 * и ветка юнита не защищена. Во всех остальных случаях ход read-only.
 *
 * Правило намеренно не знает о токенах: наличие личного токена проверяется
 * в момент выдачи токена, и его отсутствие не понижает ход до read-only, а
 * отклоняет RW-ход с понятной ошибкой.
 */
final class OpredelitelRezhimaHodaOpenCode
{
    public function readOnly(SostoyanieUnita $sostoyanie, string $accountId): bool
    {
        return !$this->mozhetRedaktirovat($sostoyanie, $accountId);
    }

    public function mozhetRedaktirovat(SostoyanieUnita $sostoyanie, string $accountId): bool
    {
        $owner = trim($accountId);

        if (!$sostoyanie->exists || $owner === '') {
            return false;
        }

        if (!$this->yavlyaetsyaAvtoromIliRedaktorom($sostoyanie, $owner)) {
            return false;
        }

        return $sostoyanie->redaktirovanieVklucheno && !$sostoyanie->vetkaZashishena;
    }

    private function yavlyaetsyaAvtoromIliRedaktorom(SostoyanieUnita $sostoyanie, string $owner): bool
    {
        if ($sostoyanie->authorId !== null && $sostoyanie->authorId === $owner) {
            return true;
        }

        return in_array($owner, $sostoyanie->redaktory, true);
    }
}
