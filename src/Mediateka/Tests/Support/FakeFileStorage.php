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
     * @var string[]
     */
    public array $deleted = [];

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
        return isset($this->saved[$key]);
    }

    public function presignedGetUrl(string $key, ?string $downloadName = null, ?int $ttl = null): string
    {
        return 'https://storage.test/' . ltrim($key, '/');
    }
}
