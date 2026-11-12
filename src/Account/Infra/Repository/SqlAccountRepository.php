<?php

namespace App\Account\Infra\Repository;

use App\Account\Business\Model\Account;
use App\Account\Business\Port\AccountRepository;
use App\Utils\EventSauce\ProjectionsManager;

final class SqlAccountRepository implements AccountRepository
{
    public function __construct(
        private ProjectionsManager $projectionsManager
    )
    {}

    public function getBy(string $accountId): Account
    {
        $account = $this->projectionsManager->retrieve(Account::class, Account\AccountId::fromString($accountId));
        /** @var Account $account*/
        if ($account->aggregateRootVersion() === 0) {
            throw new \DomainException('account.not_found');
        }

        return $account;
    }

    public function save(Account $account): void
    {
        $this->projectionsManager->persistAndPullProjections($account);
    }
}
