<?php

declare(strict_types=1);

namespace App\AiChat\Infra\Adapter;

use App\AiChat\Business\Port\TransactionManager;
use Doctrine\DBAL\Connection;

/**
 * Транзакции поверх Doctrine DBAL.
 *
 * `Connection::transactional()` коммитит при успешном возврате и откатывает
 * при любом исключении. Дополнительные аргументы (само соединение) в
 * замыкание use case не передаются — PHP их просто игнорирует.
 */
final class DbalTransactionManager implements TransactionManager
{
    public function __construct(private readonly Connection $connection)
    {
    }

    public function vTransakcii(callable $rabota): mixed
    {
        return $this->connection->transactional($rabota);
    }
}
