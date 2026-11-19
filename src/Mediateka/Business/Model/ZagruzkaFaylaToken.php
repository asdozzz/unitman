<?php

declare(strict_types=1);

namespace App\Mediateka\Business\Model;

use InvalidArgumentException;

/**
 * Полезная нагрузка подписанного токена прямой загрузки.
 *
 * Токен stateless: метаданные будущего файла передаются между запросами
 * presign и finalize в самом токене, поэтому отдельная таблица и миграция
 * не нужны. Целостность обеспечивает HMAC-SHA256 в {@see \App\Mediateka\Business\Utils\UploadTokenCodec}.
 */
final class ZagruzkaFaylaToken
{
    public function __construct(
        public readonly string $fileId,
        public readonly ?string $unitId,
        public readonly ?string $projectId,
        public readonly string $name,
        public readonly int $size,
        public readonly string $contentType,
        public readonly string $storageKey,
        public readonly ?string $uploadedBy,
        public readonly int $expiresAt
    ) {
        if (trim($this->fileId) === '') {
            throw new InvalidArgumentException('token.file_id_is_empty');
        }

        if ($this->unitId !== null && trim($this->unitId) === '') {
            throw new InvalidArgumentException('token.unit_id_is_empty');
        }

        if ($this->projectId !== null && trim($this->projectId) === '') {
            throw new InvalidArgumentException('token.project_id_is_empty');
        }

        if ($this->unitId !== null && $this->projectId !== null) {
            throw new InvalidArgumentException('token.owner_is_ambiguous');
        }

        if (trim($this->name) === '') {
            throw new InvalidArgumentException('token.name_is_empty');
        }

        if ($this->size <= 0) {
            throw new InvalidArgumentException('token.size_is_invalid');
        }

        if (trim($this->contentType) === '') {
            throw new InvalidArgumentException('token.content_type_is_empty');
        }

        if (trim($this->storageKey) === '') {
            throw new InvalidArgumentException('token.storage_key_is_empty');
        }

        if ($this->expiresAt <= 0) {
            throw new InvalidArgumentException('token.expires_at_is_invalid');
        }

        if ($this->uploadedBy !== null && trim($this->uploadedBy) === '') {
            throw new InvalidArgumentException('token.uploaded_by_is_empty');
        }
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return [
            'fileId' => $this->fileId,
            'unitId' => $this->unitId,
            'projectId' => $this->projectId,
            'name' => $this->name,
            'size' => $this->size,
            'contentType' => $this->contentType,
            'storageKey' => $this->storageKey,
            'uploadedBy' => $this->uploadedBy,
            'expiresAt' => $this->expiresAt,
        ];
    }

    /**
     * @param array<array-key, mixed> $data
     */
    public static function fromArray(array $data): self
    {
        return new self(
            self::stroka($data, 'fileId'),
            self::strokaIliNull($data, 'unitId'),
            self::strokaIliNull($data, 'projectId'),
            self::stroka($data, 'name'),
            self::celoe($data, 'size'),
            self::stroka($data, 'contentType'),
            self::stroka($data, 'storageKey'),
            self::strokaIliNull($data, 'uploadedBy'),
            self::celoe($data, 'expiresAt')
        );
    }

    /**
     * @param array<array-key, mixed> $data
     */
    private static function stroka(array $data, string $key): string
    {
        $value = $data[$key] ?? null;
        if (!is_string($value) || $value === '') {
            throw new InvalidArgumentException('token.' . $key . '_is_invalid');
        }

        return $value;
    }

    /**
     * @param array<array-key, mixed> $data
     */
    private static function strokaIliNull(array $data, string $key): ?string
    {
        $value = $data[$key] ?? null;
        if ($value === null) {
            return null;
        }

        if (!is_string($value)) {
            throw new InvalidArgumentException('token.' . $key . '_is_invalid');
        }

        return $value;
    }

    /**
     * @param array<array-key, mixed> $data
     */
    private static function celoe(array $data, string $key): int
    {
        $value = $data[$key] ?? null;
        if (!is_int($value)) {
            throw new InvalidArgumentException('token.' . $key . '_is_invalid');
        }

        return $value;
    }
}
