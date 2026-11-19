<?php

declare(strict_types=1);

namespace App\OpenCode\Tests\Infra\Repository;

use App\OpenCode\Business\Model\StatusHoda;
use App\OpenCode\Infra\Repository\SqlHodOpenCodeRepository;
use Doctrine\DBAL\Connection;
use Doctrine\DBAL\ParameterType;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

/**
 * Выборка ходов в RUNNING для watchdog'а.
 */
final class SqlHodOpenCodeRepositoryFindVProzesseTest extends TestCase
{
    #[Test]
    public function vybiraet_tolko_running_s_ogranicheniem(): void
    {
        $zapros = null;

        $connection = $this->createMock(Connection::class);
        $connection->expects($this->once())
            ->method('fetchAllAssociative')
            ->willReturnCallback(
                static function (string $sql, array $params, array $types) use (&$zapros): array {
                    $zapros = ['sql' => $sql, 'params' => $params, 'types' => $types];

                    return [[
                        'id' => 'turn-1',
                        'session_id' => 'session-1',
                        'seq' => 1,
                        'workflow_id' => 'workflow-1',
                        'status' => StatusHoda::RUNNING->value,
                        'request_text' => 'Привет',
                        'answers_json' => '[]',
                        'result_json' => null,
                        'error_key' => null,
                        'created_at' => 1_700_000_000,
                        'finished_at' => null,
                    ]];
                }
            );

        $repository = new SqlHodOpenCodeRepository($connection);

        $turns = $repository->findVProzesse(10);

        self::assertCount(1, $turns);
        self::assertSame('turn-1', $turns[0]->getId());
        self::assertSame(StatusHoda::RUNNING, $turns[0]->getStatus());

        self::assertNotNull($zapros);
        self::assertStringContainsString("status = 'RUNNING'", $zapros['sql']);
        self::assertStringContainsString('ORDER BY created_at', $zapros['sql']);
        self::assertStringContainsString('LIMIT :limit', $zapros['sql']);
        self::assertSame(10, $zapros['params']['limit']);
        self::assertSame(ParameterType::INTEGER, $zapros['types']['limit']);
    }
}
