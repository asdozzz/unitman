<?php

declare(strict_types=1);

namespace App\OpenCode\Business\Utils;

use App\OpenCode\Business\Model\RolUchastnikaSessii;
use App\OpenCode\Business\Model\SessiyaOpenCode;
use App\OpenCode\Business\Model\UchastnikSessii;
use App\OpenCode\Business\Port\UchastnikiSessiiRepository;

/**
 * Единая политика доступа к сессии OpenCode.
 *
 * Владелец сессии — неявный участник с ролью `owner`. Это важно по двум
 * причинам: unit-сессии не имеют таблицы участников, а workspace, созданные
 * до появления участников, не должны потерять доступ владельца.
 *
 * Не-участник получает `opencode.session.not_found` (не раскрываем
 * существование сессии), участник без нужной роли — `opencode.session.forbidden`.
 */
final class ProverkaDostupaKSessii
{
    public function __construct(private readonly UchastnikiSessiiRepository $uchastniki)
    {
    }

    public function rol(SessiyaOpenCode $session, string $accountId): ?RolUchastnikaSessii
    {
        $uchastnik = $this->uchastniki->find($session->getId(), $accountId);

        if ($uchastnik !== null) {
            return $uchastnik->getRol();
        }

        return $session->prinadlezhit($accountId) ? RolUchastnikaSessii::OWNER : null;
    }

    /**
     * Проверяет доступ и возвращает явную строку участника, если она есть.
     *
     * `null` — доступ разрешён владельцу как неявному owner: unit-сессии не
     * имеют участников, а старые workspace ещё не прошли backfill.
     *
     * @throws \DomainException `opencode.session.not_found` | `opencode.session.forbidden`
     */
    public function trebovat(
        SessiyaOpenCode $session,
        string $accountId,
        RolUchastnikaSessii $minimum,
    ): ?UchastnikSessii {
        $uchastnik = $this->uchastniki->find($session->getId(), $accountId);

        if ($uchastnik === null) {
            if ($session->prinadlezhit($accountId)) {
                return null;
            }

            throw new \DomainException('opencode.session.not_found');
        }

        if (!$uchastnik->getRol()->dostatochnoDlya($minimum)) {
            throw new \DomainException($minimum === RolUchastnikaSessii::VIEWER
                ? 'opencode.session.not_found'
                : 'opencode.session.forbidden');
        }

        return $uchastnik;
    }
}
