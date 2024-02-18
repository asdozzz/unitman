<?php

namespace App\Unitman\Infra\Repository\Repo;

use App\Unitman\Business\Command\Repo\GetActiveRepoList;
use App\Unitman\Business\Command\Repo\GetRepoList;
use App\Unitman\Business\Port\CanGetActiveProjectList;
use App\Unitman\Business\Port\Repo\CanFindRepoDouble;
use App\Unitman\Business\Port\Repo\CanGetActiveRepoList;
use App\Unitman\Business\Port\Repo\CanGetRepoList;
use App\Unitman\Business\ReadModel\RepoList;
use Doctrine\DBAL\Connection;

final class RepoListRepository implements CanFindRepoDouble, CanGetRepoList, CanGetActiveRepoList
{
    const TABLE = 'repo_list';
    public function __construct(private readonly Connection $connection)
    {
    }

    function insert(RepoList $repoList): void
    {
        $data = [
            'id' => $repoList->getId(),
            'type' => $repoList->getType(),
            'name' => $repoList->getName(),
            'repo_url' => $repoList->getRepoUrl(),
            'token' => $repoList->token,
            'confirmed' => $repoList->isConfirmed() ? 1 : 0
        ];
        $this->connection->insert(self::TABLE, $data);
    }

    function update(RepoList $repoList): void
    {
        $this->connection->update(self::TABLE, [
            'id' => $repoList->getId(),
            'type' => $repoList->getType(),
            'name' => $repoList->getName(),
            'repo_url' => $repoList->getRepoUrl(),
            'token' => $repoList->token,
            'confirmed' => $repoList->isConfirmed()?1:0
        ], ['id' => $repoList->getId()]);
    }

    function delete(string $id): void
    {
        $this->connection->delete(self::TABLE,['id' => $id]);
    }

    function getById(string $id): RepoList
    {
        $row = $this->findRowById($id);

        if (empty($row)) {
            throw new \Exception('Repo not found with id='.$id);
        }

        $repo = $this->makeRepoByDbRow($row);

        return $repo;
    }

    function makeRepoByDbRow(array $row): RepoList
    {
        $repo = new RepoList(
            $row['id'],
            $row['type'],
            $row['name'],
            $row['repo_url'],
            $row['token'],
            $row['confirmed'],
        );
        return $repo;
    }

    public function isExistDoubleByName(string $repoName): bool
    {
        $table = self::TABLE;
        $row = $this->connection->fetchAssociative("SELECT * FROM $table WHERE name = :name", ['name' => $repoName]);

        return !empty($row);
    }

    /**
     * @param string $id
     * @return false|mixed[]
     * @throws \Doctrine\DBAL\Exception
     */
    public function findRowById(string $id): array|false
    {
        $table = self::TABLE;
        $row = $this->connection->fetchAssociative("SELECT * FROM $table WHERE id = :id", ['id' => $id]);
        return $row;
    }

    /**
     * @return array<int, RepoList>
     */
    function getList(GetRepoList $query): array
    {
        $table = self::TABLE;
        $rows = $this->connection->fetchAllAssociative("SELECT * FROM $table LIMIT :limit OFFSET :offset",
            ['limit' => $query->limit, 'offset' => $query->offset]);

        $result = [];
        foreach ($rows as $row) {
            $result[] = $this->makeRepoByDbRow($row);
        }

        return $result;
    }

    /**
     * @return array<int, RepoList>
     */
    function getActiveRepoList(GetActiveRepoList $query): array
    {
        $table = self::TABLE;
        $rows = $this->connection->fetchAllAssociative("SELECT * FROM $table WHERE confirmed = '1' LIMIT :limit OFFSET :offset",
            ['limit' => $query->limit, 'offset' => $query->offset]);

        $result = [];
        foreach ($rows as $row) {
            $result[] = $this->makeRepoByDbRow($row);
        }

        return $result;
    }
}
