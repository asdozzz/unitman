<?php

namespace App\Unitman\Business\Port\Project;

use App\Unitman\Business\Model\Project\ProjectWebhook;
use App\Unitman\Business\ReadModel\Project\ProjectWebhookReadModel;

interface WebhookProjectRepository
{
    function dobavit(ProjectWebhook $projectWebhook): void;
    function obnovit(ProjectWebhook $projectWebhook): void;

    function getById(string $id): ProjectWebhook;

    function getReadModelById(string $id): ProjectWebhookReadModel;

    function esliEstDubliPoUrl(string $projectId, string $url): bool;
    /**
     * @return ProjectWebhook[]
     * */
    function getAllActiveByProjectId(string $projectId): array;

    /**
     * @return ProjectWebhookReadModel[]
     * */
    function poluchitWebhookiProekta(string $projectId): array;
}
