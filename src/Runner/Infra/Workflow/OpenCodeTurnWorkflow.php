<?php

declare(strict_types=1);

namespace App\Runner\Infra\Workflow;

use App\App\Infra\Workflow\WorkflowClientFactory;
use App\Runner\Business\Command\VipolnitHodOpenCode;
use App\Runner\Business\Model\GolangRunner\OpenCode\ResultatHodaOpenCode;
use Temporal\Activity\ActivityOptions;
use Temporal\Workflow;
use Temporal\Workflow\ReturnType;
use Temporal\Workflow\WorkflowInterface;
use Temporal\Workflow\WorkflowMethod;

/**
 * Один workflow = один ход диалога с OpenCode.
 *
 * Workflow запускает единственную активити на `runnerQueue` и завершается:
 * - `Kind = ANSWER`   — OpenCode дал финальный ответ на этот ход;
 * - `Kind = QUESTION` — OpenCode задал вопрос; следующий ход стартует новый
 *   workflow с `Answers`, заполненными по этому вопросу;
 * - `Kind = ERROR`    — ошибка, текст которой передаётся ключом переводов.
 *
 * Никаких сигналов и долгоживущих workflow: бэкенд стартует workflow и опрашивает
 * `getResult(...)` короткими порциями, как это уже сделано для остальных задач Runner.
 */
#[WorkflowInterface]
final class OpenCodeTurnWorkflow
{
    /**
     * @psalm-suppress MissingReturnType
     */
    #[WorkflowMethod]
    #[ReturnType(ResultatHodaOpenCode::class)]
    public function execute(VipolnitHodOpenCode $command)
    {
        return yield Workflow::executeActivity(
            'VipolnitHodOpenCodeActivity',
            [$command],
            ActivityOptions::new()
                // Ход может включать длительные операции OpenCode (правки файлов,
                // запуск команд), поэтому таймаут щедрый — как у действий над юнитом.
                ->withStartToCloseTimeout(60 * 60)
                ->withTaskQueue(WorkflowClientFactory::runnerQueueName)
        );
    }
}
