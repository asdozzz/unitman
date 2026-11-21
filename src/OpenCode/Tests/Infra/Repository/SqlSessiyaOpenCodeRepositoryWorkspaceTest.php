<?php

declare(strict_types=1);

namespace App\OpenCode\Tests\Infra\Repository;

use App\OpenCode\Business\Model\ProektWorkspace;
use App\OpenCode\Business\Model\SessiyaOpenCode;
use App\OpenCode\Business\Model\SessiyaOpenCodeFilter;
use App\OpenCode\Business\Model\StatusWorkspace;
use App\OpenCode\Business\Model\TipSessiiOpenCode;
use App\OpenCode\Infra\Repository\SqlSessiyaOpenCodeRepository;
use Doctrine\DBAL\Connection;
use Doctrine\DBAL\ParameterType;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

final class SqlSessiyaOpenCodeRepositoryWorkspaceTest extends TestCase
{
    private const NOW = 1_700_000_000;

    #[Test]
    public function save_workspace_pishet_vse_tablicy_v_odnoj_transakcii(): void
    {
        $transakcii = 0;
        $inserts = [];
        $deletes = [];
        $statements = [];

        $connection = $this->createMock(Connection::class);
        $connection->expects($this->once())
            ->method('transactional')
            ->willReturnCallback(static function (callable $callback) use (&$transakcii): mixed {
                ++$transakcii;

                return $callback();
            });
        $connection->method('insert')
            ->willReturnCallback(
                static function (string $table, array $data, array $types = []) use (&$inserts): int {
                    $inserts[] = ['table' => $table, 'data' => $data, 'types' => $types];

                    return 1;
                }
            );
        $connection->method('delete')
            ->willReturnCallback(
                static function (string $table, array $criteria) use (&$deletes): int {
                    $deletes[] = ['table' => $table, 'criteria' => $criteria];

                    return 0;
                }
            );
        $connection->method('executeStatement')
            ->willReturnCallback(
                static function (string $sql, array $params = [], array $types = []) use (&$statements): int {
                    $statements[] = ['sql' => $sql, 'params' => $params, 'types' => $types];

                    return 1;
                }
            );

        $repository = new SqlSessiyaOpenCodeRepository($connection);
        $repository->save($this->workspace());

        $this->assertSame(1, $transakcii);

        $sessionInsert = $this->findInsert($inserts, 'opencode_session');
        $this->assertNotNull($sessionInsert);
        $this->assertSame('workspace', $sessionInsert['data']['tip']);
        $this->assertNull($sessionInsert['data']['unit_id']);
        $this->assertNull($sessionInsert['data']['unit_project_id']);

        $workspaceStatement = $this->findStatement($statements, 'insert into opencode_workspace');
        $this->assertNotNull($workspaceStatement);
        $this->assertStringContainsString('on conflict (session_id)', $workspaceStatement['sql']);
        $this->assertSame('CREATING', $workspaceStatement['params']['status']);
        $this->assertTrue($workspaceStatement['params']['read_only']);

        $deletedTables = array_map(static fn (array $delete): string => $delete['table'], $deletes);
        $this->assertContains('opencode_session_unit', $deletedTables);
        $this->assertContains('opencode_workspace_project', $deletedTables);

        $projectInserts = array_values(array_filter(
            $inserts,
            static fn (array $insert): bool => $insert['table'] === 'opencode_workspace_project'
        ));
        $this->assertCount(2, $projectInserts);
        $this->assertSame('main', $projectInserts[0]['data']['main_branch']);
        $this->assertSame(ParameterType::STRING, $projectInserts[0]['types']['main_branch']);
        $this->assertSame('develop', $projectInserts[1]['data']['main_branch']);
    }

    #[Test]
    public function findByAccountId_filtruet_po_tipu_i_batch_chitaet_workspace(): void
    {
        $fetched = [];

        $connection = $this->createMock(Connection::class);
        $connection->expects($this->exactly(3))
            ->method('fetchAllAssociative')
            ->willReturnCallback(
                static function (string $sql, array $params, array $types) use (&$fetched): array {
                    $fetched[] = ['sql' => $sql, 'params' => $params, 'types' => $types];

                    if (str_contains($sql, 'opencode_workspace_project')) {
                        return [
                            [
                                'session_id' => 's-1',
                                'project_id' => 'p-1',
                                'project_name' => 'api',
                                'project_code' => 'services/api',
                                'main_branch' => 'main',
                                'can_push' => true,
                                'position_index' => 0,
                            ],
                            [
                                'session_id' => 's-1',
                                'project_id' => 'p-2',
                                'project_name' => 'web',
                                'project_code' => 'services/web',
                                'main_branch' => 'develop',
                                'can_push' => false,
                                'position_index' => 1,
                            ],
                        ];
                    }

                    if (str_contains($sql, 'FROM opencode_workspace WHERE')) {
                        return [[
                            'session_id' => 's-1',
                            'branch' => 'feature/payments',
                            'status' => 'RUNNING',
                            'read_only' => true,
                            'initial_prompt' => 'Сделай рефакторинг',
                            'initial_prompt_started_at' => null,
                            'error_key' => null,
                            'operation' => 'create',
                            'operation_workflow_id' => 'wf-1',
                            'operation_attempts' => 0,
                            'next_attempt_at' => 0,
                            'created_at' => self::NOW,
                            'updated_at' => self::NOW,
                        ]];
                    }

                    return [[
                        'id' => 's-1',
                        'account_id' => 'acc-1',
                        'credential_id' => 'cred-1',
                        'title' => 'payments-refactor',
                        'tip' => 'workspace',
                        'external_session_id' => null,
                        'unit_id' => null,
                        'unit_project_id' => null,
                        'archived_at' => null,
                        'archive_reason' => null,
                        'created_at' => self::NOW,
                        'updated_at' => self::NOW,
                    ]];
                }
            );

        $repository = new SqlSessiyaOpenCodeRepository($connection);
        $sessions = $repository->findByAccountId(
            'acc-1',
            new SessiyaOpenCodeFilter(false, null, TipSessiiOpenCode::WORKSPACE)
        );

        $this->assertCount(1, $sessions);
        $this->assertTrue($sessions[0]->estWorkspace());
        $this->assertSame('payments-refactor', $sessions[0]->getTitle());

        $workspace = $sessions[0]->getWorkspace();
        $this->assertNotNull($workspace);
        $this->assertSame(StatusWorkspace::RUNNING, $workspace->getStatus());
        $this->assertTrue($workspace->isReadOnly());
        $this->assertCount(2, $workspace->getProjects());
        $this->assertSame('p-1', $workspace->getProjects()[0]->projectId);
        $this->assertSame('main', $workspace->getProjects()[0]->mainBranch);
        $this->assertFalse($workspace->getProjects()[1]->canPush);
        $this->assertSame('develop', $workspace->getProjects()[1]->mainBranch);

        $this->assertStringContainsString('tip = :tip', $fetched[0]['sql']);
        $this->assertSame('workspace', $fetched[0]['params']['tip']);
        $this->assertSame(ParameterType::STRING, $fetched[0]['types']['tip']);
    }

    #[Test]
    public function perevesti_status_atomarno_obnovlyaet_stroku(): void
    {
        $zapros = null;

        $connection = $this->createMock(Connection::class);
        $connection->expects($this->once())
            ->method('executeStatement')
            ->willReturnCallback(
                static function (string $sql, array $params, array $types) use (&$zapros): int {
                    $zapros = ['sql' => $sql, 'params' => $params, 'types' => $types];

                    return 1;
                }
            );

        $repository = new SqlSessiyaOpenCodeRepository($connection);

        $this->assertTrue($repository->perevestiStatusWorkspace(
            's-1',
            [StatusWorkspace::ARCHIVED, StatusWorkspace::ERROR],
            StatusWorkspace::STARTING,
        ));

        $this->assertNotNull($zapros);
        $this->assertStringContainsString('update opencode_workspace set status = :to', $zapros['sql']);
        $this->assertStringContainsString('and status = any(:from)', $zapros['sql']);
        $this->assertSame('STARTING', $zapros['params']['to']);
        $this->assertSame('{ARCHIVED,ERROR}', $zapros['params']['from']);
    }

    #[Test]
    public function perevesti_status_vernet_false_esli_stroka_ne_obnovlena(): void
    {
        $connection = $this->createMock(Connection::class);
        $connection->expects($this->once())
            ->method('executeStatement')
            ->willReturn(0);

        $repository = new SqlSessiyaOpenCodeRepository($connection);

        $this->assertFalse($repository->perevestiStatusWorkspace(
            's-1',
            [StatusWorkspace::RUNNING],
            StatusWorkspace::ARCHIVING,
        ));
    }

    #[Test]
    public function perevesti_status_bez_ozhidaemyh_statusov_ne_obrashchaetsya_k_bd(): void
    {
        $connection = $this->createMock(Connection::class);
        $connection->expects($this->never())->method('executeStatement');

        $repository = new SqlSessiyaOpenCodeRepository($connection);

        $this->assertFalse($repository->perevestiStatusWorkspace('s-1', [], StatusWorkspace::RUNNING));
    }

    #[Test]
    public function delete_workspace_kaskadno_udelyaet_svyazannye_stroki(): void
    {
        $transakcii = 0;
        $statements = [];

        $connection = $this->createMock(Connection::class);
        $connection->expects($this->once())
            ->method('transactional')
            ->willReturnCallback(static function (callable $callback) use (&$transakcii): mixed {
                ++$transakcii;

                return $callback();
            });
        $connection->expects($this->exactly(5))
            ->method('executeStatement')
            ->willReturnCallback(
                static function (string $sql, array $params, array $types) use (&$statements): int {
                    $statements[] = ['sql' => $sql, 'params' => $params];

                    return 1;
                }
            );

        $repository = new SqlSessiyaOpenCodeRepository($connection);
        $repository->deleteWorkspace('s-1');

        $this->assertSame(1, $transakcii);
        $this->assertCount(5, $statements);

        $expectedTables = [
            'opencode_workspace_project',
            'opencode_workspace',
            'opencode_turn',
            'opencode_notification',
        ];

        foreach ($expectedTables as $index => $table) {
            $this->assertStringContainsString("delete from $table where session_id = :id", $statements[$index]['sql']);
            $this->assertSame('s-1', $statements[$index]['params']['id']);
        }

        $this->assertStringContainsString('delete from opencode_session where id = :id', $statements[4]['sql']);
        $this->assertSame('s-1', $statements[4]['params']['id']);
    }

    #[Test]
    public function find_aktivnie_workspace_beret_workspace_i_arhivnie_v_tranzitnom_statuse(): void
    {
        $zapros = null;

        $connection = $this->createMock(Connection::class);
        $connection->expects($this->once())
            ->method('fetchAllAssociative')
            ->willReturnCallback(
                static function (string $sql, array $params, array $types) use (&$zapros): array {
                    $zapros = ['sql' => $sql, 'params' => $params, 'types' => $types];

                    return [];
                }
            );

        $repository = new SqlSessiyaOpenCodeRepository($connection);

        $this->assertSame([], $repository->findAktivnieWorkspace(50));

        $this->assertNotNull($zapros);
        $this->assertStringContainsString('INNER JOIN opencode_workspace w', $zapros['sql']);
        $this->assertStringContainsString('s.tip = :tip', $zapros['sql']);
        // Архивные сессии не отбрасываются, если операция ещё транзитная:
        // иначе удаление архива и восстановление из архива залипают навсегда.
        $this->assertStringContainsString(
            '(s.archived_at is null OR w.status = any(:tranzit))',
            $zapros['sql'],
        );
        $this->assertSame('workspace', $zapros['params']['tip']);
        $this->assertSame('{CREATING,STARTING,ARCHIVING,DELETING}', $zapros['params']['tranzit']);
        $this->assertSame(ParameterType::STRING, $zapros['types']['tranzit']);
        $this->assertSame(50, $zapros['params']['limit']);
    }

    private function workspace(): SessiyaOpenCode
    {
        return SessiyaOpenCode::createWorkspace(
            's-1',
            'acc-1',
            'cred-1',
            'payments-refactor',
            'feature/payments',
            [
                new ProektWorkspace('p-1', 'api', 'services/api', true, 0, 'main'),
                new ProektWorkspace('p-2', 'web', 'services/web', false, 1, 'develop'),
            ],
            'Сделай рефакторинг',
            self::NOW,
        );
    }

    /**
     * @param list<array{table: string, data: array<string, mixed>, types: array<string, mixed>}> $inserts
     *
     * @return array{table: string, data: array<string, mixed>, types: array<string, mixed>}|null
     */
    private function findInsert(array $inserts, string $table): ?array
    {
        foreach ($inserts as $insert) {
            if ($insert['table'] === $table) {
                return $insert;
            }
        }

        return null;
    }

    /**
     * @param list<array{sql: string, params: array<string, mixed>}> $statements
     *
     * @return array{sql: string, params: array<string, mixed>}|null
     */
    private function findStatement(array $statements, string $needle): ?array
    {
        foreach ($statements as $statement) {
            if (str_contains($statement['sql'], $needle)) {
                return $statement;
            }
        }

        return null;
    }
}
