<?php

declare(strict_types=1);

namespace App\Unitman\Infra\Repository\Unit;

use App\Unitman\Business\Model\Unit\TipZanyatosti;
use App\Unitman\Business\Model\Unit\ZanyatostUnita;
use App\Unitman\Business\Port\Unit\ZanyatostUnitaRepository;
use Doctrine\DBAL\Connection;

final class SqlZanyatostUnitaRepository implements ZanyatostUnitaRepository
{
    const TABLE = 'unit_zanyatost';

    public function __construct(private Connection $connection)
    {
    }

    /**
     * Атомарный захват одним запросом.
     *
     * Почему не `select ... for update` + `insert`: между проверкой и записью
     * появилось бы окно, в котором второй ход OpenCode увидел бы юнит
     * свободным. Здесь конфликт по первичному ключу разрешает сама СУБД:
     * `on conflict do update ... where istekaet_v < now()` перезаписывает
     * строку только если предыдущий захват истёк. `rowCount() === 0` означает,
     * что условие `where` не выполнилось, то есть юнит держит кто-то другой
     * (или тот же владелец, но с неистёкшим TTL) — это корректный отказ.
     *
     * Сравнение с `now()` БД, а не с переданным временем: часы воркеров могут
     * расходиться, а TTL должен считаться по единым часам.
     */
    public function zahvatit(
        string $unitId,
        string $vladelec,
        TipZanyatosti $tip,
        \DateTimeImmutable $istekaetV,
    ): bool {
        $table = self::TABLE;

        $affected = $this->connection->executeStatement(
            "insert into $table (unit_id, vladelec, tip, zahvacheno_v, istekaet_v)
             values (:unitId, :vladelec, :tip, now(), :istekaetV)
             on conflict (unit_id) do update
                 set vladelec = excluded.vladelec,
                     tip = excluded.tip,
                     zahvacheno_v = excluded.zahvacheno_v,
                     istekaet_v = excluded.istekaet_v
                 where $table.istekaet_v < now()",
            [
                'unitId' => $unitId,
                'vladelec' => $vladelec,
                'tip' => $tip->value,
                'istekaetV' => $istekaetV->format('Y-m-d H:i:sP'),
            ]
        );

        return $affected > 0;
    }

    /**
     * Освобождение с проверкой владельца: чужой захват снять нельзя.
     *
     * Идемпотентно — отсутствие строки просто означает, что освобождать нечего
     * (например, TTL истёк и строку уже вычистил фоновый обход).
     */
    public function osvobodit(string $unitId, string $vladelec): void
    {
        $table = self::TABLE;

        $this->connection->executeStatement(
            "delete from $table where unit_id = :unitId and vladelec = :vladelec",
            ['unitId' => $unitId, 'vladelec' => $vladelec]
        );
    }

    public function poluchitAktivnuyu(string $unitId): ?ZanyatostUnita
    {
        $table = self::TABLE;

        $row = $this->connection->fetchAssociative(
            "select * from $table where unit_id = :unitId and istekaet_v > now()",
            ['unitId' => $unitId]
        );

        if ($row === false) {
            return null;
        }

        return new ZanyatostUnita(
            (string)$row['unit_id'],
            (string)$row['vladelec'],
            TipZanyatosti::from((string)$row['tip']),
            new \DateTimeImmutable((string)$row['zahvacheno_v']),
            new \DateTimeImmutable((string)$row['istekaet_v']),
        );
    }

    public function udalitProsrochennie(\DateTimeImmutable $now): int
    {
        $table = self::TABLE;

        return (int)$this->connection->executeStatement(
            "delete from $table where istekaet_v < :now",
            ['now' => $now->format('Y-m-d H:i:sP')]
        );
    }
}
