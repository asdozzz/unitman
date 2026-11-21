<?php

declare(strict_types=1);

namespace App\Runner\Tests\Infra\Workflow;

use App\Runner\Infra\Workflow\IzmenitRezhimRedaktoraUnitaWorkflow;
use App\Runner\Infra\Workflow\PodgotovitSessiyuRedaktoraWorkflow;
use PHPUnit\Framework\TestCase;
use Temporal\Workflow\ReturnType;
use Temporal\Workflow\WorkflowInterface;
use Temporal\Workflow\WorkflowMethod;

/**
 * Workflow редактора регистрируются на очереди Go-раннера и следуют
 * принятому формату: `#[WorkflowInterface]` + `#[WorkflowMethod]` + `#[ReturnType]`.
 */
final class RedaktorWorkflowTest extends TestCase
{
    public function test_imena_aktivnostey_i_ochered_sootvetstvuyut_kontraktu(): void
    {
        self::assertSame('IzmenitRezhimRedaktoraUnitaActivity', IzmenitRezhimRedaktoraUnitaWorkflow::ACTIVITY);
        self::assertSame('PodgotovitSessiyuRedaktoraActivity', PodgotovitSessiyuRedaktoraWorkflow::ACTIVITY);

        self::assertSame('unitman-runner-queue', IzmenitRezhimRedaktoraUnitaWorkflow::TASK_QUEUE);
        self::assertSame('unitman-runner-queue', PodgotovitSessiyuRedaktoraWorkflow::TASK_QUEUE);
    }

    public function test_workflow_zaregistrirovany(): void
    {
        $registered = require \dirname(__DIR__, 5) . '/config/workflows.php';

        self::assertContains(IzmenitRezhimRedaktoraUnitaWorkflow::class, $registered);
        self::assertContains(PodgotovitSessiyuRedaktoraWorkflow::class, $registered);
    }

    public function test_workflow_annotirovany_kak_workflow_s_tipom_rezultata(): void
    {
        foreach ([IzmenitRezhimRedaktoraUnitaWorkflow::class, PodgotovitSessiyuRedaktoraWorkflow::class] as $class) {
            $reflection = new \ReflectionClass($class);
            self::assertNotEmpty($reflection->getAttributes(WorkflowInterface::class));

            $methodReflection = $reflection->getMethod('execute');
            self::assertNotEmpty($methodReflection->getAttributes(WorkflowMethod::class));
            self::assertNotEmpty($methodReflection->getAttributes(ReturnType::class));
        }
    }
}
