<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20230309110546 extends AbstractMigration
{
    public function getDescription(): string
    {
        return '';
    }

    public function up(Schema $schema): void
    {
        $this->addSql("create table project_list
            (
                id       varchar(128) not null
                    constraint project_list_pk
                        primary key,
                code varchar      not null,
                name varchar      not null,
                main_branch varchar      not null,
                state varchar      not null,
                build_text text      default null,
                remove_text text      default null,
                is_active    bit
            );
        ");

        $this->addSql("create table project_users
            (
                project_id varchar(128) not null,
                user_id varchar(128) not null,
                role varchar(128) not null
            );
        ");

        $this->addSql("create unique index unique_project_user on project_users (project_id, user_id);");
    }

    public function down(Schema $schema): void
    {
        $this->addSql('DROP TABLE IF EXISTS project_list');
        $this->addSql('DROP TABLE IF EXISTS project_users');
    }
}
