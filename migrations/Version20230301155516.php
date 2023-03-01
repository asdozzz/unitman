<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20230301155516 extends AbstractMigration
{
    public function getDescription(): string
    {
        return '';
    }

    public function up(Schema $schema): void
    {
        $this->addSql("create table unitman_event_store
        (
            id                serial  not null
                constraint unitman_event_store_pk
                    primary key,
            event_id          varchar not null,
            aggregate_root_id varchar not null,
            version           integer not null,
            payload           text    not null,
            constraint unitman_reconstitution
                unique (aggregate_root_id, version)
        );");

        $this->addSql("create table repo_list
            (
                id       varchar(128) not null
                    constraint repo_list_pk
                        primary key,
                type varchar      not null,
                name varchar      not null,
                repoUrl varchar      not null,
                repoLogin varchar      not null,
                repoPassword varchar      not null,
                confirmed    bit
            );
        ");

    }

    public function down(Schema $schema): void
    {
        $this->addSql('DROP TABLE unitman_event_store');
        $this->addSql('DROP TABLE repo_list');

    }
}
