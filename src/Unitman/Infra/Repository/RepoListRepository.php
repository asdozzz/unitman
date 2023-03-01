<?php

namespace App\Unitman\Infra\Repository;

use App\Unitman\Business\ReadModel\RepoList;
use Doctrine\DBAL\Connection;

final class RepoListRepository
{
    const TABLE = 'repo_list';
    public function __construct(private Connection $connection)
    {
    }

    function insert(RepoList $repoList): void
    {
        $this->connection->insert(self::TABLE, [
            'id' => $repoList->getId(),
            'type' => $repoList->getType(),
            'name' => $repoList->getName(),
            'repoUrl' => $repoList->getRepoUrl(),
            'repoLogin' => $repoList->getRepoLogin(),
            'repoPassword' => $repoList->getRepoPassword(),
            'confirmed' => $repoList->isConfirmed()
        ]);
    }

    function update(RepoList $repoList): void
    {
        $this->connection->update(self::TABLE, [
            'id' => $repoList->getId(),
            'type' => $repoList->getType(),
            'name' => $repoList->getName(),
            'repoUrl' => $repoList->getRepoUrl(),
            'repoLogin' => $repoList->getRepoLogin(),
            'repoPassword' => $repoList->getRepoPassword(),
            'confirmed' => $repoList->isConfirmed()
        ], ['id' => $repoList->getId()]);
    }

    function delete(string $id): void
    {
        $this->connection->delete(self::TABLE,['id' => $id]);
    }

    function getById(string $id): RepoList
    {
        $table = self::TABLE;
        $row = $this->connection->fetchAssociative("SELECT * FROM $table WHERE id = :id", ['id' => $id]);

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
            $row['repoUrl'],
            $row['repoLogin'],
            $row['repoPassword'],
            $row['confirmed'],
        );
        return $repo;
    }
}
