<?php

declare(strict_types=1);

namespace App\Runner\Tests\Api;

use App\Runner\Api\RunnerApi;
use App\Runner\Api\RunnerApiInterface;
use App\Runner\Business\Command\Workspace\OstanovitWorkspace;
use App\Runner\Business\Command\Workspace\ProveritIzmeneniyaWorkspace;
use App\Runner\Business\Command\Workspace\ResultatOstanovkiWorkspace;
use App\Runner\Business\Command\Workspace\ResultatProverkiIzmeneniyWorkspace;
use App\Runner\Business\Command\Workspace\ResultatUdaleniyaWorkspace;
use App\Runner\Business\Command\Workspace\ResultatZapuskaWorkspace;
use App\Runner\Business\Command\Workspace\UdalitWorkspace;
use App\Runner\Business\Command\Workspace\ZapustitWorkspaceZapros;
use PHPUnit\Framework\TestCase;

final class RunnerApiWorkspaceSignatureTest extends TestCase
{
    public function test_runner_api_realizuet_workspace_kontrakt(): void
    {
        $reflection = new \ReflectionClass(RunnerApi::class);

        self::assertTrue($reflection->implementsInterface(RunnerApiInterface::class));

        $expected = [
            ['zapustitWorkspace', ZapustitWorkspaceZapros::class, 'string'],
            ['poluchitResultatZapuskaWorkspace', null, '?' . ResultatZapuskaWorkspace::class],
            ['ostanovitWorkspace', OstanovitWorkspace::class, 'string'],
            ['poluchitResultatOstanovkiWorkspace', null, '?' . ResultatOstanovkiWorkspace::class],
            ['udalitWorkspace', UdalitWorkspace::class, 'string'],
            ['poluchitResultatUdaleniyaWorkspace', null, '?' . ResultatUdaleniyaWorkspace::class],
            ['proveritIzmeneniyaWorkspace', ProveritIzmeneniyaWorkspace::class, ResultatProverkiIzmeneniyWorkspace::class],
        ];

        foreach ($expected as [$method, $commandClass, $returnType]) {
            self::assertTrue($reflection->hasMethod($method), $method);

            $methodReflection = $reflection->getMethod($method);
            self::assertSame($returnType, (string) $methodReflection->getReturnType(), $method);

            if ($commandClass === null) {
                continue;
            }

            $parameterTypes = array_map(
                static fn(\ReflectionParameter $parameter): string => (string) $parameter->getType(),
                $methodReflection->getParameters(),
            );

            self::assertContains($commandClass, $parameterTypes, $method);
        }
    }
}
