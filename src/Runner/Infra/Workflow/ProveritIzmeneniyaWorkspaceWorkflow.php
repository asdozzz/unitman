<?php

declare(strict_types=1);

namespace App\Runner\Infra\Workflow;

use App\Runner\Business\Command\Workspace\ProveritIzmeneniyaWorkspace;
use App\Runner\Business\Command\Workspace\ResultatProverkiIzmeneniyWorkspace;
use Temporal\Activity\ActivityOptions;
use Temporal\Workflow;
use Temporal\Workflow\ReturnType;
use Temporal\Workflow\WorkflowInterface;
use Temporal\Workflow\WorkflowMethod;

/**
 * Синхронная проверка изменений: по образцу `ProveritKonteinerUnitaWorkflow`
 * клиент получает результат прямо из вызова workflow.
 */
#[WorkflowInterface]
final class ProveritIzmeneniyaWorkspaceWorkflow
{
    public const ACTIVITY = 'ProveritIzmeneniyaWorkspaceActivity';

    /**
     * @psalm-suppress MissingReturnType
     */
    #[WorkflowMethod(name: 'ProveritIzmeneniyaWorkspace')]
    #[ReturnType(ResultatProverkiIzmeneniyWorkspace::class)]
    public function proverit(ProveritIzmeneniyaWorkspace $command, string $taskQueue = '')
    {
        if ($taskQueue === '') {
            throw new \InvalidArgumentException('taskQueue обязателен для запуска workflow');
        }

        return yield Workflow::executeActivity(
            self::ACTIVITY,
            [$command],
            ActivityOptions::new()
                ->withStartToCloseTimeout(30)
                ->withTaskQueue($taskQueue)
        );
    }
}
