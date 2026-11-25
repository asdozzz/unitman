<?php

declare(strict_types=1);

namespace App\AiChat\Tests\Support;

use App\AiChat\Business\Port\TransactionManager;

/**
 * Синхронный менеджер транзакций для тестов.
 *
 * Как и настоящая реализация, просто выполняет переданный callable, но не
 * открывает соединения с БД. Счётчик помогает проверить, что запись истории
 * действительно обёрнута в транзакцию.
 */
final class ImmediateTransactionManager implements TransactionManager
{
    public int $transakciy = 0;

    public function vTransakcii(callable $rabota): mixed
    {
        ++$this->transakciy;

        return $rabota();
    }
}
