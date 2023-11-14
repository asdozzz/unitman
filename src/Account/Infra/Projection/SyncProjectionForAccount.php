<?php

namespace App\Account\Infra\Projection;

use EventSauce\EventSourcing\MessageConsumer;
use Symfony\Component\DependencyInjection\Attribute\AutoconfigureTag;

#[AutoconfigureTag('account.sync_projection')]
interface SyncProjectionForAccount extends MessageConsumer
{

}
