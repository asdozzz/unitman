<?php

declare(strict_types=1);

namespace App\OpenCode\Tests\Infra\Repository;

use App\OpenCode\Business\Model\HodOpenCode;
use App\OpenCode\Infra\Repository\Workspace\SqlWorkspaceTurnRepository;
use Doctrine\DBAL\Connection;
use Doctrine\DBAL\ParameterType;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

final class SqlWorkspaceTurnRepositoryAuthorTest extends TestCase
{
    private const NOW = 1_700_000_000;

    #[Test]
    public function save_zapisyvaet_avtora_i_rekvizity(): void
    {
        $zapros = null;
        $connection = $this->createMock(Connection::class);
        $connection->expects($this->once())
            ->method('insert')
            ->willReturnCallback(static function (string $table, array $data, array $types) use (&$zapros): int {
                $zapros = ['table' => $table, 'data' => $data, 'types' => $types];

                return 1;
            });

        (new SqlWorkspaceTurnRepository($connection))->save($this->turn());

        self::assertNotNull($zapros);
        self::assertSame('author-1', $zapros['data']['author_account_id']);
        self::assertSame('cred-author', $zapros['data']['credential_id']);
        self::assertSame('', $zapros['data']['author_display_name']);
        self::assertSame(ParameterType::STRING, $zapros['types']['author_account_id']);
    }

    #[Test]
    public function findById_vozvrashchaet_avtora(): void
    {
        $connection = $this->createMock(Connection::class);
        $connection->method('fetchAssociative')->willReturn([
            'id' => 'turn-1',
            'workspace_id' => 'workspace-1',
            'seq' => 1,
            'workflow_id' => 'workflow-1',
            'status' => 'RUNNING',
            'request_text' => 'Привет',
            'answers_json' => '[]',
            'result_json' => null,
            'error_key' => null,
            'model' => 'model-1',
            'author_account_id' => 'author-1',
            'credential_id' => 'cred-author',
            'author_display_name' => 'Автор',
            'created_at' => self::NOW,
            'finished_at' => null,
        ]);

        $turn = (new SqlWorkspaceTurnRepository($connection))->getById('turn-1');

        self::assertSame('author-1', $turn->getAuthorAccountId());
        self::assertSame('cred-author', $turn->getCredentialId());
        self::assertSame('Автор', $turn->getAuthorDisplayName());
    }

    private function turn(): HodOpenCode
    {
        return HodOpenCode::create(
            'turn-1',
            'workspace-1',
            1,
            'workflow-1',
            'Привет',
            [],
            self::NOW,
            authorAccountId: 'author-1',
            credentialId: 'cred-author',
        );
    }
}
