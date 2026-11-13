<?php

namespace App\Unitman\Business\ReadModel\Project;

final class WebsocketProjectEventReadModel
{
    const SOZDAN = 'SOZDAN';
    const UDALEN = 'UDALEN';
    const OBNOVLEN = 'OBNOVLEN';

    public function __construct(
        public readonly string $id,
        public readonly string $eventType,
        public readonly string $userId,
        public readonly string $userEmail,
        public readonly string $projectName,
    )
    {
    }
}
