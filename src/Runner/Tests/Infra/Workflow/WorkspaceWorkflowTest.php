<?php

declare(strict_types=1);

namespace App\Runner\Tests\Infra\Workflow;

use App\Runner\Infra\Workflow\OstanovitWorkspaceWorkflow;
use App\Runner\Infra\Workflow\ProveritIzmeneniyaWorkspaceWorkflow;
use App\Runner\Infra\Workflow\UdalitWorkspaceWorkflow;
use App\Runner\Infra\Workflow\ZapustitWorkspaceWorkflow;
use PHPUnit\Framework\TestCase;
use Temporal\Workflow\ReturnType;
use Temporal\Workflow\WorkflowInterface;
use Temporal\Workflow\WorkflowMethod;

final class WorkspaceWorkflowTest extends TestCase
{
    public function test_imena_aktivnostey_i_ochered_strogo_sootvetstvuyut_kontraktu(): void
    {
        self::assertSame('ZapustitWorkspaceActivity', ZapustitWorkspaceWorkflow::ACTIVITY);
        self::assertSame('OstanovitWorkspaceActivity', OstanovitWorkspaceWorkflow::ACTIVITY);
        self::assertSame('UdalitWorkspaceActivity', UdalitWorkspaceWorkflow::ACTIVITY);
        self::assertSame('ProveritIzmeneniyaWorkspaceActivity', ProveritIzmeneniyaWorkspaceWorkflow::ACTIVITY);

        // Legacy-очередь как константа удалена: очередь приходит только
        // параметром `taskQueue` от выбранного раннера.
        foreach (
            [
                ZapustitWorkspaceWorkflow::class,
                OstanovitWorkspaceWorkflow::class,
                UdalitWorkspaceWorkflow::class,
                ProveritIzmeneniyaWorkspaceWorkflow::class,
            ] as $workflow
        ) {
            self::assertFalse(defined($workflow . '::TASK_QUEUE'));
        }
    }

    public function test_workflow_zaregistrirovany(): void
    {
        $registered = require \dirname(__DIR__, 5) . '/config/workflows.php';

        self::assertContains(ZapustitWorkspaceWorkflow::class, $registered);
        self::assertContains(OstanovitWorkspaceWorkflow::class, $registered);
        self::assertContains(UdalitWorkspaceWorkflow::class, $registered);
        self::assertContains(ProveritIzmeneniyaWorkspaceWorkflow::class, $registered);
    }

    public function test_workflow_annotirovany_kak_workflow_s_tipom_rezultata(): void
    {
        $methods = [
            [ZapustitWorkspaceWorkflow::class, 'execute'],
            [OstanovitWorkspaceWorkflow::class, 'execute'],
            [UdalitWorkspaceWorkflow::class, 'execute'],
            [ProveritIzmeneniyaWorkspaceWorkflow::class, 'proverit'],
        ];

        foreach ($methods as [$class, $method]) {
            $reflection = new \ReflectionClass($class);
            self::assertNotEmpty($reflection->getAttributes(WorkflowInterface::class));

            $methodReflection = $reflection->getMethod($method);
            self::assertNotEmpty($methodReflection->getAttributes(WorkflowMethod::class));
            self::assertNotEmpty($methodReflection->getAttributes(ReturnType::class));
        }
    }
}
