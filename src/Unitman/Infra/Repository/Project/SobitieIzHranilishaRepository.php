<?php

namespace App\Unitman\Infra\Repository\Project;

use App\Unitman\Business\Model\SobitieIzHranilisha;
use Doctrine\DBAL\Connection;
use Symfony\Component\Serializer\Serializer;

final class SobitieIzHranilishaRepository
{
    const TABLE = 'sobitiya_iz_hranilisha';
    public function __construct(private readonly Connection $connection)
    {
    }

    function insert(SobitieIzHranilisha $sobitieIzHranilisha): void
    {
        $this->connection->insert(self::TABLE, ['id' => $sobitieIzHranilisha->id,'project_id' => $sobitieIzHranilisha->projectId, 'payload' => $sobitieIzHranilisha->eventPayload]);
    }

    function makeModelByRow(array $row): SobitieIzHranilisha
    {
        return new SobitieIzHranilisha($row['id'], $row['project_id'], $row['payload']);
    }

    /**
     * @return SobitieIzHranilisha[]
     * */
    function poluchitZadachiNaObrabotku(int $limit = 10): array
    {
        $table = self::TABLE;
        $rows = $this->connection->fetchAllAssociative("SELECT * FROM $table WHERE result=0 LIMIT $limit for update skip locked");

        $result = [];
        foreach ($rows as $row) {
            $result[] = $this->makeModelByRow($row);
        }

        return $result;
    }

    function udalitZadachuIzOcheredi(string $id): void
    {
        $this->connection->delete(self::TABLE, ['id'=> $id]);
    }

    function setError(string $id, string $error): void
    {
        $this->connection->update(self::TABLE, ['result' => 1, 'error' => $error], ['id'=> $id]);
    }

    function setSuccess(string $id, string $error): void
    {
        $this->connection->update(self::TABLE, ['result' => 2, 'error' => $error], ['id'=> $id]);
    }
}
