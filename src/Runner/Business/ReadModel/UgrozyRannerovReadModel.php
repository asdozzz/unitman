<?php

declare(strict_types=1);

namespace App\Runner\Business\ReadModel;

/**
 * Краткая сводка угроз по раннерам для шапки сайта.
 *
 * Доступна всем авторизованным пользователям, поэтому намеренно не содержит
 * деталей ресурсов, hostname и причин блокировки.
 */
final class UgrozyRannerovReadModel implements \JsonSerializable
{
    /**
     * @param list<array{id: string, level: string, blocked: bool, healthFresh: bool}> $runners
     */
    public function __construct(
        public bool $hasThreats,
        public string $worstLevel,
        public int $count,
        public array $runners,
        // Общее число записей реестра, включая неактивные и без публичного
        // адреса. Нужно шапке, чтобы отличить «всё в норме» от «раннеров нет».
        public int $registeredCount,
    ) {
    }

    public function jsonSerialize(): mixed
    {
        return get_object_vars($this);
    }
}
