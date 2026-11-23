<?php

declare(strict_types=1);

namespace App\Unitman\Infra\Repository\Unit;

use App\Unitman\Business\Model\SobitieIzHranilisha\TipSobitiya;
use App\Unitman\Business\Model\Unit\OtlozhennoeDeistvie;
use App\Unitman\Business\Port\Unit\OtlozhennieDeistviyaRepository;
use App\Unitman\Infra\Repository\Project\SqlProjectListRepository;
use Doctrine\DBAL\Connection;

final class SqlOtlozhennieDeistviyaRepository implements OtlozhennieDeistviyaRepository
{
    const TABLE = 'unit_otlozhennoe_deistvie';
    const DATETIME_FORMAT = 'Y-m-d H:i:sP';

    public function __construct(private Connection $connection)
    {
    }

    /**
     * upsert по (`unit_id`, `tip_sobitiya`).
     *
     * `do update`, а не `do nothing`: повторный вебхук по той же ветке несёт
     * более свежий `payload` и `unixtime`, и примениться должно последнее
     * событие, а не первое. Счётчик `attempts` и `next_attempt_at` при этом
     * сбрасываются — новая попытка заслуживает свежего бюджета ретраев.
     */
    public function otlozhit(
        string $unitId,
        string $projectId,
        TipSobitiya $tipSobitiya,
        array $payload,
        \DateTimeImmutable $now,
        string $status = OtlozhennoeDeistvie::STATUS_READY,
    ): void {
        $table = self::TABLE;

        $this->connection->executeStatement(
            "insert into $table (unit_id, project_id, tip_sobitiya, payload, created_at, attempts, next_attempt_at, status)
             values (:unitId, :projectId, :tip, cast(:payload as jsonb), :now, 0, :now, :status)
             on conflict (unit_id, tip_sobitiya) do update
                 set project_id = excluded.project_id,
                     payload = excluded.payload,
                     created_at = excluded.created_at,
                     attempts = 0,
                     next_attempt_at = excluded.next_attempt_at,
                     status = excluded.status,
                     last_error = null",
            [
                'unitId' => $unitId,
                'projectId' => $projectId,
                'tip' => $tipSobitiya->value,
                'payload' => json_encode($payload, JSON_THROW_ON_ERROR | JSON_UNESCAPED_UNICODE),
                'now' => $now->format(self::DATETIME_FORMAT),
                'status' => $status,
            ]
        );
    }

    public function poluchitGotovieDlyaUnita(string $unitId, \DateTimeImmutable $now): array
    {
        $table = self::TABLE;

        $rows = $this->connection->fetchAllAssociative(
            "select * from $table
             where unit_id = :unitId
               and status = :status
               and next_attempt_at <= :now
             order by id asc",
            [
                'unitId' => $unitId,
                'status' => OtlozhennoeDeistvie::STATUS_READY,
                'now' => $now->format(self::DATETIME_FORMAT),
            ]
        );

        return $this->makeModels($rows);
    }

    /**
     * Пачка для фонового обхода.
     *
     * `for update skip locked` — тот же приём, что в очереди юнитов: несколько
     * воркеров разбирают разные строки, не вставая в ожидание друг друга.
     */
    public function poluchitGotovie(\DateTimeImmutable $now, int $limit = 50): array
    {
        $table = self::TABLE;

        $rows = $this->connection->fetchAllAssociative(
            "select * from $table
             where status = :status
               and next_attempt_at <= :now
             order by next_attempt_at asc
             limit $limit
             for update skip locked",
            [
                'status' => OtlozhennoeDeistvie::STATUS_READY,
                'now' => $now->format(self::DATETIME_FORMAT),
            ]
        );

        return $this->makeModels($rows);
    }

    public function udovletvorit(string $unitId, TipSobitiya $tipSobitiya): void
    {
        $this->connection->delete(
            self::TABLE,
            ['unit_id' => $unitId, 'tip_sobitiya' => $tipSobitiya->value]
        );
    }

    public function otmetitPopytku(
        string $unitId,
        TipSobitiya $tipSobitiya,
        \DateTimeImmutable $nextAttemptAt,
        ?string $lastError,
    ): void {
        $table = self::TABLE;

        $this->connection->executeStatement(
            "update $table
             set attempts = attempts + 1,
                 next_attempt_at = :nextAttemptAt,
                 last_error = :lastError
             where unit_id = :unitId and tip_sobitiya = :tip",
            [
                'unitId' => $unitId,
                'tip' => $tipSobitiya->value,
                'nextAttemptAt' => $nextAttemptAt->format(self::DATETIME_FORMAT),
                'lastError' => $lastError,
            ]
        );
    }

    public function udalitPoUnituITipu(string $unitId, TipSobitiya $tipSobitiya): void
    {
        $this->udovletvorit($unitId, $tipSobitiya);
    }

    public function estNamerenieUdaleniya(string $unitId): bool
    {
        $table = self::TABLE;

        $result = $this->connection->fetchOne(
            "select 1 from $table
             where unit_id = :unitId
               and tip_sobitiya in (:udalenie, :slianie)
             limit 1",
            [
                'unitId' => $unitId,
                'udalenie' => TipSobitiya::VETKA_UDALENA->value,
                'slianie' => TipSobitiya::VETKA_SMERZHENA->value,
            ]
        );

        return $result !== false;
    }

    public function uderzhat(string $unitId, TipSobitiya $tipSobitiya): void
    {
        $this->connection->update(
            self::TABLE,
            ['status' => OtlozhennoeDeistvie::STATUS_HELD_AUTO_OBNOVLENIE],
            ['unit_id' => $unitId, 'tip_sobitiya' => $tipSobitiya->value]
        );
    }

    public function osvoboditUderzhannie(string $projectId, \DateTimeImmutable $now): int
    {
        $table = self::TABLE;

        return (int)$this->connection->executeStatement(
            "update $table
             set status = :ready,
                 next_attempt_at = :now
             where project_id = :projectId
               and status = :held
               and tip_sobitiya = :tip",
            [
                'ready' => OtlozhennoeDeistvie::STATUS_READY,
                'now' => $now->format(self::DATETIME_FORMAT),
                'projectId' => $projectId,
                'held' => OtlozhennoeDeistvie::STATUS_HELD_AUTO_OBNOVLENIE,
                'tip' => TipSobitiya::KOD_OBNOVLEN->value,
            ]
        );
    }

    /**
     * @return string[]
     */
    public function poluchitUnitIdsSPoGotovimi(string $projectId, \DateTimeImmutable $now): array
    {
        $table = self::TABLE;

        $rows = $this->connection->fetchAllAssociative(
            "select distinct unit_id from $table
             where project_id = :projectId
               and status = :ready
               and next_attempt_at <= :now",
            [
                'projectId' => $projectId,
                'ready' => OtlozhennoeDeistvie::STATUS_READY,
                'now' => $now->format(self::DATETIME_FORMAT),
            ]
        );

        return array_map(
            static fn(array $row): string => (string)$row['unit_id'],
            $rows
        );
    }

    public function osvoboditUderzhannieDlyaVsehProektov(\DateTimeImmutable $now): int
    {
        $table = self::TABLE;
        $projectTable = SqlProjectListRepository::TABLE;

        return (int)$this->connection->executeStatement(
            "update $table d
             set status = :ready,
                 next_attempt_at = :now
             from $projectTable p
             where d.project_id = p.id
               and d.status = :held
               and d.tip_sobitiya = :tip
               and coalesce(
                   (p.payload->'nastroikiHukaProekta'->>'avtoobnovlenie')::boolean,
                   true
               ) = true",
            [
                'ready' => OtlozhennoeDeistvie::STATUS_READY,
                'now' => $now->format(self::DATETIME_FORMAT),
                'held' => OtlozhennoeDeistvie::STATUS_HELD_AUTO_OBNOVLENIE,
                'tip' => TipSobitiya::KOD_OBNOVLEN->value,
            ]
        );
    }

    public function udalitUstarevshie(\DateTimeImmutable $before): int
    {
        $table = self::TABLE;

        return (int)$this->connection->executeStatement(
            "delete from $table where created_at < :before",
            ['before' => $before->format(self::DATETIME_FORMAT)]
        );
    }

    /**
     * @param array<int, array<string, mixed>> $rows
     * @return OtlozhennoeDeistvie[]
     */
    private function makeModels(array $rows): array
    {
        $models = [];
        foreach ($rows as $row) {
            $models[] = $this->makeModel($row);
        }

        return $models;
    }

    private function makeModel(array $row): OtlozhennoeDeistvie
    {
        return new OtlozhennoeDeistvie(
            (string)$row['unit_id'],
            (string)$row['project_id'],
            TipSobitiya::from((string)$row['tip_sobitiya']),
            $this->decodePayload($row['payload']),
            (int)$row['attempts'],
            new \DateTimeImmutable((string)$row['next_attempt_at']),
            (int)$row['id'],
            $row['last_error'] === null ? null : (string)$row['last_error'],
            (string)($row['status'] ?? OtlozhennoeDeistvie::STATUS_READY),
        );
    }

    /**
     * @return array<string, mixed>
     */
    private function decodePayload(mixed $payload): array
    {
        if (is_array($payload)) {
            return $payload;
        }

        if (!is_string($payload) || $payload === '') {
            return [];
        }

        $decoded = json_decode($payload, true);

        return is_array($decoded) ? $decoded : [];
    }
}
