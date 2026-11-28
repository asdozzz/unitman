<?php

declare(strict_types=1);

namespace App\OpenCode\Business\Workspace\Port;

use App\OpenCode\Business\ReadModel\UvedomlenieONovomSoobsheniiOpenCode;

/**
 * Уведомления workspace на самостоятельном агрегате
 * (`opencode_workspace_notification`).
 *
 * Уникальность `(turn_id, account_id)` — тот же ключ идемпотентности, что и в
 * легаси: один ход рассылается нескольким получателям, повторное завершение не
 * дублирует строку конкретному получателю.
 */
interface WorkspaceNotificationsRepository
{
    /**
     * @return bool true только если строка была вставлена
     */
    public function save(UvedomlenieONovomSoobsheniiOpenCode $notification): bool;
}
