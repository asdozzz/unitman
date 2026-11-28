<?php

declare(strict_types=1);

namespace App\OpenCode\Business\Workspace\Utils;

use App\OpenCode\Business\Model\RolUchastnikaSessii;
use App\OpenCode\Business\Model\UchastnikSessii;
use App\OpenCode\Business\Workspace\Model\WorkspaceSession;
use App\OpenCode\Business\Workspace\Port\WorkspaceParticipantsRepository;

/**
 * Политика доступа к workspace на самостоятельном агрегате.
 *
 * Владелец (`WorkspaceSession::$accountId`) — неявный `owner`: строка участника
 * для него может отсутствовать, но доступ не теряется. Роли:
 * - `owner` — чат и управление;
 * - `writer` — промпт/ответ, чтение;
 * - `viewer` — чтение, история, состояние, результат, прогресс, артефакты.
 *
 * Не-участник получает `opencode.session.not_found` (не раскрываем
 * существование workspace), участник без нужной роли — `opencode.session.forbidden`.
 */
final class ProverkaDostupaWorkspaceSessii
{
    public function __construct(private readonly WorkspaceParticipantsRepository $uchastniki)
    {
    }

    public function rol(WorkspaceSession $workspace, string $accountId): ?RolUchastnikaSessii
    {
        $uchastnik = $this->uchastniki->find($workspace->getId(), $accountId);

        if ($uchastnik !== null) {
            return $uchastnik->getRol();
        }

        return $workspace->prinadlezhit($accountId) ? RolUchastnikaSessii::OWNER : null;
    }

    /**
     * Проверяет доступ и возвращает явную строку участника, если она есть.
     *
     * `null` — доступ разрешён владельцу как неявному owner.
     *
     * @throws \DomainException `opencode.session.not_found` | `opencode.session.forbidden`
     */
    public function trebovat(
        WorkspaceSession $workspace,
        string $accountId,
        RolUchastnikaSessii $minimum,
    ): ?UchastnikSessii {
        $uchastnik = $this->uchastniki->find($workspace->getId(), $accountId);

        if ($uchastnik === null) {
            if ($workspace->prinadlezhit($accountId)) {
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

    /**
     * Чтение доступно всем ролям.
     */
    public function proverit(WorkspaceSession $workspace, string $accountId): void
    {
        $this->trebovat($workspace, $accountId, RolUchastnikaSessii::VIEWER);
    }
}
