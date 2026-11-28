<?php

declare(strict_types=1);

namespace App\OpenCode\Tests\Infra\Repository;

use App\OpenCode\Business\Model\RolUchastnikaSessii;
use App\OpenCode\Business\Model\UchastnikSessii;
use App\OpenCode\Infra\Repository\SqlUchastnikiSessiiRepository;
use Doctrine\DBAL\ArrayParameterType;
use Doctrine\DBAL\Connection;
use Doctrine\DBAL\ParameterType;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

final class SqlUchastnikiSessiiRepositoryTest extends TestCase
{
    private const NOW = 1_700_000_000;

    #[Test]
    public function save_delit_upsert_i_peredayot_tipy(): void
    {
        $statements = [];

        $connection = $this->createMock(Connection::class);
        $connection->expects($this->once())
            ->method('executeStatement')
            ->willReturnCallback(
                static function (string $sql, array $params, array $types) use (&$statements): int {
                    $statements[] = ['sql' => $sql, 'params' => $params, 'types' => $types];

                    return 1;
                }
            );

        (new SqlUchastnikiSessiiRepository($connection))->save($this->uchastnik());

        $this->assertCount(1, $statements);
        $this->assertStringContainsString(
            'on conflict (session_id, account_id) do update set',
            $statements[0]['sql'],
        );
        $this->assertSame('s-1', $statements[0]['params']['session_id']);
        $this->assertSame('acc-2', $statements[0]['params']['account_id']);
        $this->assertSame('writer', $statements[0]['params']['role']);
        $this->assertSame('cred-2', $statements[0]['params']['default_credential_id']);
        $this->assertSame(ParameterType::INTEGER, $statements[0]['types']['created_at']);
        $this->assertSame(ParameterType::STRING, $statements[0]['types']['role']);
    }

    #[Test]
    public function find_vozvrashchaet_uchastnika(): void
    {
        $connection = $this->createMock(Connection::class);
        $connection->expects($this->once())
            ->method('fetchAssociative')
            ->with(
                $this->stringContains('where session_id = :session_id and account_id = :account_id'),
                ['session_id' => 's-1', 'account_id' => 'acc-2'],
                ['session_id' => ParameterType::STRING, 'account_id' => ParameterType::STRING],
            )
            ->willReturn($this->row());

        $uchastnik = (new SqlUchastnikiSessiiRepository($connection))->find('s-1', 'acc-2');

        $this->assertNotNull($uchastnik);
        $this->assertSame('acc-2', $uchastnik->getAccountId());
        $this->assertSame(RolUchastnikaSessii::WRITER, $uchastnik->getRol());
    }

    #[Test]
    public function find_vozvrashchaet_null_kogda_stroki_net(): void
    {
        $connection = $this->createMock(Connection::class);
        $connection->method('fetchAssociative')->willReturn(false);

        $this->assertNull(
            (new SqlUchastnikiSessiiRepository($connection))->find('s-1', 'acc-2'),
        );
    }

    #[Test]
    public function findBySessionIds_gruppiruet_po_sessii(): void
    {
        $connection = $this->createMock(Connection::class);
        $connection->expects($this->once())
            ->method('fetchAllAssociative')
            ->with(
                $this->stringContains('where session_id in (:ids)'),
                ['ids' => ['s-1', 's-2']],
                ['ids' => ArrayParameterType::STRING],
            )
            ->willReturn([
                $this->row(['session_id' => 's-2', 'account_id' => 'acc-3', 'role' => 'viewer']),
                $this->row(),
                $this->row(['account_id' => 'acc-3', 'role' => 'owner']),
            ]);

        $rezultat = (new SqlUchastnikiSessiiRepository($connection))
            ->findBySessionIds(['s-1', ' s-2 ', 's-1', '']);

        $this->assertSame(['s-2', 's-1'], array_keys($rezultat));
        $this->assertCount(1, $rezultat['s-2']);
        $this->assertCount(2, $rezultat['s-1']);
        $this->assertSame(RolUchastnikaSessii::OWNER, $rezultat['s-1'][1]->getRol());
    }

    #[Test]
    public function findBySessionIds_na_pustom_spiske_ne_hodit_v_db(): void
    {
        $connection = $this->createMock(Connection::class);
        $connection->expects($this->never())->method('fetchAllAssociative');

        $this->assertSame(
            [],
            (new SqlUchastnikiSessiiRepository($connection))->findBySessionIds(['  ', '']),
        );
    }

    #[Test]
    public function findBySessionId_chitaet_s_porjadkom(): void
    {
        $connection = $this->createMock(Connection::class);
        $connection->expects($this->once())
            ->method('fetchAllAssociative')
            ->with(
                $this->stringContains('where session_id = :session_id order by created_at, account_id'),
                ['session_id' => 's-1'],
                ['session_id' => ParameterType::STRING],
            )
            ->willReturn([$this->row(), $this->row(['account_id' => 'acc-3', 'role' => 'viewer'])]);

        $uchastniki = (new SqlUchastnikiSessiiRepository($connection))->findBySessionId('s-1');

        $this->assertCount(2, $uchastniki);
        $this->assertSame('acc-2', $uchastniki[0]->getAccountId());
        $this->assertSame(RolUchastnikaSessii::VIEWER, $uchastniki[1]->getRol());
    }

    #[Test]
    public function udalit_vladelca_otklonyaetsya_bez_zaprosa_v_db(): void
    {
        $connection = $this->createMock(Connection::class);
        $connection->expects($this->never())->method('delete');

        $vladelec = $this->uchastnik(RolUchastnikaSessii::OWNER, 'acc-1');

        $this->expectException(\DomainException::class);
        $this->expectExceptionMessage('opencode.participant.owner_cannot_be_removed');

        (new SqlUchastnikiSessiiRepository($connection))->udalit($vladelec);
    }

    #[Test]
    public function udalit_uchastnika_udalyayet_po_sostavnomu_klyuchu(): void
    {
        $deletes = [];

        $connection = $this->createMock(Connection::class);
        $connection->expects($this->once())
            ->method('delete')
            ->willReturnCallback(
                static function (string $table, array $criteria, array $types = []) use (&$deletes): int {
                    $deletes[] = ['table' => $table, 'criteria' => $criteria, 'types' => $types];

                    return 1;
                }
            );

        (new SqlUchastnikiSessiiRepository($connection))->udalit($this->uchastnik());

        $this->assertSame('opencode_session_participant', $deletes[0]['table']);
        $this->assertSame(['session_id' => 's-1', 'account_id' => 'acc-2'], $deletes[0]['criteria']);
    }

    /**
     * @param array<string, mixed> $overrides
     *
     * @return array<string, mixed>
     */
    private function row(array $overrides = []): array
    {
        return array_merge([
            'session_id' => 's-1',
            'account_id' => 'acc-2',
            'role' => 'writer',
            'default_credential_id' => 'cred-2',
            'default_model' => 'gpt-4o',
            'invited_by' => 'acc-1',
            'created_at' => self::NOW,
            'updated_at' => self::NOW,
        ], $overrides);
    }

    private function uchastnik(
        RolUchastnikaSessii $rol = RolUchastnikaSessii::WRITER,
        string $accountId = 'acc-2',
    ): UchastnikSessii {
        return UchastnikSessii::create(
            's-1',
            $accountId,
            $rol,
            'cred-2',
            'gpt-4o',
            'acc-1',
            self::NOW,
        );
    }
}
