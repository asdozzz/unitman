<?php

declare(strict_types=1);

namespace App\Mediateka\Migrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Файл медиатеки может быть загружен до создания юнита (черновик),
 * поэтому привязка к юниту становится необязательной.
 */
final class Version20261101120000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Медиатека: файл может быть черновиком (unit_id nullable)';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('alter table mediateka_file alter column unit_id drop not null');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('delete from mediateka_file where unit_id is null');
        $this->addSql('alter table mediateka_file alter column unit_id set not null');
    }
}
