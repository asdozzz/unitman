<?php

namespace App\Unitman\Infra\Repository\Unit;

use App\Unitman\Business\Command\Unit\GetMyUnits;
use App\Unitman\Business\Command\Unit\GetUnitList;
use App\Unitman\Business\Port\Unit\CanFindUnitDouble;
use App\Unitman\Business\Port\Unit\CanFindUnitIdByContainerName;
use App\Unitman\Business\Port\Unit\CanGetMyUnits;
use App\Unitman\Business\Port\Unit\CanGetUnitList;
use App\Unitman\Business\Port\Unit\CanGetUnitReadModelById;
use App\Unitman\Business\Model\Unit\TipZanyatosti;
use App\Unitman\Business\Model\Unit\UnitProcess\UnitProcessState;
use App\Unitman\Business\Port\Unit\UmeetObnovlyatSostoyanieKonteyneraUnita;
use App\Unitman\Business\Port\Unit\UmeetObnovlyatStatistikuKonteinera;
use App\Unitman\Business\Port\Unit\UmeetOtbiratNeaktivnieUniti;
use App\Unitman\Business\ReadModel\Unit\ProjectListContainerStats;
use App\Unitman\Business\ReadModel\Unit\SpisokUnitovReadModel;
use Doctrine\DBAL\ArrayParameterType;
use Doctrine\DBAL\Connection;
use Symfony\Component\Serializer\Serializer;

final class SpisokUnitovRepository implements CanFindUnitDouble, CanGetUnitList, CanGetMyUnits, CanGetUnitReadModelById, CanFindUnitIdByContainerName, UmeetObnovlyatStatistikuKonteinera, UmeetOtbiratNeaktivnieUniti, UmeetObnovlyatSostoyanieKonteyneraUnita
{
    const TABLE = 'spisok_unitov';
    public function __construct(private Connection $connection, private Serializer $serializer)
    {
    }

    function init(): void
    {
        $table = self::TABLE;
        $this->connection->executeQuery("create table IF NOT EXISTS $table
            (
                id varchar(128) not null constraint spisok_unitov_pk primary key,
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

    public function findById(string $id): ?SpisokUnitovReadModel
    {
        $row = $this->findByIdRaw($id);
        if (empty($row)) {
            return null;
        }

        return $this->makeUnitByRow($row);
    }

    function getById(string $unitId): SpisokUnitovReadModel
    {
        $unit = $this->findById($unitId);

        if (empty($unit)) {
            throw new \Exception('unit.spisok_unitov.unit_not_found');
        }

        return $unit;
    }

    function insert(SpisokUnitovReadModel $spisokUnitovReadModel): void
    {
        $data = [
            'id' => $spisokUnitovReadModel->id,
            'payload' => $this->serializer->serialize($spisokUnitovReadModel, 'json')
        ];

        $this->connection->insert(self::TABLE, $data);
    }

    function update(SpisokUnitovReadModel $spisokUnitovReadModel): void
    {
        $data = [
            'payload' => $this->serializer->serialize($spisokUnitovReadModel, 'json')
        ];
        $this->connection->update(self::TABLE, $data, ['id' => $spisokUnitovReadModel->id]);
    }

    function delete(string $id): void
    {
        $this->connection->delete(self::TABLE,['id' => $id]);
    }

    function makeUnitByRow(array $row): SpisokUnitovReadModel
    {
        $unit = $this->serializer->deserialize($row['payload'], SpisokUnitovReadModel::class, 'json');
        return $unit;
    }

    public function isExistsDoubleByName(string $projectId, string $unitName): bool
    {
        $table = self::TABLE;
        $row = $this->connection->fetchAssociative("SELECT * FROM $table WHERE payload->>'projectId' = :projectId and payload->>'name' = :name", ['name' => $unitName, 'projectId' => $projectId]);
        return !empty($row);
    }

    /**
     * Занятость не хранится в payload: реестр захватов меняется вне потока событий,
     * поэтому признак подмешивается в ответ через LEFT JOIN по актуальным захватам.
     *
     * @param string[] $projectIds проекты, доступные пользователю
     * @return array<int, array<string, mixed>>
     */
    function getList(GetUnitList $query, string $currentUserId, array $projectIds): array
    {
        $table = self::TABLE;
        $params = ['limit' => $query->limit, 'offset' => $query->offset];
        $types = [];
        $whereArr = [];

        if ($query->filter->unitId) {
            $whereArr[] = "payload->>'id' = :id";
            $params['id'] = $query->filter->unitId;
        }

        if ($query->filter->onlyMine) {
            $whereArr[] = "payload->>'authorId' = :authorId";
            $params['authorId'] = $currentUserId;
        }

        if (!empty($query->filter->name)) {
            $whereArr[] = "payload->>'name' like :name";
            $params['name'] = '%'.$query->filter->name.'%';
        }

        if (!empty($query->filter->branch)) {
            $whereArr[] = "payload->>'branch' like :branch";
            $params['branch'] = '%'.$query->filter->branch.'%';
        }

        $this->dobavitUsloviePoProektam($whereArr, $params, $types, $projectIds, $query->filter->projectId);

        $this->dobavitUsloviePoSvyazyam($whereArr, $params, $types, $query->filter->unitIds);

        $where = "WHERE ".join(' AND ', $whereArr);

        $rows = $this->connection->fetchAllAssociative(
            $this->sqlSNanyatostyu("$table t", $where)." ORDER BY t.id desc LIMIT :limit OFFSET :offset",
            $params,
            $types
        );

        $result = [];
        foreach ($rows as $row) {
            $result[] = $this->makeUnitSNanyatostyu($row);
        }

        return $result;
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    function getMyUnits(GetMyUnits $command, string $authorId): array
    {
        $table = self::TABLE;
        $rows = $this->connection->fetchAllAssociative(
            $this->sqlSNanyatostyu("$table t", "where t.payload->>'authorId' = :authorId")
            ." ORDER BY t.id desc LIMIT :limit OFFSET :offset",
            ['limit' => $command->limit, 'offset' => $command->offset, 'authorId' => $authorId]
        );

        $result = [];
        foreach ($rows as $row) {
            $result[] = $this->makeUnitSNanyatostyu($row);
        }

        return $result;
    }

    /**
     * @return string[]
     * */
    function findIdsByBranch(string $projectId, string $vetka): array
    {
        $table = self::TABLE;
        $rows = $this->connection->fetchAllAssociative("SELECT id FROM $table where payload->>'projectId' = :projectId and payload->>'branch' = :branch",
            ['branch' => $vetka, 'projectId' => $projectId]);

        $result = [];
        foreach ($rows as $row) {
            $result[] = $row['id'];
        }

        return $result;
    }

    function findIdByNameAndProjectName(string $containerName): ?string
    {
        $segments = explode('.', $containerName);
        if (count($segments) !== 2) {
            return null;
        }
        $table = self::TABLE;

        $row = $this->connection->fetchAssociative("SELECT id FROM $table where payload->>'projectName' = :projectName and payload->>'name' = :name",
            ['name' => $segments[0], 'projectName' => $segments[1]]);

        return $row['id'] ?? null;
    }

    function obnovitStatistikuContainer(string $id, ProjectListContainerStats $containerStats): void
    {
        $table = self::TABLE;
        $json = $this->serializer->serialize($containerStats, 'json');
        $this->connection->executeQuery("update $table set payload = jsonb_set(payload, '{statistikaKonteinera}', :val) where id=:id", ['id' => $id, 'val' =>$json]);
    }

    /**
     * Атомарно обновляет только признаки расхождения состояния контейнера.
     * Оператор `||` мержит патч в payload, не затрагивая остальные ключи
     * (в том числе statistikaKonteinera), и не требует read-modify-write.
     */
    public function obnovitSostoyanieKonteynera(
        string $unitId,
        bool $mismatch,
        ?string $expectedState,
        ?string $actualState,
        ?int $detectedAt,
    ): bool {
        $table = self::TABLE;
        $patch = json_encode(
            [
                'containerStateMismatch' => $mismatch,
                'containerStateMismatchExpected' => $mismatch ? $expectedState : null,
                'containerStateMismatchActual' => $mismatch ? $actualState : null,
                'containerStateMismatchDetectedAt' => $mismatch ? $detectedAt : null,
            ],
            JSON_THROW_ON_ERROR | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES
        );

        $affected = $this->connection->executeStatement(
            "update $table set payload = payload || cast(:patch as jsonb) where id = :id",
            ['patch' => $patch, 'id' => $unitId]
        );

        return $affected > 0;
    }

    function otobratNeaktivnieUniti(int $unixtime): array
    {
        $table = self::TABLE;
        $rows = $this->connection->fetchAllAssociative("SELECT id FROM $table where payload->>'unixtimePoslednegoObnovleniyaUnita' is not null and payload->>'unixtimePoslednegoObnovleniyaUnita' < :unixtime",
            ['unixtime' => $unixtime]);
        $result = [];
        foreach ($rows as $row) {
            $result[] = $row['id'];
        }
        return $result;
    }

    function naitiDubliPoVetke(string $projectId, string $branch): array
    {
        $table = self::TABLE;
        $rows = $this->connection
            ->fetchAllAssociative("SELECT * FROM $table WHERE payload->>'projectId' = :projectId and payload->>'branch' = :branch",
                ['branch' => $branch, 'projectId' => $projectId]);
        $result = [];
        foreach ($rows as $row) {
            $result[] = $this->makeUnitByRow($row);
        }

        return $result;
    }

    /**
     * Ограничение выборки доступными пользователю проектами.
     *
     * Пустой $projectIds не должен превращаться в "без фильтра" — иначе пользователь
     * без доступа получил бы все юниты.
     *
     * @param string[] $whereArr
     * @param array<string, mixed> $params
     * @param array<string, mixed> $types
     * @param string[] $projectIds
     */
    private function dobavitUsloviePoProektam(
        array &$whereArr,
        array &$params,
        array &$types,
        array $projectIds,
        ?string $projectId
    ): void {
        if ($projectId !== null && $projectId !== '') {
            if (!in_array($projectId, $projectIds, true)) {
                $whereArr[] = '1 = 0';

                return;
            }

            $whereArr[] = "payload->>'projectId' = :projectId";
            $params['projectId'] = $projectId;

            return;
        }

        if ($projectIds === []) {
            $whereArr[] = '1 = 0';

            return;
        }

        $whereArr[] = "payload->>'projectId' in (:projectIds)";
        $params['projectIds'] = array_values($projectIds);
        $types['projectIds'] = ArrayParameterType::STRING;
    }

    /**
     * Юниты, переданные в фильтре, плюс связанные с ними через хелпер переменной
     * (связи учитываются в обе стороны).
     *
     * @param string[] $whereArr
     * @param array<string, mixed> $params
     * @param array<string, mixed> $types
     * @param string[] $unitIds
     */
    private function dobavitUsloviePoSvyazyam(
        array &$whereArr,
        array &$params,
        array &$types,
        array $unitIds
    ): void {
        if ($unitIds === []) {
            return;
        }

        $svyaziTable = SvyaziUnitovRepository::TABLE;

        $whereArr[] = "(
            payload->>'id' in (:unitIds)
            or exists (
                select 1 from $svyaziTable s
                where (s.unit_id in (:unitIds) and s.target_unit_id = payload->>'id')
                   or (s.target_unit_id in (:unitIds) and s.unit_id = payload->>'id')
            )
        )";
        $params['unitIds'] = array_values($unitIds);
        $types['unitIds'] = ArrayParameterType::STRING;
    }

    /**
     * Выборка списка юнитов вместе с актуальным захватом.
     *
     * Захваты с истёкшим TTL не учитываются: иначе «зависший» владелец навсегда
     * показывал бы юнит занятым, хотя любой другой участник уже может его захватить.
     *
     * @param string $from выражение FROM с алиасом t
     */
    private function sqlSNanyatostyu(string $from, string $where): string
    {
        $zanyatostTable = SqlZanyatostUnitaRepository::TABLE;

        return "SELECT t.payload, z.tip as zanyat_tip
            FROM $from
            LEFT JOIN $zanyatostTable z
                ON z.unit_id = t.payload->>'id' AND z.istekaet_v > now()
            $where";
    }

    /**
     * Строка выборки: payload юнита плюс подмешанная занятость.
     *
     * @param array<string, mixed> $row
     * @return array<string, mixed>
     */
    private function makeUnitSNanyatostyu(array $row): array
    {
        $payload = $row['payload'] ?? null;
        $unit = is_string($payload) ? json_decode($payload, true) : [];

        if (!is_array($unit)) {
            $unit = [];
        }

        $tip = $row['zanyat_tip'] ?? null;

        return $this->obogatitZanyatostyu($unit, is_string($tip) ? $tip : null);
    }

    /**
     * Добавляет в строку ответа признаки занятости.
     *
     * Приоритет причины повторяет ZanyatostUnitaReadModel: явный захват важнее
     * незавершённого процесса, потому что захват блокирует внешние действия.
     *
     * @param array<string, mixed> $unit
     * @return array<string, mixed>
     */
    private function obogatitZanyatostyu(array $unit, ?string $tipZanyatosti): array
    {
        $prichina = $this->opredelitPrichinuZanyatosti($unit, $tipZanyatosti);

        $unit['zanyat'] = $prichina !== null;
        $unit['prichinaZanyatosti'] = $prichina;

        return $unit;
    }

    /**
     * @param array<string, mixed> $unit
     */
    private function opredelitPrichinuZanyatosti(array $unit, ?string $tipZanyatosti): ?string
    {
        if ($tipZanyatosti !== null && $tipZanyatosti !== '') {
            return $tipZanyatosti;
        }

        $prozesi = $unit['prozesi'] ?? [];

        if (!is_array($prozesi)) {
            return null;
        }

        return $this->estNezavershenniyProzes($prozesi) ? TipZanyatosti::PROZES->value : null;
    }

    /**
     * @param array<int|string, mixed> $prozesi
     */
    private function estNezavershenniyProzes(array $prozesi): bool
    {
        $zavershennie = [
            UnitProcessState::SUCCESS->value,
            UnitProcessState::ERROR->value,
            UnitProcessState::CANCLED->value,
        ];

        foreach ($prozesi as $prozes) {
            if (!is_array($prozes)) {
                continue;
            }

            if (!in_array($prozes['state'] ?? null, $zavershennie, true)) {
                return true;
            }
        }

        return false;
    }

    /**
     * @param string $id
     * @return array|false|mixed[]
     * @throws \Doctrine\DBAL\Exception
     */
    function findByIdRaw(string $id): array|false
    {
        $table = self::TABLE;
        $row = $this->connection->fetchAssociative("SELECT * FROM $table WHERE id = :id", ['id' => $id]);
        return $row;
    }
}
