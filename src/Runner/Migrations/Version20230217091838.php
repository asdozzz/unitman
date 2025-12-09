<?php

declare(strict_types=1);

namespace App\Runner\Migrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20230217091838 extends AbstractMigration
{
    public function getDescription(): string
    {
        return '';
    }

    public function up(Schema $schema): void
    {
        $this->addSql("create table IF NOT EXISTS runner_state
            (
                id varchar(128) not null constraint runner_state_pk primary key,
                payload jsonb
            );
        ");
    }

    public function down(Schema $schema): void
    {
        $this->addSql('DROP TABLE runner_state');
    }
}
