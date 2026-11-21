<?php

declare(strict_types=1);

namespace App\Runner\Infra\Workflow;

use App\App\Infra\Workflow\WorkflowClientFactory;
use App\Runner\Business\Command\PodgotovitSessiyuRedaktora;
use App\Runner\Business\Model\GolangRunner\Unit\ResultatPodgotovkiSessiiRedaktora;
use Temporal\Activity\ActivityOptions;
use Temporal\Workflow;
use Temporal\Workflow\ReturnType;
use Temporal\Workflow\WorkflowInterface;
use Temporal\Workflow\WorkflowMethod;

/**
 * Подготовка редакторской сессии юнита (plan §8).
 *
 * Раннер настраивает RW-доступ и git-креденшелы под конкретного пользователя
 * и его личный токен. К моменту открытия персональной ссылки сессия обычно
 * уже применена — workflow стартует асинхронно.
 */
#[WorkflowInterface]
final class PodgotovitSessiyuRedaktoraWorkflow
{
    public const ACTIVITY = 'PodgotovitSessiyuRedaktoraActivity';
    public const TASK_QUEUE = WorkflowClientFactory::runnerQueueName;

    /**
     * @psalm-suppress MissingReturnType
     */
    #[WorkflowMethod(name: 'PodgotovitSessiyuRedaktora')]
    #[ReturnType(ResultatPodgotovkiSessiiRedaktora::class)]
    public function execute(PodgotovitSessiyuRedaktora $command)
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
