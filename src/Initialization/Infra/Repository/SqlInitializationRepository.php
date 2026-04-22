<?php

namespace App\Initialization\Infra\Repository;

use App\Initialization\Business\Model\InitializationRecord;
use App\Initialization\Business\Port\InitializationRepository;
use Doctrine\DBAL\Connection;

final class SqlInitializationRepository implements InitializationRepository
{
    const TABLE = 'initialization';

    public function __construct(private Connection $connection)
    {
    }

    public function init(): void
    {
        $this->connection->executeQuery("create table IF NOT EXISTS " . self::TABLE . "
            (
                id varchar not null primary key,
                prop varchar not null,
                value varchar default null,
                init integer default null
            );
        ");
    }

    public function destroy(): void
    {
        $this->connection->executeQuery('DROP TABLE IF EXISTS ' . self::TABLE);
    }

    public function truncate(): void
    {
        $this->connection->executeQuery("TRUNCATE " . self::TABLE);
    }

    public function resetByProp(string $prop): void
    {
        $this->connection->executeQuery("DELETE FROM " . self::TABLE . " WHERE prop = :prop", ['prop' => $prop]);
    }

    public function getByProp(string $prop): InitializationRecord
    {
        $row = $this->connection->fetchAssociative("SELECT * FROM " . self::TABLE . " WHERE prop = :prop", ['prop' => $prop]);
        if (empty($row)) {
            throw new \DomainException("Initialization record with prop $prop not found");
        }

        return InitializationRecord::fromRow($row);
    }

    public function save(InitializationRecord $record): void
    {
        $data = ['id' => $record->id, 'prop' => $record->prop, 'value' => $record->value, 'init' => 1];
        $this->connection->update(self::TABLE, $data, ['prop' => $record->prop]);
    }

    public function getAll(): array
    {
        return $this->connection->fetchAllAssociative('SELECT * FROM '.self::TABLE);
    }
}
