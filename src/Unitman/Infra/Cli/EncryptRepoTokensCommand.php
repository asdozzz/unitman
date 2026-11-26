<?php

declare(strict_types=1);

namespace App\Unitman\Infra\Cli;

use App\Unitman\Infra\Service\RepoTokenEncryptionMigrator;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

/**
 * Opt-in шифрование legacy-токенов репозиториев на месте.
 *
 * По умолчанию — dry-run: печатает только количество строк, подлежащих
 * миграции. Реальная перезапись выполняется только с `--apply`, в одной
 * транзакции, после создания backup-таблиц.
 *
 * Это единственная санкционированная перезапись старых payload'ов event store
 * (см. WS-2, constraint #3). Требует координации с WS-12 (план миграции).
 */
#[AsCommand(
    name: 'app:security:encrypt-repo-tokens',
    description: 'Перешифровать legacy-токены репозиториев (dry-run по умолчанию)',
)]
final class EncryptRepoTokensCommand extends Command
{
    public function __construct(
        private readonly RepoTokenEncryptionMigrator $migrator,
    ) {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this->addOption(
            'apply',
            null,
            InputOption::VALUE_NONE,
            'Выполнить перезапись (по умолчанию только отчёт)',
        );
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);
        $apply = (bool)$input->getOption('apply');

        $plan = $this->migrator->plan();

        $io->title('Repo tokens at-rest encryption');

        if (!$apply) {
            $io->writeln(sprintf('event store legacy rows: %d', $plan['eventStore']));
            $io->writeln(sprintf('repo_list legacy rows: %d', $plan['repoList']));
            $io->note('Dry-run. Ничего не изменено. Для перезаписи запустите с --apply.');

            return Command::SUCCESS;
        }

        $result = $this->migrator->apply();

        $io->success(sprintf(
            'Перешифровано: event store %d, repo_list %d. Backup-таблицы созданы.',
            $result['eventStore'],
            $result['repoList'],
        ));

        return Command::SUCCESS;
    }
}
