<?php

declare(strict_types=1);

namespace App\OpenCode\Infra\Cli;

use App\OpenCode\Infra\Migration\WorkspaceAggregateBackfill;
use Doctrine\DBAL\Connection;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

/**
 * Идемпотентный backfill легаси workspace-агрегата в новый агрегат.
 *
 * Глобальный advisory lock не даёт двум экземплярам идти параллельно.
 * Повторный запуск безопасен: все вставки идут через `on conflict`.
 */
#[AsCommand(
    name: 'app:opencode:workspace-migration:backfill',
    description: 'Идемпотентно переносит workspace из легаси-агрегата в новый'
)]
final class WorkspaceMigrationBackfillCommand extends Command
{
    private const LOCK_KEY = 'opencode.workspace.migration.backfill';

    public function __construct(
        private readonly Connection $connection,
        private readonly WorkspaceAggregateBackfill $backfill,
    ) {
        parent::__construct();
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $style = new SymfonyStyle($input, $output);
        $style->title('OpenCode workspace migration: backfill');

        if (!$this->zahvatitBlokirovku()) {
            $style->error('Backfill уже выполняется другим экземпляром команды.');

            return Command::FAILURE;
        }

        try {
            $report = $this->backfill->run();
        } finally {
            $this->osvoboditBlokirovku();
        }

        $rows = [];

        foreach ($report as $key => $value) {
            $rows[] = [$key, (string) $value];
        }

        $style->table(['Шаг', 'Затронуто строк'], $rows);
        $style->success('Backfill завершён.');

        return Command::SUCCESS;
    }

    private function zahvatitBlokirovku(): bool
    {
        return (bool) $this->connection->fetchOne(
            'select pg_try_advisory_lock(hashtext(:key))',
            ['key' => self::LOCK_KEY]
        );
    }

    private function osvoboditBlokirovku(): void
    {
        $this->connection->executeStatement(
            'select pg_advisory_unlock(hashtext(:key))',
            ['key' => self::LOCK_KEY]
        );
    }
}
