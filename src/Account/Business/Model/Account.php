<?php

namespace App\Account\Business\Model;

use App\Account\Business\Command\RegisterAccount;
use App\Account\Business\Model\Event\AccountWasRegistered;
use Ecotone\Modelling\Attribute\AggregateIdentifier;
use Ecotone\Modelling\Attribute\CommandHandler;
use Ecotone\Modelling\Attribute\EventSourcingAggregate;
use Ecotone\Modelling\Attribute\EventSourcingHandler;
use Ecotone\Modelling\WithAggregateVersioning;

#[EventSourcingAggregate]
final class Account
{
    use WithAggregateVersioning;

    #[AggregateIdentifier]
    private string $accountId;

    private string $email;
    #[CommandHandler]
    public static function registerAccount(RegisterAccount $command): array
    {
        return [new AccountWasRegistered($command->getAccountId(), $command->getEmail())];
    }

    #[EventSourcingHandler]
    public function applyAccountWasRegistred(AccountWasRegistered $fact): void
    {
        $this->accountId = $fact->getAccountId();
        $this->email = $fact->getEmail();
    }
}
