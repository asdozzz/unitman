<?php

namespace App\Unitman\Infra\Repository\Project;

use App\Unitman\Business\Command\Project\GetActiveProjectList;
use App\Unitman\Business\Command\Project\GetProjectList;
use App\Unitman\Business\Model\Project\Event\ProjectDataWasChanged;
use App\Unitman\Business\Model\Project\Event\ProjectWasNotDeleted;
use App\Unitman\Business\Port\Project\CanFindProjectDouble;
use App\Unitman\Business\Port\Project\CanGetActiveProjectList;
use App\Unitman\Business\Port\Project\CanGetProjectList;
use App\Unitman\Business\Port\Project\UmeetPoluchatSpisokPeremenihProekta;
use App\Unitman\Business\Port\Project\UmeetPoluchatSpisokPolzovateleiProekta;
use App\Unitman\Business\Port\Project\UmeetPoluchatSpisokProektovDlyPolzovatelya;
use App\Unitman\Business\ReadModel\ProjectList;
use Doctrine\DBAL\Connection;
use DomainException;
use Symfony\Component\Serializer\Serializer;

final class SqlProjectListRepository implements CanFindProjectDouble, CanGetProjectList, CanGetActiveProjectList, UmeetPoluchatSpisokProektovDlyPolzovatelya, UmeetPoluchatSpisokPolzovateleiProekta, UmeetPoluchatSpisokPeremenihProekta
{
    const TABLE = 'project_list';
    public function __construct(private readonly Connection $connection, private readonly Serializer $serializer)
    {
    }

    function init(): void
    {
        $this->connection->executeQuery("create table IF NOT EXISTS project_list
            (
                id       varchar(128) not null
                    constraint project_list_pk
                        primary key,
                payload jsonb
            );
        ");
    }

    function destroy(): void
    {
        $this->connection->executeQuery('DROP TABLE IF EXISTS project_list');
    }

    function truncate(): void
    {
        $table = self::TABLE;
        $this->connection->executeQuery("TRUNCATE $table");
    }

    function resetById(string $id): void
    {
        $table = self::TABLE;
        $this->connection->executeQuery("DELETE FROM $table where id = '$id'");
    }

    function insert(ProjectList $projectList): void
    {
        $data = [
            'id' => $projectList->id,
            'payload' => $this->serializer->serialize($projectList, 'json'),
        ];
        $this->connection->insert(self::TABLE, $data);
    }

    function update(ProjectList $projectList): void
    {
        $this->connection->update(self::TABLE, [
            'payload' => $this->serializer->serialize($projectList, 'json'),
        ], ['id' => $projectList->id]);
    }

    function getById(string $id): ProjectList
    {
        $row = $this->findRowById($id);

        if (empty($row)) {
            throw new DomainException('project.not_found');
        }

        return $this->makeProjectByDbRow($row);
    }

    function updateState(string $projectId,ProjectList\ProjectListStateType $state, array $steps = []): void
    {
        $project = $this->getById($projectId);
        $project->state = $state;

        $this->update($project);
    }

    function updateNastroikiHuka(string $projectId,ProjectList\NastroikiHukaProekta $nastroikiHukaProekta): void
    {
        $project = $this->getById($projectId);
        $project->nastroikiHukaProekta = $nastroikiHukaProekta;
        $this->update($project);
    }

    function handleProjectWasNotDeleted(ProjectWasNotDeleted $fact): void
    {
        $project = $this->getById($fact->id);
        $project->state = ProjectList\ProjectListStateType::REMOVE_ERROR;
        $project->waitResultRunner = false;
        $project->isActive = $fact->isActive;

        $this->update($project);
    }

    function handleProjectDataWasChanged(ProjectDataWasChanged $fact): void
    {
        $project = $this->getById($fact->id);
        $project->name = $fact->newName;
        $project->proxyHost = $fact->newProxyHost;
        $project->memoryLimit = $fact->memoryLimit;

        $this->update($project);
    }

    function updateActive(string $projectId, bool $isActive): void
    {
        $project = $this->getById($projectId);
        $project->isActive = $isActive;

        $this->update($project);
    }

    public function isExistDouble(string $projectCode, string $projectName): bool
    {
        $table = self::TABLE;
        $row = $this->connection->fetchAssociative("SELECT * FROM $table WHERE payload->>'code' = :code OR payload->>'name' = :name", ['code' => $projectCode, 'name' => $projectName]);

        return !empty($row);
    }

    function delete(string $projectId): void
    {
        $this->connection->delete(self::TABLE, ['id' => $projectId]);
    }

    public function isExistDoubleByName(string $projectId, string $projectName): bool
    {
        $table = self::TABLE;
        $row = $this->connection->fetchAssociative("SELECT * FROM $table WHERE payload->>'name' = :name and id != :id", ['name' => $projectName, 'id' => $projectId]);

        return !empty($row);
    }

    public function findRowById(string $id): ?array
    {
        $table = self::TABLE;
        $row = $this->connection->fetchAssociative("SELECT * FROM $table WHERE id = :id", ['id' => $id]);
        if (empty($row)) {
            return null;
        }

        return $row;
    }

    function makeProjectByDbRow(array $row): ProjectList
    {
        $project = $this->serializer->deserialize($row['payload'], ProjectList::class, 'json');

        return $project;
    }

    function getList(GetProjectList $query): array
    {
        $table = self::TABLE;

        $rows = $this->connection->fetchAllAssociative("SELECT * FROM $table LIMIT :limit OFFSET :offset",
            ['limit' => $query->limit, 'offset' => $query->offset]);

        $result = [];
        foreach ($rows as $row) {
            $result[] = $this->makeProjectByDbRow($row);
        }

        return $result;
    }

    function getListByRepoId(string $repoId): array
    {
        $table = self::TABLE;
        $rows = $this->connection->fetchAllAssociative("SELECT * FROM $table where payload->>'repoId' = :repoId", ['repoId' => $repoId]);

        $result = [];
        foreach ($rows as $row) {
            $result[] = $this->makeProjectByDbRow($row);
        }

        return $result;
    }

    function getActiveList(GetActiveProjectList $query): array
    {
        $table = self::TABLE;

        $rows = $this->connection->fetchAllAssociative("SELECT * FROM $table WHERE payload->>'isActive'= 'true' 
            ORDER BY id desc LIMIT :limit OFFSET :offset ",
            ['limit' => $query->limit, 'offset' => $query->offset]);

        $result = [];
        foreach ($rows as $row) {
            $result[] = $this->makeProjectByDbRow($row);
        }

        return $result;
    }

    function getActiveListByIds(array $projectIds): array
    {
        $table = self::TABLE;
        $ids = "'" . join("','", $projectIds) . "'";
        $sql = "SELECT * FROM $table WHERE payload->>'isActive'= 'true' and id in ($ids) ORDER BY id desc";
        $rows = $this->connection->fetchAllAssociative($sql);

        $result = [];
        foreach ($rows as $row) {
            $result[] = $this->makeProjectByDbRow($row);
        }

        return $result;
    }

    function poluchitSpisokProektovDlyPolzovatelya(string $userId): array
    {
        $table = self::TABLE;

        $rows = $this->connection->fetchAllAssociative("SELECT * FROM $table WHERE payload->>'isActive'= 'true' and payload->'users' @> '[{\"userId\":\"$userId\"}]'");

        $result = [];
        foreach ($rows as $row) {
            $result[] = $this->makeProjectByDbRow($row);
        }

        return $result;
    }

    function poluchitSpisokPolzovateleiProekta(string $projectId): array
    {
        $project = $this->getById($projectId);
        return $project->users;
    }

    /**
     * @return
     * */
    function poluchitSpisokPeremenihProekta(string $projectId): array
    {
        $project = $this->getById($projectId);
        return $project->variables;
    }
}
