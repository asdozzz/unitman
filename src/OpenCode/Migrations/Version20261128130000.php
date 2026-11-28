<?php

declare(strict_types=1);

namespace App\OpenCode\Migrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Моды агентов OpenCode: общий каталог, персональные настройки пользователя,
 * дефолты по типу чата и снимок мода в сессиях/ходах.
 *
 * Seed-промпты build/plan взяты из базового системного промпта OpenCode
 * v2.3.2 (`packages/core/src/session/runner/prompt/system.txt`). Встроенные
 * build/plan не имеют отдельного system-промпта: они различаются правами.
 * Поэтому оба мода получают один и тот же базовый текст, а различие задаётся
 * полем `access_mode`.
 *
 * Таблицы аддитивные. `agent_mode_id` не имеет FK на каталог: мод может быть
 * архивирован (soft delete), а история ходов должна оставаться читаемой.
 */
final class Version20261128130000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Моды агентов OpenCode: каталог, настройки пользователя, дефолты и снимок в сессиях/ходах';
    }

    public function up(Schema $schema): void
    {
        $this->addSql("create table if not exists opencode_agent_mode
            (
                id               varchar(64)  not null
                    constraint opencode_agent_mode_pk primary key,
                slug             varchar(64)  not null,
                name             varchar(128) not null,
                description      varchar(512) not null default '',
                prompt           text         not null,
                access_mode      varchar(16)  not null,
                is_global        boolean      not null default false,
                owner_account_id varchar(128),
                version          integer      not null default 1,
                created_by       varchar(128) not null,
                updated_by       varchar(128) not null,
                created_at       integer      not null,
                updated_at       integer      not null,
                archived_at      integer
            );
        ");

        // Уникальность slug в области видимости проверяется в репозитории:
        // частичные уникальные индексы переносимы не на всех СУБД.
        $this->addSql('create index if not exists opencode_agent_mode_global_slug_idx on opencode_agent_mode (is_global, slug);');
        $this->addSql('create index if not exists opencode_agent_mode_owner_slug_idx on opencode_agent_mode (owner_account_id, slug);');
        $this->addSql('create index if not exists opencode_agent_mode_archived_idx on opencode_agent_mode (archived_at);');

        $this->addSql("create table if not exists opencode_agent_mode_user
            (
                account_id    varchar(128) not null,
                mode_id       varchar(64)  not null,
                credential_id varchar(64),
                model         varchar(128),
                enabled       boolean      not null default true,
                sort_order    integer      not null default 0,
                updated_at    integer      not null,
                constraint opencode_agent_mode_user_pk primary key (account_id, mode_id)
            );
        ");

        $this->addSql('create index if not exists opencode_agent_mode_user_account_idx on opencode_agent_mode_user (account_id, sort_order);');

        $this->addSql("create table if not exists opencode_agent_mode_default
            (
                account_id varchar(128) not null,
                chat_type  varchar(16)  not null,
                mode_id    varchar(64)  not null,
                updated_at integer      not null,
                constraint opencode_agent_mode_default_pk primary key (account_id, chat_type)
            );
        ");

        // Снимок стартового мода сессии.
        $this->addSql('alter table opencode_session add column if not exists agent_mode_id varchar(64) null');
        $this->addSql('alter table opencode_unit_session add column if not exists agent_mode_id varchar(64) null');
        $this->addSql('alter table opencode_workspace_session add column if not exists agent_mode_id varchar(64) null');

        // Фактический мод хода и снимок имени для истории.
        $this->addSql('alter table opencode_turn add column if not exists agent_mode_id varchar(64) null');
        $this->addSql("alter table opencode_turn add column if not exists agent_mode_name varchar(128) not null default ''");
        $this->addSql('alter table opencode_unit_session_turn add column if not exists agent_mode_id varchar(64) null');
        $this->addSql("alter table opencode_unit_session_turn add column if not exists agent_mode_name varchar(128) not null default ''");
        $this->addSql('alter table opencode_workspace_turn add column if not exists agent_mode_id varchar(64) null');
        $this->addSql("alter table opencode_workspace_turn add column if not exists agent_mode_name varchar(128) not null default ''");

        $this->seedGlobalModes();
    }

    public function down(Schema $schema): void
    {
        $this->addSql('alter table opencode_workspace_turn drop column if exists agent_mode_name');
        $this->addSql('alter table opencode_workspace_turn drop column if exists agent_mode_id');
        $this->addSql('alter table opencode_unit_session_turn drop column if exists agent_mode_name');
        $this->addSql('alter table opencode_unit_session_turn drop column if exists agent_mode_id');
        $this->addSql('alter table opencode_turn drop column if exists agent_mode_name');
        $this->addSql('alter table opencode_turn drop column if exists agent_mode_id');
        $this->addSql('alter table opencode_workspace_session drop column if exists agent_mode_id');
        $this->addSql('alter table opencode_unit_session drop column if exists agent_mode_id');
        $this->addSql('alter table opencode_session drop column if exists agent_mode_id');

        $this->addSql('drop table if exists opencode_agent_mode_default');
        $this->addSql('drop table if exists opencode_agent_mode_user');
        $this->addSql('drop table if exists opencode_agent_mode');
    }

    private function seedGlobalModes(): void
    {
        // Базовый промпт OpenCode v2.3.2 с раскрытым блоком tool guidance.
        // `agent.system` подставляется как есть, поэтому placeholder
        // `${OPENCODE_TOOL_GUIDANCE}` раскрыт статически для всех трёх
        // основных инструментов.
        $prompt = <<<'PROMPT'
You are an AI agent running in OpenCode, a coding agent harness. Help the user accomplish their goals using the tools you have available.

# Harness
- Responses are rendered as GitHub-flavored Markdown.
- `<system-reminder>` blocks are harness instructions, not user-authored content. Read and follow them.
- Prefer parallelizing independent tool calls.
- Prefer dedicated tools over shell commands; fall back to the shell when a tool cannot do what you need.
- Do not chain shell commands with separators like `echo "====";` or `printf '---'`; the output becomes noisy in a way that makes the user's side of the conversation worse.
- Use the write tool to create files or completely replace their content. Prefer using the edit tool for targeted changes.
- Use the edit tool for targeted changes to existing text files. It replaces the exact text in `oldString` with `newString`, and the values must differ. By default, `oldString` must occur exactly once. If it occurs multiple times, include more surrounding context to make it unique or set `replaceAll` to true to replace every occurrence.

# Communication
- Use clear file paths when referring to files.
- Keep responses clear and concise, and avoid unnecessary technical jargon.

# Working in codebases
- Keep changes consistent with the structure, naming, style, and patterns of the surrounding code.
- Treat unfamiliar files or changes as potential user work and investigate before deleting or overwriting them.
PROMPT;

        $promptSql = $this->connection->quote($prompt);
        $now = time();

        $modes = [
            [
                'id' => '00000000-0000-4000-8000-000000000001',
                'slug' => 'build',
                'name' => 'Build',
                'description' => 'The default agent. Executes tools based on configured permissions.',
                'access_mode' => 'build',
            ],
            [
                'id' => '00000000-0000-4000-8000-000000000002',
                'slug' => 'plan',
                'name' => 'Plan',
                'description' => 'Read-only agent for exploring the codebase and planning work before implementation.',
                'access_mode' => 'plan',
            ],
        ];

        foreach ($modes as $mode) {
            $id = $this->connection->quote($mode['id']);
            $slug = $this->connection->quote($mode['slug']);
            $name = $this->connection->quote($mode['name']);
            $description = $this->connection->quote($mode['description']);
            $accessMode = $this->connection->quote($mode['access_mode']);

            $this->addSql(
                'insert into opencode_agent_mode
                    (id, slug, name, description, prompt, access_mode, is_global,
                     owner_account_id, version, created_by, updated_by, created_at, updated_at)
                 select ' . $id . ', ' . $slug . ', ' . $name . ', ' . $description . ', '
                . $promptSql . ', ' . $accessMode . ', true, null, 1, '
                . "'system', 'system', " . $now . ', ' . $now
                . ' where not exists (select 1 from opencode_agent_mode where id = ' . $id . ')'
            );
        }
    }
}
