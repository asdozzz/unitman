<?php

declare(strict_types=1);

namespace App\Unitman\Business\Model\VolumeType;

use DomainException;

/**
 * Тип тома `unitman_volumes` в каталоге проекта.
 *
 * `code` — публичный ключ типа; при создании юнита он сверяется с `dir` тома
 * из конфига, поэтому `code` не меняется после появления связанных архивов.
 */
final class VolumeType
{
    public const CODE_MAX_LENGTH = 100;
    public const NAME_MAX_LENGTH = 255;

    public readonly string $code;

    public readonly string $name;

    public function __construct(
        public readonly string $id,
        public readonly string $projectId,
        string $code,
        string $name,
        public readonly int $createdAt
    ) {
        if (trim($this->id) === '') {
            throw new DomainException('unit.volume_type.id_is_empty');
        }
        if (trim($this->projectId) === '') {
            throw new DomainException('unit.volume_type.project_id_is_empty');
        }

        $this->code = trim($code);
        if ($this->code === '') {
            throw new DomainException('unit.volume_type.code_is_empty');
        }
        if (mb_strlen($this->code) > self::CODE_MAX_LENGTH) {
            throw new DomainException('unit.volume_type.code_is_too_long');
        }

        $this->name = trim($name);
        if ($this->name === '') {
            throw new DomainException('unit.volume_type.name_is_empty');
        }
        if (mb_strlen($this->name) > self::NAME_MAX_LENGTH) {
            throw new DomainException('unit.volume_type.name_is_too_long');
        }
    }

    /**
     * @param array<string, mixed> $row
     */
    public static function fromRow(array $row): self
    {
        return new self(
            (string) $row['id'],
            (string) $row['project_id'],
            (string) $row['code'],
            (string) $row['name'],
            (int) $row['created_at']
        );
    }

    public function esliKodSovpadaetSDir(string $dir): bool
    {
        return $this->code === trim($dir);
    }
}
