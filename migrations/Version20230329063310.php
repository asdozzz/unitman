<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20230329063310 extends AbstractMigration
{
    public function getDescription(): string
    {
        return '';
    }

    public function up(Schema $schema): void
    {
        $this->addSql("create table spisok_unitov
            (
                id       varchar(128) not null
                    constraint spisok_unitov_pk
                        primary key,
                author_id varchar      not null,
                name varchar      not null,
                project_id varchar      not null,
                project_name varchar      not null,
                branch varchar      not null,
                state text  not null,
                wait_result_from_runner bit,
                commands text default null
            );
        ");
    }

    public function down(Schema $schema): void
    {
        $this->addSql('DROP TABLE IF EXISTS spisok_unitov');
    }
}
