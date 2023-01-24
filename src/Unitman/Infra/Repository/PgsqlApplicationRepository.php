<?php

namespace App\Unitman\Infra\Repository;

use App\Unitman\Business\Model\Application;
use Doctrine\DBAL\Connection;

final class PgsqlApplicationRepository implements \App\Unitman\Business\Port\ApplicationRepository
{
    const TABLE_NAME = 'application';
    public function __construct(private Connection $connection)
    {
    }


    function save(Application $application): void
    {
        $builder = $this->connection->createQueryBuilder();

        $res = $builder
            ->select('id')
            ->from(self::TABLE_NAME)
            ->where('id=:id')
            ->setParameter('id', $application->getId())
            ->fetchAssociative();

        if (!empty($res['id'])) {
            $this->connection->update(self::TABLE_NAME, [
                'name' => $application->getName()
            ], ['id' => $application->getId()]);
        } else {
            $this->connection->insert(self::TABLE_NAME, [
                'id' => $application->getId(),
                'name' => $application->getName()
            ]);
        }
    }

    function getById(string $id): Application
    {
        $builder = $this->connection->createQueryBuilder();
        $res = $builder
            ->select('*')
            ->from(self::TABLE_NAME)
            ->where('id=:id')
            ->setParameter('id', $id)
            ->fetchAssociative();

        if (empty($res)) {
            throw new \Exception('app.repo.not_found_by_id');
        }

        return new Application(new Application\ApplicationId($res['id']), new Application\ApplicationName($res['name']));
    }
}
