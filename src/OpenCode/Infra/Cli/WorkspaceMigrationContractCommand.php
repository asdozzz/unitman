<?php

declare(strict_types=1);

namespace App\OpenCode\Infra\Cli;

use App\OpenCode\Infra\Migration\WorkspaceAggregateContract;
use Doctrine\DBAL\Connection;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

/**
 * Contract-шаг миграции workspace.
 *
 * По умолчанию dry-run: показывает, что будет удалено, и требует `--force`.
 * Перед удалением обязательно прогоняет гейт сверки и проверку совместимости
 * с rollback-sync; при расхождении возвращает `FAILURE` и ничего не удаляет.
 * Легаси-таблицы физически не удаляются, unit-данные не затрагиваются.
 */
#[AsCommand(
    name: 'app:opencode:workspace-migration:contract',
    description: 'Удаляет workspace-строки легаси-агрегата; по умолчанию dry-run'
)]
final class WorkspaceMigrationContractCommand extends Command
{
    private const LOCK_KEY = 'opencode.workspace.migration.contract';

    public function __construct(
        private readonly Connection $connection,
        private readonly WorkspaceAggregateContract $contract,
    ) {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this->addOption(
            'force',
            null,
            InputOption::VALUE_NONE,
            'Выполнить удаление. Без флага команда работает как dry-run',
        );
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $style = new SymfonyStyle($input, $output);
        $style->title('OpenCode workspace migration: contract');

        $problems = $this->contract->proveritBezopasnost();

        if ($problems !== []) {
            $style->error('Contract запрещён: проверки не пройдены.');
            $style->listing($problems);

            return Command::FAILURE;
        }

        $style->success('Parity и rollback-sync-совместимость: OK.');

        $plan = $this->contract->plan();

        $rows = [];

        foreach ($plan as $table => $count) {
            $rows[] = [$table, (string) $count];
        }

        $style->table(['Что', 'Строк'], $rows);

        if ($input->getOption('force') !== true) {
            $style->note('Dry-run: изменения не применены. Для удаления запустите с --force.');

            return Command::SUCCESS;
        }

        if (!$this->zahvatitBlokirovku()) {
            $style->error('Contract уже выполняется другим экземпляром команды.');

            return Command::FAILURE;
        }

        try {
            $report = $this->contract->run();
        } finally {
            $this->osvoboditBlokirovku();
        }

        $rows = [];

        foreach ($report as $table => $count) {
            $rows[] = [$table, (string) $count];
        }

        $style->table(['Таблица', 'Удалено строк'], $rows);
        $style->success('Contract завершён. Unit-сессии и новый агрегат не затронуты.');

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
