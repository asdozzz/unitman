<?php

namespace App\Unitman\Business\ReadModel\Unit;

final class SpisokUnitovReadModel
{
    public function __construct(
        public readonly string $id,
        public readonly string $authorId,
        public readonly string $authorName,
        public readonly string $name,
        public readonly string $projectId,
        public readonly string $projectName,
        public readonly string $branch,
        public readonly string $state,
        public readonly bool $waitResultFromRunner,
        public readonly array $commands = [],
        public readonly bool $error = false,
        public readonly array $links = [],
    )
    {
    }

    function copyAndUpdateData(array $props): static
    {
        $id = $this->id;
        $authorId = $this->authorId;
        $name = $this->name;
        $projectId = $this->projectId;
        $projectName = $this->projectName;
        $branch = $this->branch;

        $state = $props['state']??$this->state;
        $waitResultFromRunner = isset($props['waitResultFromRunner'])?$props['waitResultFromRunner']:$this->waitResultFromRunner;
        $commands = isset($props['commands'])?$props['commands']:$this->commands;
        $links = array_key_exists('links', $props)?$props['links']:$this->links;
        $error = array_key_exists('error', $props)?$props['error']:$this->error;

        return new static(
            $id,
            $authorId,
            $this->authorName,
            $name,
            $projectId,
            $projectName,
            $branch,
            $state,
            $waitResultFromRunner,
            $commands,
            $error,
            $links,
        );
    }
}
