<?php

declare(strict_types=1);

namespace App\OpenCode\Business\Workspace\Port;

/**
 * Одностороннее зеркало легаси-агрегата в новый агрегат.
 *
 * Источник истины — легаси. Зеркало вызывается **после** успешной записи
 * легаси-операции и только до тех пор, пока флаг
 * `OPENCODE_WORKSPACE_MIRROR_WRITE` включён. Любая ошибка зеркала
 * проглатывается вызывающим кодом: она не должна ломать пользовательскую
 * операцию, расхождение ловит гейт сверки.
 *
 * Двустороннего dual-write нет: новые таблицы никогда не пишут в легаси.
 */
interface WorkspaceMirror
{
    public function isEnabled(): bool;

    /**
     * Переносит сессию, её проекты и pending-обновление из легаси.
     */
    public function zerkalitSessiyu(string $sessionId): void;

    /**
     * Переносит один ход легаси, если он принадлежит workspace-сессии.
     */
    public function zerkalitHod(string $turnId): void;

    public function zerkalitUchastnika(string $sessionId, string $accountId): void;

    public function udalitUchastnika(string $sessionId, string $accountId): void;

    public function zerkalitUvedomlenie(string $notificationId): void;

    /**
     * Синхронизирует `read_at` у уже перенесённых уведомлений.
     *
     * @param list<string> $notificationIds
     */
    public function zerkalitProchitannost(array $notificationIds): void;

    public function zerkalitPendingUpdate(string $sessionId): void;

    public function udalitPendingUpdate(string $sessionId): void;

    /**
     * Удаляет дочерние строки и сессию нового агрегата.
     */
    public function udalitWorkspace(string $sessionId): void;
}
