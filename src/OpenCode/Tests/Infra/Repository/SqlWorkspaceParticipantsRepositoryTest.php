<?php

declare(strict_types=1);

namespace App\OpenCode\Tests\Infra\Repository;

use App\OpenCode\Business\Model\RolUchastnikaSessii;
use App\OpenCode\Business\Model\UchastnikSessii;
use App\OpenCode\Infra\Repository\Workspace\SqlWorkspaceParticipantsRepository;
use Doctrine\DBAL\Connection;
use Doctrine\DBAL\ParameterType;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

final class SqlWorkspaceParticipantsRepositoryTest extends TestCase
{
    private const NOW = 1_700_000_000;

    #[Test]
    public function find_privyazyvaet_uchastnika_k_workspace(): void
    {
        $connection = $this->createMock(Connection::class);
        $connection->expects($this->once())
            ->method('fetchAssociative')
            ->willReturnCallback(static function (string $sql, array $params) use (&$zapros): array {
                $zapros = ['sql' => $sql, 'params' => $params];

                return [
                    'workspace_id' => 'workspace-1',
                    'account_id' => 'acc-2',
                    'role' => 'writer',
                    'default_credential_id' => 'cred-2',
                    'default_model' => null,
                    'invited_by' => 'owner-1',
                    'created_at' => self::NOW,
                    'updated_at' => self::NOW,
                ];
            });

        $uchastnik = (new SqlWorkspaceParticipantsRepository($connection))->find('workspace-1', 'acc-2');

        self::assertNotNull($zapros);
        self::assertStringContainsString('opencode_workspace_participant', $zapros['sql']);
        self::assertSame('workspace-1', $zapros['params']['workspace_id']);
        self::assertInstanceOf(UchastnikSessii::class, $uchastnik);
        self::assertSame('workspace-1', $uchastnik->getSessionId());
        self::assertSame(RolUchastnikaSessii::WRITER, $uchastnik->getRol());
    }

    #[Test]
    public function save_pishet_workspace_id_i_idempotenten_po_klyuchu(): void
    {
        $zapros = null;
        $connection = $this->createMock(Connection::class);
        $connection->expects($this->once())
            ->method('executeStatement')
            ->willReturnCallback(static function (string $sql, array $params, array $types) use (&$zapros): int {
                $zapros = ['sql' => $sql, 'params' => $params, 'types' => $types];

                return 1;
            });

        $uchastnik = UchastnikSessii::create(
            'workspace-1',
            'acc-2',
            RolUchastnikaSessii::VIEWER,
            'cred-2',
            'model-2',
            'owner-1',
            self::NOW,
        );

        (new SqlWorkspaceParticipantsRepository($connection))->save($uchastnik);

        self::assertNotNull($zapros);
        self::assertStringContainsString('on conflict (workspace_id, account_id)', $zapros['sql']);
        self::assertSame('workspace-1', $zapros['params']['workspace_id']);
        self::assertSame(ParameterType::STRING, $zapros['types']['workspace_id']);
    }
}
