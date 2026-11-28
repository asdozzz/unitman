<?php

declare(strict_types=1);

namespace App\OpenCode\Business\Workspace\Port;

use App\OpenCode\Business\Model\HodOpenCode;

interface WorkspaceTurnRepository
{
    /**
     * Время жизни резервации хода, секунды.
     *
     * Общая таблица `opencode_session_running_turn` без FK: аренда общая с
     * unit-сессиями, TTL тот же, что и в легаси.
     */
    public const TTL_ZANYATOSTI_SEKUND = 900;

    public function save(HodOpenCode $turn): void;

    public function update(HodOpenCode $turn): void;

    /**
     * @throws \DomainException если ход не найден
     */
    public function getById(string $id): HodOpenCode;

    public function findById(string $id): ?HodOpenCode;

    public function findByWorkflowId(string $workflowId): ?HodOpenCode;

    /**
     * @return list<HodOpenCode>
     */
    public function findBySessionId(string $workspaceId): array;

    /**
     * @return list<HodOpenCode>
     */
    public function findVProzesse(int $limit): array;

    public function findPosledniiPoSessii(string $workspaceId): ?HodOpenCode;

    public function sleduyushiiSeq(string $workspaceId): int;

    /**
     * Атомарно занимает workspace ходом `$turnId`.
     *
     * @param int $ustarevanieSekund возраст аренды, после которого её можно перехватить
     *
     * @throws \DomainException `opencode.session.busy` если workspace занят свежим ходом
     */
    public function zanyatSessiyu(string $workspaceId, string $turnId, int $now, int $ustarevanieSekund): void;

    /**
     * Снимает аренду только если она принадлежит этому ходу.
     */
    public function osvoboditSessiyu(string $workspaceId, string $turnId): void;

    /**
     * Занят ли workspace свежей (не просроченной) арендой.
     */
    public function zanyata(string $workspaceId, int $now, int $ustarevanieSekund): bool;
}
