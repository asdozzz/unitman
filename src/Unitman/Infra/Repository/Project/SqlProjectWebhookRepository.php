<?php

namespace App\Unitman\Infra\Repository\Project;

use App\Unitman\Business\Model\Project\ProjectWebhook;
use App\Unitman\Business\Port\Project\WebhookProjectRepository;
use App\Unitman\Business\ReadModel\Project\ProjectWebhookReadModel;
use Doctrine\DBAL\Connection;

final class SqlProjectWebhookRepository implements WebhookProjectRepository
{
    const TABLE = 'project_webhook';
    public function __construct(private readonly Connection $connection)
    {
    }

    function dobavit(ProjectWebhook $projectWebhook): void
    {
        $data = [
            'id' => $projectWebhook->id,
            'url' => $projectWebhook->getUrl(),
            'project_id' => $projectWebhook->projectId,
            'active' => $projectWebhook->isActive() ? 1 : 0,
            'deleted_at' => $projectWebhook->getDataUdaleniya()?->getTimestamp()
        ];
        $this->connection->insert(self::TABLE, $data);
    }

    function obnovit(ProjectWebhook $projectWebhook): void
    {
        $data = [
            'url' => $projectWebhook->getUrl(),
            //'project_id' => $projectWebhook->projectId,
            'active' => $projectWebhook->isActive() ? 1 : 0,
            'deleted_at' => $projectWebhook->getDataUdaleniya()?->getTimestamp()
        ];
        $this->connection->update(self::TABLE, $data, ['id' => $projectWebhook->id]);
    }

    function getReadModelById(string $id): ProjectWebhookReadModel
    {
        $table = self::TABLE;
        $row = $this->connection->fetchAssociative("SELECT * FROM $table WHERE id = :id", ['id' => $id]);

        if (empty($row)) {
            throw new \DomainException('project.webhook.not_found_by_id');
        }

        $model = $this->makeReadModelByRow($row);
        return $model;
    }

    function getById(string $id): ProjectWebhook
    {
        $table = self::TABLE;
        $row = $this->connection->fetchAssociative("SELECT * FROM $table WHERE id = :id", ['id' => $id]);

        if (empty($row)) {
            throw new \DomainException('project.webhook.not_found_by_id');
        }

        $model = $this->makeModelByRow($row);
        return $model;
    }

    function getAllActiveByProjectId(string $projectId): array
    {
        $rows = $this->connection->fetchAllAssociative('SELECT * FROM '.self::TABLE.' 
            WHERE project_id = :project_id and active::int = 1 and deleted_at is null',
            ['project_id' => $projectId]);

        return array_map(fn($row)  => $this->makeModelByRow($row), $rows);
    }

    /**
     * @param array $row
     * @return ProjectWebhook
     */
    private function makeModelByRow(array $row): ProjectWebhook
    {
        $dataUdaleniya = null;
        if (!empty($row['deleted_at'])) {
            $dataUdaleniya = \DateTimeImmutable::createFromFormat('U', (string)$row['deleted_at']);
            if ($dataUdaleniya === false) {
                throw new \DomainException('error convert deleted_at');
            }
        }

        $model = new ProjectWebhook($row['id'], $row['project_id'], $row['url'], !empty($row['active']), $dataUdaleniya);
        return $model;
    }

    private function makeReadModelByRow(array $row): ProjectWebhookReadModel
    {
        $model = new ProjectWebhookReadModel($row['id'], $row['project_id'], $row['url'], !empty($row['active']));
        return $model;
    }

    function poluchitWebhookiProekta(string $projectId): array
    {
        $rows = $this->connection->fetchAllAssociative('SELECT * FROM '.self::TABLE.' 
            WHERE project_id = :project_id and deleted_at is null',
            ['project_id' => $projectId]);

        return array_map(fn($row)  => $this->makeReadModelByRow($row), $rows);
    }

    function esliEstDubliPoUrl(string $projectId, string $url): bool
    {
        $rows = $this->connection->fetchAllAssociative('SELECT * FROM '.self::TABLE.' 
            WHERE project_id = :project_id and url=:url and deleted_at is null',
            ['project_id' => $projectId, 'url' => $url]);

        return !empty($rows);
    }
}
