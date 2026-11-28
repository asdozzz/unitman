<?php

declare(strict_types=1);

namespace App\Skill\Business\ReadModel;

use App\Skill\Business\Model\NavykOpenCode;

/**
 * Полная карточка навыка: содержимое, хеш и признак права редактирования.
 */
final class NavykDetailReadModel
{
    /**
     * @param list<string> $projectIds
     */
    public function __construct(
        public readonly string $id,
        public readonly string $slug,
        public readonly string $name,
        public readonly string $description,
        public readonly string $content,
        public readonly string $contentHash,
        public readonly bool $isGlobal,
        public readonly array $projectIds,
        public readonly int $version,
        public readonly string $createdBy,
        public readonly string $updatedBy,
        public readonly int $createdAt,
        public readonly int $updatedAt,
        public readonly bool $archived,
        public readonly bool $canEdit,
    ) {
    }

    public static function izModela(NavykOpenCode $navyk, bool $canEdit): self
    {
        return new self(
            $navyk->getId(),
            $navyk->getSlug(),
            $navyk->getName(),
            $navyk->getDescription(),
            $navyk->getContent(),
            $navyk->getContentHash(),
            $navyk->isGlobal(),
            $navyk->getProjectIds(),
            $navyk->getVersion(),
            $navyk->getCreatedBy(),
            $navyk->getUpdatedBy(),
            $navyk->getCreatedAt(),
            $navyk->getUpdatedAt(),
            $navyk->isArchived(),
            $canEdit,
        );
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return [
            'id' => $this->id,
            'slug' => $this->slug,
            'name' => $this->name,
            'description' => $this->description,
            'content' => $this->content,
            'contentHash' => $this->contentHash,
            'isGlobal' => $this->isGlobal,
            'projectIds' => $this->projectIds,
            'version' => $this->version,
            'createdBy' => $this->createdBy,
            'updatedBy' => $this->updatedBy,
            'createdAt' => $this->createdAt,
            'updatedAt' => $this->updatedAt,
            'archived' => $this->archived,
            'canEdit' => $this->canEdit,
        ];
    }
}
