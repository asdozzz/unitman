<?php

namespace App\Initialization\Business\Model;

final class InitializationRecord
{
    public function __construct(
        public readonly string $id,
        public string $prop,
        public ?string $value,
        public ?int $init
    ){
    }

    public static function fromRow(array $row): self
    {
        return new self(
            $row['id'] ?? $row['prop'],
            $row['prop'],
            $row['value'] ?? null,
            isset($row['init']) ? (int)$row['init'] : null
        );
    }
}
