<?php

declare(strict_types=1);

namespace App\OpenCode\Tests\Infra\Repository;

use App\OpenCode\Business\ReadModel\UvedomlenieONovomSoobsheniiOpenCode;
use App\OpenCode\Infra\Repository\SqlUvedomleniyaOpenCodeRepository;
use Doctrine\DBAL\Connection;
use Doctrine\DBAL\ParameterType;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

/**
 * Уведомления хранят тип сессии; старые строки без поля читаются как unit.
 */
final class SqlUvedomleniyaOpenCodeSessionTypeTest extends TestCase
{
    private const NOW = 1_700_000_000;

    #[Test]
    public function save_pishet_session_type(): void
    {
        $params = null;

        $connection = $this->createMock(Connection::class);
        $connection->expects($this->once())
            ->method('executeStatement')
            ->willReturnCallback(
                static function (string $sql, array $bound, array $types) use (&$params): int {
                    $params = ['sql' => $sql, 'bound' => $bound, 'types' => $types];

                    return 1;
                }
            );

        $repository = new SqlUvedomleniyaOpenCodeRepository($connection);

        $this->assertTrue($repository->save($this->uvedomlenie('workspace')));

        self::assertNotNull($params);
        self::assertStringContainsString('session_type', $params['sql']);
        self::assertSame('workspace', $params['bound']['session_type']);
        self::assertSame(ParameterType::STRING, $params['types']['session_type']);
    }

    #[Test]
    public function starye_stroki_bez_session_type_chitayutsya_kak_unit(): void
    {
        $connection = $this->createMock(Connection::class);
        $connection->method('fetchAllAssociative')->willReturn([[
            'id' => 'n-1',
            'account_id' => 'acc-1',
            'session_id' => 's-1',
            'turn_id' => 't-1',
            'session_title' => 'Сессия',
            'status' => 'ANSWERED',
            'kind' => 'ANSWER',
            'created_at' => self::NOW,
            'read_at' => null,
        ]]);

        $notifications = (new SqlUvedomleniyaOpenCodeRepository($connection))
            ->findNeprochitannie('acc-1', 10);

        self::assertSame('unit', $notifications[0]->sessionType);
    }

    #[Test]
    public function novaya_stroka_otdayot_svoi_session_type(): void
    {
        $connection = $this->createMock(Connection::class);
        $connection->method('fetchAllAssociative')->willReturn([[
            'id' => 'n-2',
            'account_id' => 'acc-1',
            'session_id' => 'w-1',
            'turn_id' => 't-2',
            'session_title' => 'payments-refactor',
            'status' => 'ANSWERED',
            'kind' => 'ANSWER',
            'created_at' => self::NOW,
            'read_at' => null,
            'session_type' => 'workspace',
        ]]);

        $notifications = (new SqlUvedomleniyaOpenCodeRepository($connection))
            ->findNeprochitannie('acc-1', 10);

        self::assertSame('workspace', $notifications[0]->sessionType);
    }

    private function uvedomlenie(string $sessionType): UvedomlenieONovomSoobsheniiOpenCode
    {
        return new UvedomlenieONovomSoobsheniiOpenCode(
            'n-1',
            'acc-1',
            'w-1',
            't-1',
            'payments-refactor',
            'ANSWERED',
            'ANSWER',
            self::NOW,
            $sessionType,
        );
    }
}
