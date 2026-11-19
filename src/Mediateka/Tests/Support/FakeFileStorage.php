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
     * @var array<int, array{key: string, size: int, contentType: string, ttl: int}>
     */
    public array $presignedPutCalls = [];

    public function save(string $key, $stream, int $size, string $contentType): void
    {
        $this->saved[$key] = ['size' => $size, 'contentType' => $contentType];
    }

    public function delete(string $key): void
    {
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
}
