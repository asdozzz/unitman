<?php

namespace App\Unitman\Infra\Projection;

use EventSauce\EventSourcing\MessageConsumer;
use Symfony\Component\DependencyInjection\Attribute\AutoconfigureTag;

#[AutoconfigureTag('unitman.projection')]
interface SyncProjectionForRepo extends MessageConsumer
{

}
