<?php

declare(strict_types=1);

namespace App\Mediateka\Tests\Support;

use App\Mediateka\Business\Port\FileStorage;

/**
 * Хранилище в памяти: фиксирует сохранённые и удалённые ключи.
 */
final class FakeFileStorage implements FileStorage
{
    /**
     * @var array<string, array{size: int, contentType: string}>
     */
    public array $saved = [];

    /**
     * Объекты, якобы загруженные напрямую в MinIO (для finalize).
     *
     * @var array<string, array{size: int, contentType: ?string}>
     */
    public array $statObjects = [];

    /**
     * @var string[]
     */
    public array $deleted = [];

    /**
     * Ключи, удаление которых должно закончиться ошибкой.
     *
     * @var array<string, true>
     */
    public array $failDelete = [];

    /**
     * Содержимое для скачивания по ключу объекта.
     *
     * @var array<string, string>
     */
    public array $downloadContent = [];

    /**
     * Последний запрос на скачивание: ключ и лимит байт.
     *
     * @var array{key: string, maxBytes: int}|null
     */
    public ?array $lastDownload = null;

    /**
     * @var array<int, array{key: string, size: int, contentType: string, ttl: int}>
     */
    public array $presignedPutCalls = [];

    public function save(string $key, $stream, int $size, string $contentType): void
    {
        $this->saved[$key] = ['size' => $size, 'contentType' => $contentType];
    }

    public function delete(string $key): void
    {
        if (isset($this->failDelete[$key])) {
            throw new \RuntimeException('storage delete failed');
        }

        $this->deleted[] = $key;
    }

    public function exists(string $key): bool
    {
        return isset($this->saved[$key]) || isset($this->statObjects[$key]);
    }

    public function presignedGetUrl(string $key, ?string $downloadName = null, ?int $ttl = null): string
    {
        return 'https://storage.test/' . ltrim($key, '/');
    }

    public function presignedPutUrl(string $key, int $size, string $contentType, int $ttl): string
    {
        $this->presignedPutCalls[] = [
            'key' => $key,
            'size' => $size,
            'contentType' => $contentType,
            'ttl' => $ttl,
        ];

        return 'https://storage.test/' . ltrim($key, '/') . '?X-Amz-Signature=put';
    }

    /**
     * @return array{size: int, contentType: ?string}|null
     */
    public function stat(string $key): ?array
    {
        return $this->statObjects[$key] ?? null;
    }

    public function downloadToTemp(string $key, int $maxBytes = 0): string
    {
        $this->lastDownload = ['key' => $key, 'maxBytes' => $maxBytes];

        $content = $this->downloadContent[$key] ?? '';

        if ($maxBytes > 0 && strlen($content) > $maxBytes) {
            throw new \RuntimeException('mediateka.file.too_large');
        }

        $path = tempnam(sys_get_temp_dir(), 'fake_storage_');
        if ($path === false) {
            throw new \RuntimeException('cannot create temp file');
        }

        file_put_contents($path, $content);

        return $path;
    }
}
