<?php

namespace App\Utils\EventSauce;

use App\Utils\EventSauce\Model\StreamName;
use EventSauce\EventSourcing\MessageConsumer;
use phpDocumentor\Reflection\Types\True_;
use Symfony\Component\DependencyInjection\Attribute\AutoconfigureTag;

#[AutoconfigureTag('utils.event_store.projection')]
interface CanProjectEvents extends MessageConsumer
{
    function getProjectionName(): string;

    function reset(): void;

    function init(): void;

    function destroy(): void;

    function getStreamName(): StreamName;

    function isSyncProjection(): bool;

    function isAllowedRebuild(): bool;
}
