<?php

declare(strict_types=1);

namespace App\Notification\Infra\Service;

use App\Notification\Business\Model\TipUvedomleniya;
use App\Notification\Business\Model\Uvedomlenie;
use Symfony\Contracts\Translation\TranslatorInterface;

/**
 * Локализует push-заголовок и текст по типу уведомления и локали подписки.
 */
final class PushTekstSostavitel
{
    public function __construct(private readonly TranslatorInterface $translator)
    {
    }

    /**
     * @return array{title: string, body: string, data: array<string, mixed>}
     */
    public function sostavit(Uvedomlenie $uvedomlenie, string $locale): array
    {
        return match ($uvedomlenie->getType()) {
            TipUvedomleniya::RUNNER_THREAT => $this->runnerThreat($uvedomlenie, $locale),
            TipUvedomleniya::UNIT_STARTED => $this->unitStarted($uvedomlenie, $locale),
            TipUvedomleniya::ACCOUNT_ACTIVATION_REQUEST => $this->accountActivationRequest($uvedomlenie, $locale),
        };
    }

    /**
     * @return array{title: string, body: string, data: array<string, mixed>}
     */
    private function accountActivationRequest(Uvedomlenie $uvedomlenie, string $locale): array
    {
        $payload = $uvedomlenie->getPayload();
        $email = (string) ($payload['email'] ?? '');
        $nickname = (string) ($payload['nickname'] ?? '');

        return [
            'title' => $this->translator->trans(
                'notification.account_activation_request.push_title',
                [],
                'messages',
                $locale,
            ),
            'body' => $this->translator->trans(
                'notification.account_activation_request.push_body',
                ['%email%' => $email, '%nickname%' => $nickname],
                'messages',
                $locale,
            ),
            'data' => [
                'notificationId' => $uvedomlenie->getId(),
                'severity' => $uvedomlenie->getSeverity()->value,
                'requiresRead' => $uvedomlenie->requiresRead(),
                'url' => '/account/list',
            ],
        ];
    }

    /**
     * @return array{title: string, body: string, data: array<string, mixed>}
     */
    private function runnerThreat(Uvedomlenie $uvedomlenie, string $locale): array
    {
        $payload = $uvedomlenie->getPayload();
        $hostname = (string) ($payload['hostname'] ?? $payload['runnerId'] ?? '');
        $level = (string) ($payload['level'] ?? '');
        $memory = $payload['memoryUsedPercent'] ?? '';

        return [
            'title' => $this->translator->trans(
                'notification.runner_threat.push_title',
                [],
                'messages',
                $locale,
            ),
            'body' => $this->translator->trans(
                'notification.runner_threat.push_body',
                ['%hostname%' => $hostname, '%level%' => $level, '%memory%' => (string) $memory],
                'messages',
                $locale,
            ),
            'data' => [
                'notificationId' => $uvedomlenie->getId(),
                'severity' => $uvedomlenie->getSeverity()->value,
                'requiresRead' => $uvedomlenie->requiresRead(),
                'url' => '/runner/list',
            ],
        ];
    }

    /**
     * @return array{title: string, body: string, data: array<string, mixed>}
     */
    private function unitStarted(Uvedomlenie $uvedomlenie, string $locale): array
    {
        $payload = $uvedomlenie->getPayload();
        $unitName = (string) ($payload['unitName'] ?? $payload['unitId'] ?? '');
        $unitId = (string) ($payload['unitId'] ?? '');

        return [
            'title' => $this->translator->trans(
                'notification.unit_started.push_title',
                [],
                'messages',
                $locale,
            ),
            'body' => $this->translator->trans(
                'notification.unit_started.push_body',
                ['%unitName%' => $unitName],
                'messages',
                $locale,
            ),
            'data' => [
                'notificationId' => $uvedomlenie->getId(),
                'severity' => $uvedomlenie->getSeverity()->value,
                'requiresRead' => $uvedomlenie->requiresRead(),
                'url' => $unitId === '' ? '/notifications' : '/unit/' . rawurlencode($unitId) . '/jobs',
            ],
        ];
    }
}
