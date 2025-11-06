<?php

namespace App\Unitman\Business\UseCase\Project\Webhook;

use App\Unitman\Business\Command\Project\Webhook\IzmenitUrlWebhooka;
use App\Unitman\Business\Port\Project\WebhookProjectRepository;
use App\Unitman\Business\Port\UnitmanSecurityService;

final class IzmenitUrlWebhookaUseCase
{
    public function __construct(
        private WebhookProjectRepository $repository,
        private UnitmanSecurityService $securityService,
    )
    {
    }

    function handle(IzmenitUrlWebhooka $command): void
    {
        if (!$this->securityService->isAdmin()) {
            throw new \Exception('security.access_denied');
        }
        $model = $this->repository->getById($command->id);
        $model->izmenitUrl($command->newUrl);
        $this->repository->obnovit($model);
    }
}
