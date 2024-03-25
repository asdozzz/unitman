<?php

namespace App\Utils\EventSauce;

use App\Utils\EventSauce\Model\StreamName;
use EventSauce\EventSourcing\MessageConsumer;
use Symfony\Component\DependencyInjection\Attribute\AutoconfigureTag;

#[AutoconfigureTag('utils.event_store.projection')]
interface CanProjectEvents extends MessageConsumer
{
    function getProjectionName(): string;

    function reset(): void;

    function getStreamName(): StreamName;
}
