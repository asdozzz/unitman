<?php

declare(strict_types=1);

namespace App\OpenCode\Infra\Cli;

use Doctrine\DBAL\Connection;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

/**
 * Read-only аудит перед backfill (Phase 0).
 *
 * Печатает счётчики и аномалии легаси workspace-агрегата. Ничего не пишет;
 * безопасен для запуска на боевой копии. Ненулевой код не возвращает:
 * аудит — отчёт, гейтом служит `workspace-migration:check`.
 */
#[AsCommand(
    name: 'app:opencode:workspace-migration:audit',
    description: 'Read-only аудит легаси workspace-агрегата перед миграцией'
)]
final class WorkspaceMigrationAuditCommand extends Command
{
    public function __construct(private readonly Connection $connection)
    {
        parent::__construct();
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $style = new SymfonyStyle($input, $output);
        $style->title('OpenCode workspace migration: audit');

        $rows = [];

        $rows[] = ['workspace sessions (tip=workspace + opencode_workspace)', $this->chislo(
            "select count(*) from opencode_session s join opencode_workspace w on w.session_id = s.id where s.tip = 'workspace'"
        )];
        $rows[] = ['tip=workspace без opencode_workspace', $this->chislo(
            "select count(*) from opencode_session s left join opencode_workspace w on w.session_id = s.id where s.tip = 'workspace' and w.session_id is null"
        )];
        $rows[] = ['tip=unit, но есть opencode_workspace', $this->chislo(
            "select count(*) from opencode_session s join opencode_workspace w on w.session_id = s.id where s.tip = 'unit'"
        )];
        $rows[] = ['workspace с непустыми unit_id/unit_project_id', $this->chislo(
            "select count(*) from opencode_session s where s.tip = 'workspace' and (s.unit_id is not null or s.unit_project_id is not null)"
        )];
        $rows[] = ['workspace с дублями (session_id, seq)', $this->chislo(
            "select count(*) from (select t.session_id from opencode_turn t join opencode_session s on s.id = t.session_id and s.tip = 'workspace' group by t.session_id, t.seq having count(*) > 1) d"
        )];
        $rows[] = ['workspace ходы с пустым автором', $this->chislo(
            "select count(*) from opencode_turn t join opencode_session s on s.id = t.session_id where s.tip = 'workspace' and coalesce(t.author_account_id, '') = ''"
        )];
        $rows[] = ['workspace уведомления', $this->chislo(
            "select count(*) from opencode_notification n join opencode_session s on s.id = n.session_id where s.tip = 'workspace'"
        )];
        $rows[] = ['workspace уведомления прочитанные', $this->chislo(
            "select count(*) from opencode_notification n join opencode_session s on s.id = n.session_id where s.tip = 'workspace' and n.read_at is not null"
        )];
        $rows[] = ['workspace без строки owner', $this->chislo(
            "select count(*) from opencode_session s where s.tip = 'workspace' and not exists (select 1 from opencode_session_participant p where p.session_id = s.id and p.account_id = s.account_id and p.role = 'owner')"
        )];
        $rows[] = ['workspace с более чем одним owner', $this->chislo(
            "select count(*) from (select p.session_id from opencode_session_participant p join opencode_session s on s.id = p.session_id and s.tip = 'workspace' where p.role = 'owner' group by p.session_id having count(*) > 1) d"
        )];
        $rows[] = ['opencode_workspace_update без workspace', $this->chislo(
            'select count(*) from opencode_workspace_update u left join opencode_workspace w on w.session_id = u.session_id where w.session_id is null'
        )];
        $rows[] = ['сироты opencode_workspace_unit', $this->chislo(
            'select count(*) from opencode_workspace_unit u left join opencode_workspace w on w.session_id = u.workspace_id where w.session_id is null'
        )];
        $rows[] = ['сироты workspace_runner', $this->chislo(
            'select count(*) from workspace_runner r left join opencode_workspace w on w.session_id = r.workspace_id where w.session_id is null'
        )];
        $rows[] = ['уже перенесено: сессии нового агрегата', $this->chislo('select count(*) from opencode_workspace_session')];
        $rows[] = ['уже перенесено: ходы нового агрегата', $this->chislo('select count(*) from opencode_workspace_turn')];
        $rows[] = ['уже перенесено: уведомления нового агрегата', $this->chislo('select count(*) from opencode_workspace_notification')];

        $style->table(['Пункт', 'Значение'], $rows);
        $style->success('Аудит завершён (read-only).');

        return Command::SUCCESS;
    }

    private function chislo(string $sql): int
    {
        return (int) $this->connection->fetchOne($sql);
    }
}
