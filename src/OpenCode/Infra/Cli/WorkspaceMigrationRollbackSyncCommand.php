<?php

declare(strict_types=1);

namespace App\OpenCode\Infra\Cli;

use App\OpenCode\Infra\Migration\WorkspaceAggregateRollbackSync;
use Doctrine\DBAL\Connection;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

/**
 * Обратный backfill: новый workspace-агрегат → легаси.
 *
 * Используется при откате `OPENCODE_WORKSPACE_WRITE`. Идемпотентен: повторный
 * запуск не дублирует строки и не перезаписывает более свежие легаси-данные.
 * Легаси ничего не удаляет.
 */
#[AsCommand(
    name: 'app:opencode:workspace-migration:rollback-sync',
    description: 'Идемпотентно переносит workspace из нового агрегата обратно в легаси'
)]
final class WorkspaceMigrationRollbackSyncCommand extends Command
{
    private const LOCK_KEY = 'opencode.workspace.migration.rollback-sync';

    public function __construct(
        private readonly Connection $connection,
        private readonly WorkspaceAggregateRollbackSync $rollbackSync,
    ) {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this->addOption(
            'since',
            null,
            InputOption::VALUE_REQUIRED,
            'Unix-время cutover: переносить только workspace с updated_at > since',
            '0',
        );
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $style = new SymfonyStyle($input, $output);
        $style->title('OpenCode workspace migration: rollback-sync');

        $since = (int) $input->getOption('since');

        if (!$this->zahvatitBlokirovku()) {
            $style->error('Rollback-sync уже выполняется другим экземпляром команды.');

            return Command::FAILURE;
        }

        try {
            $report = $this->rollbackSync->run($since);
        } finally {
            $this->osvoboditBlokirovku();
        }

        $rows = [];

        foreach ($report as $key => $value) {
            $rows[] = [$key, (string) $value];
        }

        $style->table(['Шаг', 'Затронуто строк'], $rows);
        $style->success('Rollback-sync завершён.');

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
