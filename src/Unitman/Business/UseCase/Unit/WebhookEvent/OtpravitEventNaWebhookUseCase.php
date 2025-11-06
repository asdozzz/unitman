<?php

namespace App\Unitman\Business\UseCase\Unit\WebhookEvent;

use App\Unitman\Business\Port\Project\WebhookProjectRepository;
use App\Unitman\Business\Port\Unit\WebhookEvent\WebhookEventRepository;
use App\Unitman\Business\ReadModel\Unit\WebhookEvent\WebhookEventResponse;
use Symfony\Contracts\HttpClient\HttpClientInterface;

final class OtpravitEventNaWebhookUseCase
{
    public function __construct(
        private WebhookEventRepository $webhookEventRepository,
        private WebhookProjectRepository $webhookProjectRepository,
        private HttpClientInterface $httpClient
    )
    {
    }

    function handle(string $id): void
    {
        $event = $this->webhookEventRepository->getById($id);
        $projectWebhook = $this->webhookProjectRepository->getById($event->webhookId);


        try {
            $response = $this->httpClient->request('POST', $projectWebhook->getUrl(), [
                'json' => $event->payload
            ]);

            $event->setResponse(new WebhookEventResponse($response->getStatusCode(), $response->getContent()));
        } catch (\Throwable $e) {
            $event->setError($e->getMessage());
        }

        $this->webhookEventRepository->update($event);
    }
}
