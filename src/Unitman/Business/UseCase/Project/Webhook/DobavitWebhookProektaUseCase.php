<?php

namespace App\Unitman\Business\UseCase\Project\Webhook;

use App\Unitman\Business\Command\Project\Webhook\SozdatWebhookProekta;
use App\Unitman\Business\Model\Project\ProjectWebhook;
use App\Unitman\Business\Port\CanGeneateGuid;
use App\Unitman\Business\Port\Project\WebhookProjectRepository;
use App\Unitman\Business\Port\UnitmanSecurityService;

final class DobavitWebhookProektaUseCase
{
    public function __construct(
        private CanGeneateGuid $canGeneateGuid,
        private WebhookProjectRepository $repository,
        private UnitmanSecurityService $securityService,
    )
    {
    }

    function handle(SozdatWebhookProekta $command): string
    {
        if (!$this->securityService->isAdmin()) {
            throw new \DomainException('security.access_denied');
        }

        if ($this->repository->esliEstDubliPoUrl($command->projectId, $command->url)) {
            throw new \DomainException('proekt.webhook.double');
        }

        $id = $this->canGeneateGuid->makeGuid();
        $model = ProjectWebhook::sozdat($id,$command);
        $this->repository->dobavit($model);
        return $id;
    }
}
