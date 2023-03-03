<?php

namespace App\Unitman\Infra\Repository;

use App\Unitman\Business\Port\CanFindRepoDouble;
use App\Unitman\Business\ReadModel\RepoList;
use Doctrine\DBAL\Connection;

final class RepoListRepository implements CanFindRepoDouble
{
    const TABLE = 'repo_list';
    public function __construct(private Connection $connection)
    {
    }

    function insert(RepoList $repoList): void
    {
        $data = [
            'id' => $repoList->getId(),
            'type' => $repoList->getType(),
            'name' => $repoList->getName(),
            'repo_url' => $repoList->getRepoUrl(),
            'repo_login' => $repoList->getRepoLogin(),
            'repo_password' => $repoList->getRepoPassword(),
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
            'repo_login' => $repoList->getRepoLogin(),
            'repo_password' => $repoList->getRepoPassword(),
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
            $row['repo_login'],
            $row['repo_password'],
            $row['confirmed'],
        );
        return $repo;
    }

    public function isExistDoubleByUrl(string $url): bool
    {
        $table = self::TABLE;
        $row = $this->connection->fetchAssociative("SELECT * FROM $table WHERE repo_url = :url", ['url' => $url]);

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
}
