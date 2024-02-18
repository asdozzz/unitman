<?php

namespace App\Unitman\Infra\Repository\Project;

use App\Unitman\Business\Command\Project\GetActiveProjectList;
use App\Unitman\Business\Command\Project\GetProjectList;
use App\Unitman\Business\Model\Project\Event\ProjectDataWasChanged;
use App\Unitman\Business\Model\Project\Event\ProjectWasNotDeleted;
use App\Unitman\Business\Port\Project\CanFindProjectDouble;
use App\Unitman\Business\Port\Project\CanGetActiveProjectList;
use App\Unitman\Business\Port\Project\CanGetProjectList;
use App\Unitman\Business\ReadModel\ProjectList;
use Doctrine\DBAL\Connection;

final class SqlProjectListRepository implements CanFindProjectDouble, CanGetProjectList, CanGetActiveProjectList
{
    const TABLE = 'project_list';
    public function __construct(private Connection $connection)
    {
    }

    function insert(ProjectList $projectList): void
    {
        $data = [
            'id' => $projectList->id,
            'repo_id' => $projectList->repoId,
            'code' => $projectList->code,
            'name' => $projectList->name,
            'main_branch' => $projectList->mainBranch,
            'is_active' => $projectList->isActive?1:0,
            'state' => $projectList->state->value,
            'build_text' => $projectList->buildInfo,
            'remove_text' => $projectList->removeInfo,
        ];
        $this->connection->insert(self::TABLE, $data);
    }

    function update(ProjectList $projectList): void
    {
        $this->connection->update(self::TABLE, [
            'code' => $projectList->code,
            'name' => $projectList->name,
            'main_branch' => $projectList->mainBranch,
            'is_active' => $projectList->isActive?1:0,
            'state' => $projectList->state->value,
            'build_text' => $projectList->buildInfo,
            'remove_text' => $projectList->removeInfo,
        ], ['id' => $projectList->id]);
    }

    function updateState(string $projectId,ProjectList\ProjectListStateType $state, ?string $buildInfo = null): void
    {
        $arr = [
            'state' => $state->value,
        ];

        if ($buildInfo) {
            $arr['build_text'] = $buildInfo;
        }

        $this->connection->update(self::TABLE, $arr, ['id' => $projectId]);
    }

    function handleProjectWasNotDeleted(ProjectWasNotDeleted $fact): void
    {
        $this->connection->update(self::TABLE, [
            'state' => ProjectList\ProjectListStateType::REMOVE_ERROR->value,
            'remove_text' => $fact->errorText,
            'is_active' => $fact->isActive?1:0,
        ], ['id' => $fact->id]);
    }

    function handleProjectDataWasChanged(ProjectDataWasChanged $fact): void
    {
        $this->connection->update(self::TABLE, [
            'name' => $fact->newName,
        ], ['id' => $fact->id]);
    }

    function updateActive(string $projectId, bool $is_active): void
    {
        $this->connection->update(self::TABLE, [
            'is_active' => $is_active?1:0,
        ], ['id' => $projectId]);
    }

    public function isExistDouble(string $projectCode, string $projectName): bool
    {
        $table = self::TABLE;
        $row = $this->connection->fetchAssociative("SELECT * FROM $table WHERE code = :code OR name = :name", ['code' => $projectCode, 'name' => $projectName]);

        return !empty($row);
    }

    function delete(string $projectId): void
    {
        $this->connection->delete(self::TABLE, ['id' => $projectId]);
    }

    public function isExistDoubleByName(string $projectName): bool
    {
        $table = self::TABLE;
        $row = $this->connection->fetchAssociative("SELECT * FROM $table WHERE name = :name", ['name' => $projectName]);

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
        $state = ProjectList\ProjectListStateType::from($row['state']);;
        $project = new ProjectList(
            $row['id'],
            $row['repo_id'],
            $row['code'],
            $row['name'],
            $row['main_branch'],
            $row['is_active'],
            $state,
            $row['build_text'],
            $row['remove_text'],
        );
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
        $rows = $this->connection->fetchAllAssociative("SELECT * FROM $table where repo_id = :repoId", ['repoId' => $repoId]);

        $result = [];
        foreach ($rows as $row) {
            $result[] = $this->makeProjectByDbRow($row);
        }

        return $result;
    }

    function getActiveList(GetActiveProjectList $query): array
    {
        $table = self::TABLE;

        $rows = $this->connection->fetchAllAssociative("SELECT * FROM $table WHERE is_active='1' LIMIT :limit OFFSET :offset",
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

        $rows = $this->connection->fetchAllAssociative("SELECT * FROM $table WHERE is_active='1' and id in (:ids)",
            ['ids' => join(',', $projectIds)]);

        $result = [];
        foreach ($rows as $row) {
            $result[] = $this->makeProjectByDbRow($row);
        }

        return $result;
    }
}
