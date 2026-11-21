<?php

declare(strict_types=1);

namespace App\Runner\Tests\Api;

use App\Runner\Api\RunnerApi;
use App\Runner\Business\Command\NachatHodOpenCode;
use App\Runner\Business\Command\VipolnitHodOpenCode;
use App\Runner\Business\Command\Workspace\OstanovitWorkspace;
use App\Runner\Business\Command\Workspace\ProektWorkspaceZapros;
use App\Runner\Business\Command\Workspace\ProveritIzmeneniyaWorkspace;
use App\Runner\Business\Command\Workspace\ResultatOstanovkiWorkspace;
use App\Runner\Business\Command\Workspace\ResultatProverkiIzmeneniyWorkspace;
use App\Runner\Business\Command\Workspace\ResultatUdaleniyaWorkspace;
use App\Runner\Business\Command\Workspace\ResultatZapuskaWorkspace;
use App\Runner\Business\Command\Workspace\UdalitWorkspace;
use App\Runner\Business\Command\Workspace\ZapustitWorkspace;
use App\Runner\Business\Command\Workspace\ZapustitWorkspaceZapros;
use App\Runner\Business\Model\OpenCode\RekvizityOpenCode;
use App\Runner\Business\Port\UmeetHranitProgressZadachi;
use App\Runner\Business\Port\UmeetPoluchatRekvizityOpenCode;
use App\Runner\Business\Port\UmeetPoluchatUrlKlonaProekta;
use App\Runner\Infra\Workflow\OstanovitWorkspaceWorkflow;
use App\Runner\Infra\Workflow\OpenCodeTurnWorkflow;
use App\Runner\Infra\Workflow\ProveritIzmeneniyaWorkspaceWorkflow;
use App\Runner\Infra\Workflow\UdalitWorkspaceWorkflow;
use App\Runner\Infra\Workflow\ZapustitWorkspaceWorkflow;
use PHPUnit\Framework\TestCase;
use Temporal\Client\WorkflowClient;
use Temporal\Client\WorkflowOptions;
use Temporal\Client\WorkflowStubInterface;

/**
 * WorkflowClient Temporal-а является final-классом в некоторых версиях SDK,
 * поэтому проверки API выполняются только если его можно подменить моком.
 * Сами method signatures и DTO проверяются отдельно и от mockability не зависят.
 */
final class RunnerApiWorkspaceTest extends TestCase
{
    protected function setUp(): void
    {
        $reflection = new \ReflectionClass(WorkflowClient::class);

        if ($reflection->isFinal()) {
            self::markTestSkipped('Temporal WorkflowClient is final and cannot be mocked');
        }

        foreach (['start', 'newWorkflowStub', 'newUntypedRunningWorkflowStub'] as $method) {
            if ($reflection->getMethod($method)->isFinal()) {
                self::markTestSkipped(sprintf('Temporal WorkflowClient::%s() is final and cannot be mocked', $method));
            }
        }
    }

    public function test_zapustit_workspace_registriruet_token_i_staruet_workflow_na_mono_ocheredi(): void
    {
        $stub = $this->createMock(WorkflowStubInterface::class);
        $client = $this->createMock(WorkflowClient::class);
        $captured = null;

        $client->expects(self::once())
            ->method('newWorkflowStub')
            ->with(
                self::equalTo(ZapustitWorkspaceWorkflow::class),
                self::isInstanceOf(WorkflowOptions::class),
            )
            ->willReturnCallback(static function (string $workflow, WorkflowOptions $options) use (&$captured, $stub) {
                $captured = $options;
                return $stub;
            });

        $started = null;
        $client->expects(self::once())
            ->method('start')
            ->willReturnCallback(static function (object $workflow, mixed $command) use (&$started) {
                $started = $command;
                throw new \RuntimeException('stop');
            });

        $storage = $this->createMock(UmeetHranitProgressZadachi::class);
        $storage->expects(self::once())->method('zaregistrirovat')->with('workspace-wf');

        $urlKlona = $this->createMock(UmeetPoluchatUrlKlonaProekta::class);
        $urlKlona->expects(self::once())
            ->method('poluchitUrlKlona')
            ->with('project-1')
            ->willReturn('https://oauth2:token@gitlab.example/services/api.git');

        $api = $this->api($client, $storage, null, $urlKlona);

        $request = $this->zapustitCommand();

        try {
            $api->zapustitWorkspace('workspace-wf', $request);
        } catch (\RuntimeException) {
            // start() подменён исключением: дальше кода запуска нет.
        }

        self::assertInstanceOf(WorkflowOptions::class, $captured);
        self::assertSame('unitman-mono-queue', $this->optionValue($captured, 'taskQueue'));
        self::assertSame('workspace-wf', $this->optionValue($captured, 'workflowId'));

        // В workflow уходит обогащённая команда с токеном...
        self::assertInstanceOf(ZapustitWorkspace::class, $started);
        self::assertSame(
            'https://oauth2:token@gitlab.example/services/api.git',
            $started->Projects[0]->StorageUrl,
        );

        // ...и базовой веткой проекта из публичного запроса.
        self::assertSame('main', $started->Projects[0]->MainBranch);

        // ...а публичный запрос токена не содержит.
        self::assertFalse($this->hasProperty($request, 'StorageUrl'));
        self::assertFalse($this->hasProperty($request->Projects[0], 'StorageUrl'));
    }

    public function test_ostanovit_i_udalit_staruyut_sootvetstvuyushchie_workflow(): void
    {
        foreach (
            [
                ['ostanovitWorkspace', OstanovitWorkspaceWorkflow::class, new OstanovitWorkspace('workspace-1')],
                ['udalitWorkspace', UdalitWorkspaceWorkflow::class, new UdalitWorkspace('workspace-1')],
            ] as [$method, $workflowClass, $command]
        ) {
            $stub = $this->createMock(WorkflowStubInterface::class);
            $client = $this->createMock(WorkflowClient::class);
            $captured = null;

            $client->expects(self::once())
                ->method('newWorkflowStub')
                ->with(self::equalTo($workflowClass), self::isInstanceOf(WorkflowOptions::class))
                ->willReturnCallback(static function (string $class, WorkflowOptions $options) use (&$captured, $stub) {
                    $captured = $options;
                    return $stub;
                });
            $client->expects(self::once())
                ->method('start')
                ->willThrowException(new \RuntimeException('stop'));

            $storage = $this->createMock(UmeetHranitProgressZadachi::class);
            $storage->expects(self::once())->method('zaregistrirovat')->with('wf-1');

            try {
                $this->api($client, $storage)->{$method}('wf-1', $command);
            } catch (\RuntimeException) {
            }

            self::assertInstanceOf(WorkflowOptions::class, $captured);
            self::assertSame('unitman-mono-queue', $this->optionValue($captured, 'taskQueue'));
        }
    }

    public function test_proverit_izmeneniya_vozvrashchaet_rezultat_sinhronnogo_workflow(): void
    {
        $expected = new ResultatProverkiIzmeneniyWorkspace(true, [
            ['ProjectId' => 'project-1', 'Name' => 'api', 'Dirty' => false, 'Files' => []],
        ]);

        $stub = $this->getMockBuilder(WorkflowStubInterface::class)
            ->addMethods(['proverit'])
            ->getMockForAbstractClass();
        $stub->expects(self::once())
            ->method('proverit')
            ->willReturn($expected);

        $client = $this->createMock(WorkflowClient::class);
        $captured = null;
        $client->expects(self::once())
            ->method('newWorkflowStub')
            ->with(
                self::equalTo(ProveritIzmeneniyaWorkspaceWorkflow::class),
                self::isInstanceOf(WorkflowOptions::class),
            )
            ->willReturnCallback(static function (string $class, WorkflowOptions $options) use (&$captured, $stub) {
                $captured = $options;
                return $stub;
            });

        $result = $this->api($client)->proveritIzmeneniyaWorkspace(
            new ProveritIzmeneniyaWorkspace('workspace-1', [['ProjectId' => 'project-1', 'ProjectName' => 'api']]),
        );

        self::assertSame($expected, $result);
        self::assertInstanceOf(WorkflowOptions::class, $captured);
        self::assertSame('unitman-mono-queue', $this->optionValue($captured, 'taskQueue'));
    }

    public function test_resultaty_zapuska_ostanovki_i_udaleniya_berutsya_iz_workflow(): void
    {
        $cases = [
            ['poluchitResultatZapuskaWorkspace', new ResultatZapuskaWorkspace(1)],
            ['poluchitResultatOstanovkiWorkspace', new ResultatOstanovkiWorkspace(1)],
            ['poluchitResultatUdaleniyaWorkspace', new ResultatUdaleniyaWorkspace(1)],
        ];

        foreach ($cases as [$method, $expected]) {
            $stub = $this->createMock(WorkflowStubInterface::class);
            $stub->method('hasExecution')->willReturn(true);
            $stub->method('getResult')->willReturn($expected);

            $client = $this->createMock(WorkflowClient::class);
            $client->expects(self::once())
                ->method('newUntypedRunningWorkflowStub')
                ->with('wf-1')
                ->willReturn($stub);

            self::assertSame($expected, $this->api($client)->{$method}('wf-1'));
        }
    }

    public function test_nachat_hod_prokidyvaet_workspace_polya_v_aktivnost(): void
    {
        $stub = $this->createMock(WorkflowStubInterface::class);
        $client = $this->createMock(WorkflowClient::class);
        $captured = null;

        $client->expects(self::once())
            ->method('newWorkflowStub')
            ->with(
                self::equalTo(OpenCodeTurnWorkflow::class),
                self::isInstanceOf(WorkflowOptions::class),
            )
            ->willReturn($stub);
        $client->expects(self::once())
            ->method('start')
            ->willReturnCallback(static function (object $workflow, VipolnitHodOpenCode $command) use (&$captured) {
                $captured = $command;
                throw new \RuntimeException('stop');
            });

        $rekvizity = $this->createMock(UmeetPoluchatRekvizityOpenCode::class);
        $rekvizity->method('poluchitRekvizity')
            ->willReturn(new RekvizityOpenCode('credential-1', 'https://opencode', 'unitman/main', 'secret'));

        $storage = $this->createMock(UmeetHranitProgressZadachi::class);
        $storage->expects(self::once())->method('zaregistrirovat')->with('turn-wf');

        $projects = [
            ['ProjectId' => 'project-1', 'ProjectName' => 'api', 'ProjectCode' => 'services/api', 'CanPush' => true],
        ];

        try {
            $this->api($client, $storage, $rekvizity)->nachatHodOpenCode(
                'turn-wf',
                new NachatHodOpenCode(
                    'account-1',
                    'credential-1',
                    'workspace-1',
                    'prompt',
                    [],
                    [],
                    null,
                    null,
                    [],
                    'workspace-1',
                    false,
                    $projects,
                ),
            );
        } catch (\RuntimeException) {
        }

        self::assertInstanceOf(VipolnitHodOpenCode::class, $captured);
        self::assertSame('workspace-1', $captured->WorkspaceId);
        self::assertFalse($captured->WorkspaceReadOnly);
        self::assertSame($projects, $captured->WorkspaceProjects);
    }

    private function api(
        WorkflowClient $client,
        ?UmeetHranitProgressZadachi $storage = null,
        ?UmeetPoluchatRekvizityOpenCode $rekvizity = null,
        ?UmeetPoluchatUrlKlonaProekta $urlKlona = null,
    ): RunnerApi {
        $api = (new \ReflectionClass(RunnerApi::class))->newInstanceWithoutConstructor();

        $this->setProperty($api, 'workflowClient', $client);
        if ($storage !== null) {
            $this->setProperty($api, 'progressStorage', $storage);
        }
        if ($rekvizity !== null) {
            $this->setProperty($api, 'rekvizityOpenCode', $rekvizity);
        }
        if ($urlKlona !== null) {
            $this->setProperty($api, 'urlKlonaProekta', $urlKlona);
        }

        return $api;
    }

    private function hasProperty(object $object, string $name): bool
    {
        foreach ((new \ReflectionClass($object))->getProperties() as $property) {
            if (strcasecmp($property->getName(), $name) === 0) {
                return true;
            }
        }

        return false;
    }

    /**
     * В используемой версии Temporal SDK у `WorkflowOptions` нет геттеров,
     * но есть `__debugInfo()` с публичными ключами опций.
     */
    private function optionValue(WorkflowOptions $options, string $key): ?string
    {
        $value = $options->__debugInfo()[$key] ?? null;

        return is_string($value) ? $value : null;
    }

    private function setProperty(object $object, string $name, mixed $value): void
    {
        $property = new \ReflectionProperty($object, $name);
        $property->setAccessible(true);
        $property->setValue($object, $value);
    }

    private function zapustitCommand(): ZapustitWorkspaceZapros
    {
        return new ZapustitWorkspaceZapros(
            'workspace-1',
            'payments-refactor',
            'payments-refactor-1a2b3c4d',
            'feature/payments',
            false,
            3072,
            [
                new ProektWorkspaceZapros(
                    'project-1',
                    'api',
                    'services/api',
                    true,
                    'main',
                ),
            ],
        );
    }
}
