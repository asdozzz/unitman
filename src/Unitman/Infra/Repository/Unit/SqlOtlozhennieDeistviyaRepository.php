<?php

declare(strict_types=1);

namespace App\Unitman\Infra\Repository\Unit;

use App\Unitman\Business\Model\SobitieIzHranilisha\TipSobitiya;
use App\Unitman\Business\Model\Unit\OtlozhennoeDeistvie;
use App\Unitman\Business\Port\Unit\OtlozhennieDeistviyaRepository;
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
    ): void {
        $table = self::TABLE;

        $this->connection->executeStatement(
            "insert into $table (unit_id, project_id, tip_sobitiya, payload, created_at, attempts, next_attempt_at)
             values (:unitId, :projectId, :tip, cast(:payload as jsonb), :now, 0, :now)
             on conflict (unit_id, tip_sobitiya) do update
                 set project_id = excluded.project_id,
                     payload = excluded.payload,
                     created_at = excluded.created_at,
                     attempts = 0,
                     next_attempt_at = excluded.next_attempt_at,
                     last_error = null",
            [
                'unitId' => $unitId,
                'projectId' => $projectId,
                'tip' => $tipSobitiya->value,
                'payload' => json_encode($payload, JSON_THROW_ON_ERROR | JSON_UNESCAPED_UNICODE),
                'now' => $now->format(self::DATETIME_FORMAT),
            ]
        );
    }

    public function poluchitGotovieDlyaUnita(string $unitId, \DateTimeImmutable $now): array
    {
        $table = self::TABLE;

        $rows = $this->connection->fetchAllAssociative(
            "select * from $table
             where unit_id = :unitId and next_attempt_at <= :now
             order by id asc",
            ['unitId' => $unitId, 'now' => $now->format(self::DATETIME_FORMAT)]
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
             where next_attempt_at <= :now
             order by next_attempt_at asc
             limit $limit
             for update skip locked",
            ['now' => $now->format(self::DATETIME_FORMAT)]
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
