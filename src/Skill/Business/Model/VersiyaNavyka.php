<?php

declare(strict_types=1);

namespace App\Skill\Business\Model;

/**
 * Снимок версии навыка OpenCode (`opencode_skill_version`).
 *
 * Версия создаётся только при изменении содержимого и неизменяема.
 */
final class VersiyaNavyka
{
    private function __construct(
        private readonly string $skillId,
        private readonly int $version,
        private readonly string $name,
        private readonly string $description,
        private readonly string $content,
        private readonly string $contentHash,
        private readonly string $createdBy,
        private readonly int $createdAt,
    ) {
    }

    public static function restore(
        string $skillId,
        int $version,
        string $name,
        string $description,
        string $content,
        string $contentHash,
        string $createdBy,
        int $createdAt,
    ): self {
        return new self(
            $skillId,
            $version,
            $name,
            $description,
            $content,
            $contentHash,
            $createdBy,
            $createdAt,
        );
    }

    public function getSkillId(): string
    {
        return $this->skillId;
    }

    public function getVersion(): int
    {
        return $this->version;
    }

    public function getName(): string
    {
        return $this->name;
    }

    public function getDescription(): string
    {
        return $this->description;
    }

    public function getContent(): string
    {
        return $this->content;
    }

    public function getContentHash(): string
    {
        return $this->contentHash;
    }

    public function getCreatedBy(): string
    {
        return $this->createdBy;
    }

    public function getCreatedAt(): int
    {
        return $this->createdAt;
    }
}
