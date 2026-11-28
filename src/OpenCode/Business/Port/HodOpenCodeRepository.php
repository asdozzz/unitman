<?php

declare(strict_types=1);

namespace App\OpenCode\Business\Port;

use App\OpenCode\Business\Model\HodOpenCode;

interface HodOpenCodeRepository
{
    /**
     * Время жизни резервации хода, секунды.
     *
     * Резервация защищает от двух одновременных ходов на одной сессии, но не
     * должна блокировать её навсегда, если процесс упал между стартом хода и
     * фиксацией результата. По истечении TTL следующий старт перехватывает
     * аренду как просроченную.
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
    public function findBySessionId(string $sessionId): array;

    /**
     * Ходы, которые ещё выполняются: вход watchdog'а завершения.
     *
     * @return list<HodOpenCode>
     */
    public function findVProzesse(int $limit): array;

    /**
     * Последний ход сессии — именно он определяет, ждёт ли сессия ответа.
     */
    public function findPosledniiPoSessii(string $sessionId): ?HodOpenCode;

    public function sleduyushiiSeq(string $sessionId): int;

    /**
     * Атомарно занимает сессию ходом `$turnId`.
     *
     * Вставка и захват просроченной аренды выполняются одним upsert-запросом:
     * проверка «свободна ли сессия» и запись не разделяются, поэтому два
     * параллельных промпта не могут оба увидеть сессию свободной.
     *
     * @param int $ustarevanieSekund возраст аренды, после которого её можно перехватить
     *
     * @throws \DomainException `opencode.session.busy` если сессия занята свежим ходом
     */
    public function zanyatSessiyu(string $sessionId, string $turnId, int $now, int $ustarevanieSekund): void;

    /**
     * Снимает аренду только если она принадлежит этому ходу: чужой захват не
     * освобождается, иначе завершение старого хода открыло бы сессию для
     * параллельного запуска.
     */
    public function osvoboditSessiyu(string $sessionId, string $turnId): void;

    /**
     * Занята ли сессия свежей (не просроченной) арендой.
     *
     * Нужна read-сценариям: просроченная аренда не должна показывать сессию
     * занятой — она будет перехвачена при следующем старте.
     */
    public function zanyata(string $sessionId, int $now, int $ustarevanieSekund): bool;
}