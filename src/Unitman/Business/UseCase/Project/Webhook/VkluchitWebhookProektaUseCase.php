<?php

namespace App\Unitman\Business\UseCase\Project\Webhook;

use App\Unitman\Business\Port\Project\WebhookProjectRepository;
use App\Unitman\Business\Port\UnitmanSecurityService;

final class VkluchitWebhookProektaUseCase
{
    public function __construct(
        private WebhookProjectRepository $repository,
        private UnitmanSecurityService $securityService,
    )
    {
    }

    function handle(string $id): void
    {
        if (!$this->securityService->isAdmin()) {
            throw new \Exception('security.access_denied');
        }
        $model = $this->repository->getById($id);
        $model->vkluchit();
        $this->repository->obnovit($model);
    }
}
