<?php

declare(strict_types=1);

namespace App\Skill\Business\ReadModel;

use App\Skill\Business\Model\NavykOpenCode;

/**
 * Краткая карточка навыка для списка.
 */
final class NavykKratkoReadModel
{
    /**
     * @param list<string> $projectIds
     */
    public function __construct(
        public readonly string $id,
        public readonly string $slug,
        public readonly string $name,
        public readonly string $description,
        public readonly bool $isGlobal,
        public readonly array $projectIds,
        public readonly int $version,
        public readonly int $updatedAt,
        public readonly bool $archived,
    ) {
    }

    public static function izModela(NavykOpenCode $navyk): self
    {
        return new self(
            $navyk->getId(),
            $navyk->getSlug(),
            $navyk->getName(),
            $navyk->getDescription(),
            $navyk->isGlobal(),
            $navyk->getProjectIds(),
            $navyk->getVersion(),
            $navyk->getUpdatedAt(),
            $navyk->isArchived(),
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
            'isGlobal' => $this->isGlobal,
            'projectIds' => $this->projectIds,
            'version' => $this->version,
            'updatedAt' => $this->updatedAt,
            'archived' => $this->archived,
        ];
    }
}
