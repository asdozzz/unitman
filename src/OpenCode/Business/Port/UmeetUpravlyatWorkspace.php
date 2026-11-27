<?php

declare(strict_types=1);

namespace App\OpenCode\Business\Port;

use App\OpenCode\Business\Model\SessiyaOpenCode;
use App\OpenCode\Business\Model\WorkspacePendingUpdate;
use App\OpenCode\Business\ReadModel\RezultatProverkiIzmeneniyWorkspace;

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

    public function zapustit(SessiyaOpenCode $session): void;

    public function ostanovit(SessiyaOpenCode $session): void;

    public function vosstanovit(SessiyaOpenCode $session): void;

    /**
     * Запускает обновление workspace: новые проекты и/или файлы.
     *
     * @return string id workflow обновления
     */
    public function obnovit(SessiyaOpenCode $session, WorkspacePendingUpdate $update): string;

    public function udalit(SessiyaOpenCode $session): void;

    public function poluchitIzmeneniya(SessiyaOpenCode $session): RezultatProverkiIzmeneniyWorkspace;

    /**
     * Результат текущей операции: `success`, `failure` или null, пока workflow
     * ещё выполняется.
     */
    public function poluchitRezultatOperacii(SessiyaOpenCode $session): ?string;
}
