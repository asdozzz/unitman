<?php

namespace App\Unitman\Business\Command\Project;

use App\Utils\Converter\JsonBodySerializableInterface;

final class UpdateProjectData implements JsonBodySerializableInterface
{
    public function __construct(
        public readonly string $id,
        public readonly string $newProjectName
    )
    {
    }

}
