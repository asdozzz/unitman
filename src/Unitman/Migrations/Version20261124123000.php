<?php

declare(strict_types=1);

namespace App\Unitman\Migrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Удаление таблицы legacy-модуля Initialization.
 *
 * Сам модуль удаляется отдельной волной; здесь только идемпотентное удаление
 * таблицы. Подробности: plans/runner-multi-runner.md.
 */
final class Version20261124123000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Unitman: удаление legacy-таблицы initialization';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('drop table if exists initialization');
    }

    public function down(Schema $schema): void
    {
        // Восстановление таблицы не выполняется: модуль Initialization удаляется.
        // Down оставлен пустым осознанно, чтобы не возвращать legacy-схему.
    }
}
