<?php

declare(strict_types=1);

namespace App\Mediateka\Business\Port;

/**
 * Порт хранилища «сырых» файлов (S3-совместимое хранилище).
 * Модуль Mediateka только проксирует вызовы, вся логика принадлежности — в реестре.
 */
interface FileStorage
{
    /**
     * Сохраняет содержимое по ключу.
     *
     * @param resource $stream поток с содержимым файла
     */
    public function save(string $key, $stream, int $size, string $contentType): void;

    /**
     * Удаляет объект по ключу. Удаление отсутствующего объекта не является ошибкой.
     */
    public function delete(string $key): void;

    public function exists(string $key): bool;

    /**
     * Временная ссылка на скачивание, доступная извне.
     */
    public function presignedGetUrl(string $key, ?string $downloadName = null, ?int $ttl = null): string;
}
