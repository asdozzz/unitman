<?php

declare(strict_types=1);

namespace App\Initialization\Migrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260415000000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Create initialization table and insert proxy_host record';
    }

    public function up(Schema $schema): void
    {
        $this->addSql("create table IF NOT EXISTS initialization
            (
                id varchar not null primary key,
                prop varchar not null,
                value varchar default null,
                init integer default null
            );
        ");

        // insert default record for proxy_host if not exists
        $this->addSql("INSERT INTO initialization (id, prop, value, init)
            SELECT 'proxy_host', 'proxy_host', NULL, NULL
            WHERE NOT EXISTS (SELECT 1 FROM initialization WHERE prop = 'proxy_host')");
    }

    public function down(Schema $schema): void
    {
        $this->addSql('DROP TABLE IF EXISTS initialization');
    }
}
