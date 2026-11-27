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

    /**
     * Временная ссылка на прямую загрузку объекта (PUT).
     *
     * @param int $size ожидаемый размер объекта в байтах
     * @param string $contentType MIME-тип загружаемого объекта
     * @param int $ttl время жизни ссылки в секундах
     */
    public function presignedPutUrl(string $key, int $size, string $contentType, int $ttl): string;

    /**
     * Метаданные объекта (HEAD). null — объекта нет (HTTP 404).
     *
     * @return array{size: int, contentType: ?string}|null
     */
    public function stat(string $key): ?array;

    /**
     * Скачивает объект во временный файл для проверки содержимого
     * (сигнатуры, кодировки). Возвращает путь; вызывающий удаляет файл.
     *
     * @param int $maxBytes 0 — без ограничения; иначе при превышении исключение
     */
    public function downloadToTemp(string $key, int $maxBytes = 0): string;
}
