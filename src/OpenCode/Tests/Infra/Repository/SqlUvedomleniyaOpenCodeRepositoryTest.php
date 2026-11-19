<?php

declare(strict_types=1);

namespace App\OpenCode\Tests\Infra\Repository;

use App\OpenCode\Business\ReadModel\UvedomlenieONovomSoobsheniiOpenCode;
use App\OpenCode\Infra\Repository\SqlUvedomleniyaOpenCodeRepository;
use Doctrine\DBAL\ArrayParameterType;
use Doctrine\DBAL\Connection;
use Doctrine\DBAL\ParameterType;
use Doctrine\DBAL\Result;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

final class SqlUvedomleniyaOpenCodeRepositoryTest extends TestCase
{
    private const NOW = 1_700_000_000;

    #[Test]
    public function dubl_po_turn_id_ne_sozdaet_vtoruyu_zapis(): void
    {
        $vyzovy = [];

        $connection = $this->createMock(Connection::class);
        $connection->expects($this->exactly(2))
            ->method('executeStatement')
            ->willReturnCallback(
                static function (string $sql, array $params, array $types) use (&$vyzovy): int {
                    $vyzovy[] = ['sql' => $sql, 'params' => $params, 'types' => $types];

                    // Первая вставка прошла, вторая — конфликт по turn_id и 0 строк.
                    return count($vyzovy) === 1 ? 1 : 0;
                }
            );

        $repository = new SqlUvedomleniyaOpenCodeRepository($connection);
        $notification = $this->uvedomlenie('t-1');

        $this->assertTrue($repository->save($notification));
        $this->assertFalse($repository->save($notification));

        $this->assertStringContainsString('insert into opencode_notification', $vyzovy[0]['sql']);
        $this->assertStringContainsString('on conflict (turn_id) do nothing', $vyzovy[0]['sql']);
        $this->assertSame($vyzovy[0]['params'], $vyzovy[1]['params']);
        $this->assertSame('t-1', $vyzovy[1]['params']['turn_id']);
        $this->assertSame('acc-1', $vyzovy[1]['params']['account_id']);
    }

    #[Test]
    public function zahvat_zahvatit_tolko_neprochitannie_svoego_akkaunta(): void
    {
        $result = $this->createStub(Result::class);
        $result->method('fetchFirstColumn')->willReturn(['n-1', 'n-3']);

        $zahvacheno = null;

        $connection = $this->createMock(Connection::class);
        $connection->expects($this->once())
            ->method('executeQuery')
            ->willReturnCallback(
                static function (string $sql, array $params, array $types) use (&$zahvacheno, $result): Result {
                    $zahvacheno = ['sql' => $sql, 'params' => $params, 'types' => $types];

                    return $result;
                }
            );

        $repository = new SqlUvedomleniyaOpenCodeRepository($connection);

        $ids = $repository->zahvatitNeprochitannie('acc-1', ['n-1', 'n-2', 'n-1', 'n-3'], self::NOW);

        $this->assertSame(['n-1', 'n-3'], $ids);
        $this->assertNotNull($zahvacheno);
        $this->assertStringContainsString('update opencode_notification set read_at = :now', $zahvacheno['sql']);
        $this->assertStringContainsString('account_id = :account_id', $zahvacheno['sql']);
        $this->assertStringContainsString('read_at is null', $zahvacheno['sql']);
        $this->assertStringContainsString('returning id', $zahvacheno['sql']);
        $this->assertSame(self::NOW, $zahvacheno['params']['now']);
        $this->assertSame('acc-1', $zahvacheno['params']['account_id']);
        $this->assertSame(['n-1', 'n-2', 'n-3'], $zahvacheno['params']['ids']);
        $this->assertSame(ArrayParameterType::STRING, $zahvacheno['types']['ids']);
    }

    #[Test]
    public function pustoi_spisok_id_ne_obraschaetsya_k_bd(): void
    {
        $connection = $this->createMock(Connection::class);
        $connection->expects($this->never())->method('executeQuery');

        $repository = new SqlUvedomleniyaOpenCodeRepository($connection);

        $this->assertSame([], $repository->zahvatitNeprochitannie('acc-1', [], self::NOW));
    }

    #[Test]
    public function find_neprochitannie_filtruet_po_akkauntu_i_read_at(): void
    {
        $zapros = null;

        $connection = $this->createMock(Connection::class);
        $connection->expects($this->once())
            ->method('fetchAllAssociative')
            ->willReturnCallback(
                static function (string $sql, array $params, array $types) use (&$zapros): array {
                    $zapros = ['sql' => $sql, 'params' => $params, 'types' => $types];

                    return [[
                        'id' => 'n-2',
                        'account_id' => 'acc-1',
                        'session_id' => 's-1',
                        'turn_id' => 't-2',
                        'session_title' => 'Сессия',
                        'status' => 'AWAITING_INPUT',
                        'kind' => 'QUESTION',
                        'created_at' => self::NOW,
                        'read_at' => null,
                    ]];
                }
            );

        $repository = new SqlUvedomleniyaOpenCodeRepository($connection);

        $notifications = $repository->findNeprochitannie('acc-1', 25);

        $this->assertCount(1, $notifications);
        $this->assertSame('n-2', $notifications[0]->id);
        $this->assertSame('acc-1', $notifications[0]->accountId);
        $this->assertSame('t-2', $notifications[0]->turnId);
        $this->assertSame('QUESTION', $notifications[0]->kind);
        $this->assertSame(self::NOW, $notifications[0]->createdAt);

        $this->assertNotNull($zapros);
        $this->assertStringContainsString('account_id = :account_id and read_at is null', $zapros['sql']);
        $this->assertStringContainsString('order by created_at', $zapros['sql']);
        $this->assertStringContainsString('limit :limit', $zapros['sql']);
        $this->assertSame('acc-1', $zapros['params']['account_id']);
        $this->assertSame(25, $zapros['params']['limit']);
        $this->assertSame(ParameterType::STRING, $zapros['types']['account_id']);
        $this->assertSame(ParameterType::INTEGER, $zapros['types']['limit']);
    }

    private function uvedomlenie(string $turnId): UvedomlenieONovomSoobsheniiOpenCode
    {
        return new UvedomlenieONovomSoobsheniiOpenCode(
            'n-1',
            'acc-1',
            's-1',
            $turnId,
            'Сессия',
            'ANSWERED',
            'ANSWER',
            self::NOW,
        );
    }
}
