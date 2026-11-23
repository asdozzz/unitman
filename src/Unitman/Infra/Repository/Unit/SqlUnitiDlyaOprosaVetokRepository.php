<?php

declare(strict_types=1);

namespace App\Unitman\Infra\Repository\Unit;

use App\Unitman\Business\Port\Unit\UmeetPoluchatUnitiDlyaOprosaVetok;
use App\Unitman\Business\ReadModel\Unit\UnitaDlyaOprosaVetok;
use Doctrine\DBAL\Connection;

/**
 * Выборка юнитов для polling.
 *
 * Читает только read-модели `spisok_unitov` и `project_list`: событийный
 * поток здесь не нужен, а `vebhukNastroen` уже спроецирован в `project_list`.
 */
final class SqlUnitiDlyaOprosaVetokRepository implements UmeetPoluchatUnitiDlyaOprosaVetok
{
    public function __construct(private Connection $connection)
    {
    }

    /**
     * @return UnitaDlyaOprosaVetok[]
     */
    public function poluchitUnitiDlyaOprosaVetok(): array
    {
        $rows = $this->connection->fetchAllAssociative(
            "select
                u.payload->>'id' as unit_id,
                u.payload->>'projectId' as project_id,
                p.payload->>'code' as project_code,
                p.payload->>'repoId' as repo_id,
                u.payload->>'branch' as branch,
                p.payload->>'mainBranch' as main_branch,
                nullif(u.payload->>'unixtimePoslednegoObnovleniyaVHranilishe', '')::bigint as last_unixtime,
                coalesce((p.payload->'nastroikiHukaProekta'->>'avtoobnovlenie')::boolean, true) as avtoobnovlenie,
                coalesce((p.payload->'nastroikiHukaProekta'->>'avtoudalenie')::boolean, true) as avtoudalenie
            from spisok_unitov u
            join project_list p on p.id = u.payload->>'projectId'
            where coalesce((p.payload->>'isActive')::boolean, false) = true
              and coalesce((p.payload->'nastroikiHukaProekta'->>'vebhukNastroen')::boolean, false) = false
              and u.payload->>'branch' is not null
              and p.payload->>'code' is not null
              and p.payload->>'repoId' is not null
              and p.payload->>'mainBranch' is not null"
        );

        return array_map(
            static fn(array $row): UnitaDlyaOprosaVetok => new UnitaDlyaOprosaVetok(
                (string)$row['unit_id'],
                (string)$row['project_id'],
                (string)$row['project_code'],
                (string)$row['repo_id'],
                (string)$row['branch'],
                (string)$row['main_branch'],
                $row['last_unixtime'] === null ? null : (int)$row['last_unixtime'],
                (bool)$row['avtoobnovlenie'],
                (bool)$row['avtoudalenie'],
            ),
            $rows
        );
    }
}
