<?php

namespace App\Unitman\Infra\Projection;

use EventSauce\EventSourcing\MessageConsumer;
use Symfony\Component\DependencyInjection\Attribute\AutoconfigureTag;

#[AutoconfigureTag('unit.sync_projection')]
interface SyncProjectionForUnit extends MessageConsumer
{

}
