<?php

namespace App\Account\Infra\Repository;

use App\Account\Business\Model\Account;
use App\Account\Business\Port\AccountRepository;
use Doctrine\DBAL\Connection;
use EventSauce\EventSourcing\EventSourcedAggregateRootRepository;
use EventSauce\EventSourcing\MessageDecorator;
use EventSauce\EventSourcing\MessageDispatcher;
use EventSauce\EventSourcing\MessageRepository;

/**
 * @extends EventSourcedAggregateRootRepository<Account>
 * */
final class SqlAccountRepository implements AccountRepository
{
    private Connection $connection;

    public function __construct(Connection $connection, MessageRepository $messageRepository, MessageDispatcher $messageDispatcher, MessageDecorator $messageDecorator)
    {
        $this->esRepository = new EventSourcedAggregateRootRepository(
            Account::class,
            $messageRepository,
            $messageDispatcher,
            $messageDecorator
        );
        $this->connection = $connection;
    }

    public function getBy(string $accountId): Account
    {
        $account = $this->esRepository->retrieve(Account\AccountId::fromString($accountId));
        /** @var Account $account*/
        if ($account->aggregateRootVersion() === 0) {
            throw new \DomainException('account.not_found');
        }

        return $account;
    }

    public function save(Account $account): void
    {
        $this->connection->transactional(fn() => $this->esRepository->persist($account));
    }
}
