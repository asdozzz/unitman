<?php

declare(strict_types=1);

namespace App\Mediateka\Migrations;

use App\Mediateka\Business\Utils\ImyaFaylaDlyaDockerVolume;
use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Имя файла медиатеки используется как имя docker-тома при подготовке юнита,
 * поэтому приводим к правилам docker уже загруженные ранее файлы.
 */
final class Version20261108150000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Медиатека: имена файлов приводятся к правилам docker-тома';
    }

    public function up(Schema $schema): void
    {
        foreach ($this->imenaDlyaObnovleniya() as $id => $imya) {
            $this->addSql('update mediateka_file set name = ? where id = ?', [$imya, $id]);
        }
    }

    /**
     * Откат не восстанавливает исходные имена (они не сохранялись),
     * но и не разрушает данные: нормализованное имя остаётся валидным.
     */
    public function down(Schema $schema): void
    {
    }

    /**
     * @return array<string, string> id файла => новое имя
     */
    private function imenaDlyaObnovleniya(): array
    {
        $result = [];

        $rows = $this->connection->fetchAllAssociative('select id, name from mediateka_file');

        foreach ($rows as $row) {
            $id = (string) $row['id'];
            $imya = ImyaFaylaDlyaDockerVolume::normalizovatImyaArhiva((string) $row['name']);

            if ($imya !== (string) $row['name']) {
                $result[$id] = $imya;
            }
        }

        return $result;
    }
}
