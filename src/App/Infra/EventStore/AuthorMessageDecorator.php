<?php

namespace App\App\Infra\EventStore;

use EventSauce\EventSourcing\Message;
use EventSauce\EventSourcing\MessageDecorator;
use Symfony\Bundle\SecurityBundle\Security;

final class AuthorMessageDecorator implements MessageDecorator
{
    public function __construct(private Security $security)
    {
    }

    public function decorate(Message $message): Message
    {
        /** @psalm-suppress UndefinedInterfaceMethod*/
        $userId = $this->security->getUser()?->getId();
        return $message->withHeader('executed_by', $userId);
    }
}
