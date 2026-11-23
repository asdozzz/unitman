<?php

declare(strict_types=1);

namespace App\OpenCode\Migrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Модель OpenCode в сессиях и ходах.
 *
 * `null` в существующих строках означает «взять дефолтную модель реквизитов
 * при следующем ходе»: миграция аддитивна и не переписывает историю.
 */
final class Version20261124110000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'OpenCode: модель в сессиях (unit/workspace) и ходах';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('alter table opencode_session add column if not exists model varchar(128) null');
        $this->addSql('alter table opencode_unit_session add column if not exists model varchar(128) null');
        $this->addSql('alter table opencode_workspace_session add column if not exists model varchar(128) null');

        $this->addSql('alter table opencode_turn add column if not exists model varchar(128) null');
        $this->addSql('alter table opencode_unit_session_turn add column if not exists model varchar(128) null');
        $this->addSql('alter table opencode_workspace_turn add column if not exists model varchar(128) null');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('alter table opencode_session drop column if exists model');
        $this->addSql('alter table opencode_unit_session drop column if exists model');
        $this->addSql('alter table opencode_workspace_session drop column if exists model');

        $this->addSql('alter table opencode_turn drop column if exists model');
        $this->addSql('alter table opencode_unit_session_turn drop column if exists model');
        $this->addSql('alter table opencode_workspace_turn drop column if exists model');
    }
}
