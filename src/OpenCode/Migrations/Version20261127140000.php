<?php

declare(strict_types=1);

namespace App\OpenCode\Migrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Атомарная блокировка хода и адресные уведомления участникам.
 *
 * `opencode_session_running_turn` — краткоживущая резервация «один ход на
 * сессию». Намеренно **без внешнего ключа** на `opencode_session`:
 *
 * - резервация — это аренда с TTL, а не долговечный дочерний объект; строка
 *   перехватывается по истечении TTL и физически не связана с историей;
 * - один и тот же путь используют и unit-сессии, и workspace-сессии, а удаление
 *   сессии не должно требовать отдельного порядка удаления аренд или блокировать
 *   очистку осиротевшей строками;
 * - ограничение «только для workspace» при необходимости делается в коде, а не
 *   схемой, чтобы не ломать unit-ходы.
 *
 * Уведомления: уникальность переносится с `turn_id` на пару
 * `(turn_id, account_id)`. Иначе fan-out одному ходу смог бы создать только
 * одну строку, и остальные участники не получили бы уведомление. Повторное
 * завершение того же хода по-прежнему не создаёт дубль для одного получателя.
 */
final class Version20261127140000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'OpenCode: резервация хода (opencode_session_running_turn) и адресные уведомления (turn_id + account_id)';
    }

    public function up(Schema $schema): void
    {
        $this->addSql(
            'create table if not exists opencode_session_running_turn ('
            . 'session_id varchar(64) not null primary key, '
            . 'turn_id varchar(64) not null, '
            . 'created_at integer not null'
            . ')'
        );

        $this->addSql(
            'create index if not exists opencode_session_running_turn_turn_idx '
            . 'on opencode_session_running_turn (turn_id)'
        );

        $this->addSql('drop index if exists opencode_notification_turn_uq');
        $this->addSql(
            'create unique index if not exists opencode_notification_turn_recipient_uq '
            . 'on opencode_notification (turn_id, account_id)'
        );
    }

    public function down(Schema $schema): void
    {
        // Перед возвратом старого индекса оставляем по одной строке на ход:
        // иначе уникальность по turn_id не создастся на уже разосланных
        // уведомлениях.
        $this->addSql(
            'delete from opencode_notification a '
            . 'using opencode_notification b '
            . 'where a.turn_id = b.turn_id and a.id > b.id'
        );

        $this->addSql('drop index if exists opencode_notification_turn_recipient_uq');
        $this->addSql(
            'create unique index if not exists opencode_notification_turn_uq '
            . 'on opencode_notification (turn_id)'
        );

        $this->addSql('drop index if exists opencode_session_running_turn_turn_idx');
        $this->addSql('drop table if exists opencode_session_running_turn');
    }
}
