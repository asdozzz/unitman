<?php

declare(strict_types=1);

namespace App\Unitman\Business\Port;

/**
 * Доступ Unitman к медиатеке файлов юнита.
 */
interface MediatekaService
{
    /**
     * Проверяет, что все файлы существуют и прикреплены к указанному юниту.
     *
     * @param string[] $fileIds
     */
    public function vseFaylyPrinadlezhatUnitu(array $fileIds, string $unitId): bool;

    /**
     * Проверяет, что файлы можно прикрепить к новому юниту (они загружены как черновики).
     *
     * @param string[] $fileIds
     */
    public function vseFaylyDostupnyDlyaPrivyazki(array $fileIds): bool;

    /**
     * Прикрепляет ранее загруженные файлы к юниту.
     *
     * @param string[] $fileIds
     */
    public function prikrepitFaylyKUnitu(array $fileIds, string $unitId): void;

    /**
     * Данные файла для передачи в Runner: имя архива и временная ссылка на скачивание.
     *
     * @return array{name: string, url: string}|null
     */
    public function poluchitFaylDlyaVoluma(string $fileId, string $unitId, ?int $ttl = null): ?array;

    /**
     * Удаляет все файлы, прикреплённые к юниту.
     */
    public function udaliFayliUnita(string $unitId): void;
}
