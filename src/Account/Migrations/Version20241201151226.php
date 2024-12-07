<?php

declare(strict_types=1);

namespace App\Account\Migrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20241201151226 extends AbstractMigration
{
    public function getDescription(): string
    {
        return '';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('ALTER TABLE jwt_user ADD nickname varchar DEFAULT NULL');

    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE jwt_user DROP nickname');

    }
}
