<?php

namespace App\Unitman\Business\UseCase\Project\Webhook;

use App\Unitman\Business\Port\Project\WebhookProjectRepository;
use App\Unitman\Business\Port\UnitmanSecurityService;
use Psr\Clock\ClockInterface;

final class UdalitWebhookProektaUseCase
{
    public function __construct(
        private WebhookProjectRepository $repository,
        private ClockInterface $clock,
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
        $model->udalit($this->clock->now());
        $this->repository->obnovit($model);
    }
}
