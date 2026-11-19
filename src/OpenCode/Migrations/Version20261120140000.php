<?php

declare(strict_types=1);

namespace App\OpenCode\Migrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Уведомления о новых сообщениях OpenCode.
 *
 * opencode_notification — персистентная очередь уведомлений:
 *   turn_id уникален: повторное завершение хода не создаёт дубль;
 *   read_at is null — уведомление ещё не прочитано;
 *   kind равен ANSWER, QUESTION, ERROR или null.
 */
final class Version20261120140000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Уведомления OpenCode: таблица opencode_notification и индексы';
    }

    public function up(Schema $schema): void
    {
        $this->addSql(
            'create table if not exists opencode_notification ('
            . 'id varchar(64) not null primary key, '
            . 'account_id varchar(128) not null, '
            . 'session_id varchar(64) not null, '
            . 'turn_id varchar(64) not null, '
            . 'session_title varchar(256) not null default \'\', '
            . 'status varchar(32) not null, '
            . 'kind varchar(32) null, '
            . 'created_at integer not null, '
            . 'read_at integer null'
            . ')'
        );

        $this->addSql(
            'create unique index if not exists opencode_notification_turn_uq '
            . 'on opencode_notification (turn_id)'
        );

        $this->addSql(
            'create index if not exists opencode_notification_account_unread_idx '
            . 'on opencode_notification (account_id, read_at)'
        );
    }

    public function down(Schema $schema): void
    {
        $this->addSql('drop table if exists opencode_notification');
    }
}
