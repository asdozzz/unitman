<?php

declare(strict_types=1);

namespace App\Runner\Infra\Workflow;

use App\App\Infra\Workflow\WorkflowClientFactory;
use App\Runner\Business\Command\IzmenitRezhimRedaktoraUnita;
use App\Runner\Business\Model\GolangRunner\Unit\ResultatIzmeneniyaRezhimaRedaktora;
use Temporal\Activity\ActivityOptions;
use Temporal\Workflow;
use Temporal\Workflow\ReturnType;
use Temporal\Workflow\WorkflowInterface;
use Temporal\Workflow\WorkflowMethod;

/**
 * Пересоздание IDE-сайдкара подготовленного юнита с новым режимом редактора.
 *
 * Вызывается переключателем редактирования, когда IDE уже существует
 * (plan §8, решение 15.13-а). Если юнит не подготовлен, переключение
 * применится при следующей подготовке — workflow тогда не запускается.
 */
#[WorkflowInterface]
final class IzmenitRezhimRedaktoraUnitaWorkflow
{
    public const ACTIVITY = 'IzmenitRezhimRedaktoraUnitaActivity';
    public const TASK_QUEUE = WorkflowClientFactory::runnerQueueName;

    /**
     * @psalm-suppress MissingReturnType
     */
    #[WorkflowMethod(name: 'IzmenitRezhimRedaktoraUnita')]
    #[ReturnType(ResultatIzmeneniyaRezhimaRedaktora::class)]
    public function execute(IzmenitRezhimRedaktoraUnita $command)
    {
        return yield Workflow::executeActivity(
            self::ACTIVITY,
            [$command],
            ActivityOptions::new()
                ->withStartToCloseTimeout(60 * 10)
                ->withTaskQueue(self::TASK_QUEUE)
        );
    }
}
