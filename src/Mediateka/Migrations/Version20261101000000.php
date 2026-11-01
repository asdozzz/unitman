<?php

declare(strict_types=1);

namespace App\Mediateka\Migrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20261101000000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Медиатека: реестр файлов юнитов';
    }

    public function up(Schema $schema): void
    {
        $this->addSql("create table IF NOT EXISTS mediateka_file
            (
                id varchar(36) not null primary key,
                unit_id varchar(36) not null,
                name varchar(255) not null,
                size bigint not null default 0,
                mime_type varchar(100) not null,
                storage_key varchar(512) not null,
                uploaded_by varchar(36) default null,
                created_at integer not null
            );
        ");

        $this->addSql('create index IF NOT EXISTS mediateka_file_unit_id_idx on mediateka_file (unit_id)');
        $this->addSql('create index IF NOT EXISTS mediateka_file_storage_key_idx on mediateka_file (storage_key)');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('drop table if exists mediateka_file');
    }
}
