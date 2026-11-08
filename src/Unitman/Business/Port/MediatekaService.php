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

    /**
     * Владельцы файлов:
     * - `unitId` не null — файл юнита;
     * - `projectId` не null — архив каталога проекта;
     * - оба null — черновик;
     * - файла нет в медиатеке — ключа в результате нет.
     *
     * @param string[] $fileIds
     *
     * @return array<string, array{unitId: ?string, projectId: ?string}>
     */
    public function poluchitVladelcevFaylov(array $fileIds): array;

    /**
     * Данные проектного архива для передачи в Runner: имя архива и временная ссылка.
     *
     * @return array{name: string, url: string}|null
     */
    public function poluchitFaylProektaDlyaVoluma(string $fileId, string $projectId, ?int $ttl = null): ?array;

    /**
     * Метаданные проектного архива для каталога типов томов.
     *
     * @return array{name: string, size: int, createdAt: int}|null
     */
    public function poluchitFaylProekta(string $fileId, string $projectId): ?array;

    /**
     * Удаляет проектный архив из медиатеки.
     */
    public function udaliFaylProekta(string $fileId, string $projectId): void;
}
