<?php

declare(strict_types=1);

namespace App\Mediateka\Migrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Файлы хода OpenCode: ссылка на ход и уникальность имени в рамках хода.
 *
 * Колонка `opencode_turn_id` повторяет тип `opencode_turn.id` (varchar(64)).
 * Заполнения нет: старые записи остаются с null и не попадают в частичный
 * уникальный индекс.
 */
final class Version20261127130000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Медиатека: артефакты хода OpenCode (opencode_turn_id)';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('alter table mediateka_file add column if not exists opencode_turn_id varchar(64) default null');
        $this->addSql('create index if not exists mediateka_file_opencode_turn_id_idx on mediateka_file (opencode_turn_id)');
        $this->addSql(
            'create unique index if not exists mediateka_file_turn_name_uniq
             on mediateka_file (opencode_turn_id, name)
             where opencode_turn_id is not null'
        );
    }

    public function down(Schema $schema): void
    {
        $this->addSql('drop index if exists mediateka_file_turn_name_uniq');
        $this->addSql('drop index if exists mediateka_file_opencode_turn_id_idx');
        $this->addSql('alter table mediateka_file drop column if exists opencode_turn_id');
    }
}
