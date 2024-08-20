<?php

namespace App\Utils\EventSauce;

use EventSauce\EventSourcing\Message;
use EventSauce\EventSourcing\MessageConsumer;

abstract class AbstractProjection implements CanProjectEvents
{
    protected function getExceptionEvents(): array
    {
        return [];
    }

    function isAllowedRebuild(): bool
    {
        return true;
    }
    public function handle(Message $message): void
    {
        $event = $message->payload();

        $reflect = new \ReflectionClass($event);
        $eventClass = $reflect->getShortName();
        $methodName = 'handle'.$eventClass;
        if (method_exists($this::class, $methodName)) {
            $this->{$methodName}($event);
        } else if (!in_array($event::class, $this->getExceptionEvents())) {
            throw new \RuntimeException(sprintf('Handler %s for event=%s in %s not found', $methodName, $eventClass, $this::class));
        }
    }
}
