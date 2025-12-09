<?php

namespace App\Unitman\Business\UseCase\Project\Webhook;

use App\Unitman\Business\Port\Project\WebhookProjectRepository;

final class PoluchitSpisokWebhookovProektaDlyAdmininstrirovaniya
{
    public function __construct(
        private WebhookProjectRepository $repository
    )
    {
    }

    function handle(string $id): array
    {
        return $this->repository->poluchitWebhookiProekta($id);
    }
}
