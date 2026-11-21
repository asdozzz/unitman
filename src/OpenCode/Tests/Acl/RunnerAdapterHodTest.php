<?php

declare(strict_types=1);

namespace App\OpenCode\Tests\Acl;

use App\OpenCode\Acl\RunnerAdapter;
use App\OpenCode\Business\Model\PrivyazkaKUnitu;
use App\OpenCode\Business\Port\UuidGenerator;
use App\Runner\Api\RunnerApiInterface;
use App\Runner\Business\Command\NachatHodOpenCode;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

/**
 * OpenCode -> Runner: режим хода и токен обязаны доехать до команды без
 * изменений. Токен передаётся только для RW-хода.
 */
final class RunnerAdapterHodTest extends TestCase
{
    #[Test]
    public function rw_hod_peredayot_read_only_false_i_token(): void
    {
        $zapros = $this->zapros(
            readOnly: false,
            token: 'personal-secret',
        );

        self::assertFalse($zapros->ReadOnly);
        self::assertSame('personal-secret', $zapros->Token);
        self::assertNull($zapros->WorkspaceId);
    }

    #[Test]
    public function read_only_hod_ne_peredayot_token(): void
    {
        $zapros = $this->zapros(
            readOnly: true,
            token: null,
        );

        self::assertTrue($zapros->ReadOnly);
        self::assertNull($zapros->Token);
    }

    #[Test]
    public function workspace_rezhim_ostayotsya_nezavisimym_ot_unit_rezhima(): void
    {
        $zapros = $this->zapros(readOnly: true, token: null);

        self::assertFalse($zapros->WorkspaceReadOnly, 'Workspace-режим передаётся отдельным полем');
    }

    private function zapros(bool $readOnly, ?string $token): NachatHodOpenCode
    {
        $zapros = null;

        $runnerApi = $this->createMock(RunnerApiInterface::class);
        $runnerApi->expects($this->once())
            ->method('nachatHodOpenCode')
            ->willReturnCallback(
                static function (string $workflowId, NachatHodOpenCode $command) use (&$zapros): string {
                    $zapros = $command;

                    return $workflowId;
                }
            );

        $adapter = new RunnerAdapter($runnerApi, $this->createMock(UuidGenerator::class));

        $adapter->nachatHod(
            'wf-turn',
            'account-1',
            'credential-1',
            null,
            [PrivyazkaKUnitu::izZnacheniy('unit-1', 'project-1')],
            'Промпт',
            [],
            [],
            null,
            $readOnly,
            $token,
        );

        self::assertInstanceOf(NachatHodOpenCode::class, $zapros);

        return $zapros;
    }
}
