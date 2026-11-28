<?php

declare(strict_types=1);

namespace App\OpenCode\Business\Port;

use App\OpenCode\Business\Model\SessiyaOpenCode;
use App\OpenCode\Business\Model\WorkspacePendingUpdate;
use App\OpenCode\Business\ReadModel\RezultatProverkiIzmeneniyWorkspace;
use App\OpenCode\Business\Workspace\Model\WorkspaceSession;

/**
 * Жизненный цикл workspace на стороне Runner.
 *
 * Реализация — ACL-адаптер над публичным `RunnerApi`: OpenCode не знает про
 * Temporal, workflow-id и Go-раннер. Каждая операция записывается в workspace
 * (тип операции и workflow-id), чтобы фоновая задача могла опросить результат.
 */
interface UmeetUpravlyatWorkspace
{
    /** Результат операции: успешно завершена. */
    public const RESULT_SUCCESS = 'success';

    /** Результат операции: завершена с ошибкой. */
    public const RESULT_FAILURE = 'failure';

    public function zapustit(SessiyaOpenCode|WorkspaceSession $session): void;

    public function ostanovit(SessiyaOpenCode|WorkspaceSession $session): void;

    public function vosstanovit(SessiyaOpenCode|WorkspaceSession $session): void;

    /**
     * Запускает обновление workspace: новые проекты и/или файлы.
     *
     * @return string id workflow обновления
     */
    public function obnovit(SessiyaOpenCode|WorkspaceSession $session, WorkspacePendingUpdate $update): string;

    public function udalit(SessiyaOpenCode|WorkspaceSession $session): void;

    public function poluchitIzmeneniya(SessiyaOpenCode|WorkspaceSession $session): RezultatProverkiIzmeneniyWorkspace;

    /**
     * Результат текущей операции: `success`, `failure` или null, пока workflow
     * ещё выполняется.
     */
    public function poluchitRezultatOperacii(SessiyaOpenCode|WorkspaceSession $session): ?string;
}
