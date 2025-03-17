<?php

namespace App\Utils\Service;

use Doctrine\DBAL\Connection;
use Doctrine\DBAL\Exception;

final class DoctrineReconnectHelper
{
    public function __construct(private Connection $connection)
    {
    }

    function reconnectIfNeed(): void
    {
        if (false === $this->ping()) {
            $this->connection->close();
            sleep(1);
            $this->connection->getNativeConnection();
        }
    }

    private function ping(): bool
    {
        try {
            $this->executeDummySql($this->connection);

            return true;
        } catch (Exception) {
            return false;
        }
    }

    private function executeDummySql(Connection $connection): void
    {
        $connection->executeQuery($connection->getDatabasePlatform()->getDummySelectSQL());
    }
}
