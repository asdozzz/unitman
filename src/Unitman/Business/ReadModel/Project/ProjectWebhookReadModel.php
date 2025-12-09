<?php

namespace App\Unitman\Business\ReadModel\Project;

final class ProjectWebhookReadModel
{
    public function __construct(
        public readonly string $id,
        public readonly string $projectId,
        public string $url,
        public bool $isActive,
    )
    {
    }

}
