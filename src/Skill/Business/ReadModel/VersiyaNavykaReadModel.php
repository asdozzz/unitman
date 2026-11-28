<?php

declare(strict_types=1);

namespace App\Skill\Business\ReadModel;

use App\Skill\Business\Model\VersiyaNavyka;

/**
 * Снимок версии навыка.
 */
final class VersiyaNavykaReadModel
{
    public function __construct(
        public readonly int $version,
        public readonly string $name,
        public readonly string $description,
        public readonly string $content,
        public readonly string $contentHash,
        public readonly string $createdBy,
        public readonly int $createdAt,
    ) {
    }

    public static function izModela(VersiyaNavyka $versiya): self
    {
        return new self(
            $versiya->getVersion(),
            $versiya->getName(),
            $versiya->getDescription(),
            $versiya->getContent(),
            $versiya->getContentHash(),
            $versiya->getCreatedBy(),
            $versiya->getCreatedAt(),
        );
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return [
            'version' => $this->version,
            'name' => $this->name,
            'description' => $this->description,
            'content' => $this->content,
            'contentHash' => $this->contentHash,
            'createdBy' => $this->createdBy,
            'createdAt' => $this->createdAt,
        ];
    }
}
