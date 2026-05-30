<?php

declare(strict_types=1);

namespace App\Account\Migrations;

use App\Account\Business\Command\RegisterFirstAccount;
use App\Account\Business\UseCase\RegisterFirstAccountUseCase;
use Doctrine\DBAL\Connection;
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
        $this->addSql("create table if not exists checkpoint_store
            (
                name varchar(128) not null constraint checkpoint_store_pk primary key,
                checkpoint int default 0
            );
        ");

        $this->addSql("create table if not exists account_event_store
        (
            id                serial  not null
                constraint account_event_store_pk
                    primary key,
            event_id          varchar not null,
            aggregate_root_id varchar not null,
            version           integer not null,
            payload           jsonb    not null,
            constraint reconstitution
                unique (aggregate_root_id, version)
        );");
    }

    public function down(Schema $schema): void
    {
        $this->addSql('DROP TABLE if exists checkpoint_store');
        $this->addSql('DROP TABLE if exists account_event_store');
    }
}
