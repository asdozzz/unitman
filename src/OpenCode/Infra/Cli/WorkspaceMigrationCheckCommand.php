<?php

declare(strict_types=1);

namespace App\OpenCode\Infra\Cli;

use App\OpenCode\Infra\Migration\WorkspaceAggregateParityChecker;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

/**
 * Гейт сверки легаси и нового workspace-агрегата.
 *
 * Read-only. Возвращает `FAILURE`, если есть хоть одно расхождение по
 * количеству или ключевым полям. Используется как gate Phase 3.
 */
#[AsCommand(
    name: 'app:opencode:workspace-migration:check',
    description: 'Сверяет легаси- и новый workspace-агрегат; exit != 0 при расхождениях'
)]
final class WorkspaceMigrationCheckCommand extends Command
{
    public function __construct(private readonly WorkspaceAggregateParityChecker $checker)
    {
        parent::__construct();
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $style = new SymfonyStyle($input, $output);
        $style->title('OpenCode workspace migration: parity check');

        $result = $this->checker->proverit();

        $rows = [];
        $mismatches = 0;

        foreach ($result['checks'] as $group => $values) {
            foreach ($values as $name => $count) {
                $rows[] = [$group, $name, (string) $count];

                if ($count > 0) {
                    ++$mismatches;
                }
            }
        }

        $style->table(['Группа', 'Проверка', 'Расхождений'], $rows);

        if ($result['ok']) {
            $style->success('Расхождений нет.');

            return Command::SUCCESS;
        }

        $style->error(sprintf('Расхождений: %d. Backfill не может быть признан завершённым.', $mismatches));

        return Command::FAILURE;
    }
}
