<?php

namespace App\Unitman\Infra\Repository\Unit;

use App\Unitman\Business\Port\Unit\UmeetPoluchatProzesiUnitaPoId;
use App\Unitman\Business\ReadModel\Unit\ProzesUnita;
use App\Unitman\Business\ReadModel\Unit\ProzesUnitaBezShagov;
use Doctrine\DBAL\Connection;
use Symfony\Component\Serializer\Serializer;

final class ProzesUnitaRepository implements UmeetPoluchatProzesiUnitaPoId
{
    const TABLE = 'prozesi_unitov';
    public function __construct(private Connection $connection, private Serializer $serializer)
    {
    }

    function init(): void
    {
        $table = self::TABLE;
        $this->connection->executeQuery("create table IF NOT EXISTS $table
            (
                id varchar not null primary key,
                unit_id varchar not null,
                payload jsonb
            );
        ");
    }

    function destroy(): void
    {
        $table = self::TABLE;
        $this->connection->executeQuery("DROP TABLE IF EXISTS $table");
    }
    function truncate(): void
    {
        $table = self::TABLE;
        $this->connection->executeQuery("TRUNCATE $table");
    }

    function resetById(string $id): void
    {
        $table = self::TABLE;
        $this->connection->executeQuery("DELETE FROM $table where id = '$id'");
    }

    function insert(ProzesUnita $readModel): void
    {
        $data = [
            'id' => $readModel->id,
            'unit_id' => $readModel->unitId,
            'payload' => $this->serializer->serialize($readModel, 'json')
        ];

        $this->connection->insert(self::TABLE, $data);
    }

    function update(ProzesUnita $readModel): void
    {
        $this->connection->update(self::TABLE, ['payload' => $this->serializer->serialize($readModel, 'json')],['id' => $readModel->id]);
    }

    /**
     * @return array<ProzesUnitaBezShagov>
     * */
    function poluchitProzesiPoIdUnita(string $unitId): array
    {
        $table = self::TABLE;
        $rows = $this->connection->fetchAllAssociative("SELECT id,payload FROM $table WHERE unit_id = :unitId ORDER BY id desc", ['unitId' => $unitId]);
        if (empty($rows)) {
            return [];
        }
        return array_map(fn(array $row) => $this->makeReadModelByRowWithoutSteps($row), $rows);
    }

    function makeReadModelByRowWithoutSteps(array $row): ProzesUnitaBezShagov
    {
        $unit = $this->serializer->deserialize($row['payload'], ProzesUnitaBezShagov::class, 'json');
        return $unit;
    }

    function makeReadModelByRow(array $row): ProzesUnita
    {
        $unit = $this->serializer->deserialize($row['payload'], ProzesUnita::class, 'json');
        return $unit;
    }

    function poluchitShagiZadachiPoId(string $prozesId, string $zadachaId): array
    {
        $table = self::TABLE;
        $row = $this->connection->fetchAssociative("SELECT id,payload FROM $table WHERE id = :id ORDER BY id desc", ['id' => $prozesId]);
        if (empty($row)) {
            throw new \DomainException('unit.runner_job_not_found');
        }

        $prozess = $this->makeReadModelByRow($row);
        $shagi = [];

        foreach ($prozess->jobs as $zadacha) {
            if ($zadacha->id === $zadachaId) {
                $shagi = $zadacha->steps;
                break;
            }
        }

        return $shagi;
    }
}
