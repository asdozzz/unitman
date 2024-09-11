<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use App\Unitman\Infra\Repository\Project\SobitieIzHranilishaRepository;
use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20240908165644 extends AbstractMigration
{
    public function up(Schema $schema): void
    {
        $table = SobitieIzHranilishaRepository::TABLE;
        $this->addSql("create table IF NOT EXISTS $table
            (
                id varchar(128) not null constraint {$table}_pk primary key,
                project_id varchar(128) not null,
                payload jsonb,
                result int default 0,
                error text default null
            );
        ");
    }

    public function down(Schema $schema): void
    {
        $table = SobitieIzHranilishaRepository::TABLE;
        $this->addSql("DROP TABLE IF EXISTS $table");
    }
}
