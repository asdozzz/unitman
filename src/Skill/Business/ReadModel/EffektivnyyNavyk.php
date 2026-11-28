<?php

declare(strict_types=1);

namespace App\Skill\Business\ReadModel;

use App\Skill\Business\Model\NavykOpenCode;
use App\Skill\Business\Utils\ImyaKonteyneraNavyka;

/**
 * Навык в составе эффективного набора для проекта/сессии/раннера.
 *
 * `projectId` равен `null` для глобального навыка. Для проектного навыка это
 * первый по порядку запроса проект, к которому он привязан.
 */
final class EffektivnyyNavyk
{
    private function __construct(
        private readonly string $skillId,
        private readonly string $slug,
        private readonly string $containerName,
        private readonly string $name,
        private readonly string $description,
        private readonly string $content,
        private readonly int $version,
        private readonly string $hash,
        private readonly string $scope,
        private readonly ?string $projectId,
    ) {
    }

    public static function izModela(NavykOpenCode $navyk, ?string $projectId): self
    {
        $isGlobal = $navyk->isGlobal();
        $containerName = $isGlobal
            ? ImyaKonteyneraNavyka::dlyaGlobalnogo($navyk->getSlug())
            : ImyaKonteyneraNavyka::dlyaProektnogo((string) $projectId, $navyk->getSlug());

        return new self(
            $navyk->getId(),
            $navyk->getSlug(),
            $containerName,
            $navyk->getName(),
            $navyk->getDescription(),
            $navyk->getContent(),
            $navyk->getVersion(),
            $navyk->getContentHash(),
            $isGlobal ? 'global' : 'project',
            $isGlobal ? null : $projectId,
        );
    }

    public function getSkillId(): string
    {
        return $this->skillId;
    }

    public function getContainerName(): string
    {
        return $this->containerName;
    }

    public function getVersion(): int
    {
        return $this->version;
    }

    public function getHash(): string
    {
        return $this->hash;
    }

    public function getContent(): string
    {
        return $this->content;
    }

    public function getScope(): string
    {
        return $this->scope;
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return [
            'skillId' => $this->skillId,
            'slug' => $this->slug,
            'containerName' => $this->containerName,
            'name' => $this->name,
            'description' => $this->description,
            'content' => $this->content,
            'version' => $this->version,
            'hash' => $this->hash,
            'scope' => $this->scope,
            'projectId' => $this->projectId,
        ];
    }
}
