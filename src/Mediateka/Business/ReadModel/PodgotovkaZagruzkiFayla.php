<?php

declare(strict_types=1);

namespace App\Mediateka\Business\ReadModel;

/**
 * Данные, необходимые клиенту для прямой загрузки объекта в MinIO.
 */
final class PodgotovkaZagruzkiFayla
{
    /**
     * @param array<string, string> $headers
     */
    public function __construct(
        public readonly string $fileId,
        public readonly string $storageKey,
        public readonly string $uploadUrl,
        public readonly array $headers,
        public readonly string $uploadToken,
        public readonly int $expiresAt
    ) {
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return [
            'fileId' => $this->fileId,
            'storageKey' => $this->storageKey,
            'uploadUrl' => $this->uploadUrl,
            'method' => 'PUT',
            'headers' => $this->headers,
            'uploadToken' => $this->uploadToken,
            'expiresAt' => $this->expiresAt,
        ];
    }
}
