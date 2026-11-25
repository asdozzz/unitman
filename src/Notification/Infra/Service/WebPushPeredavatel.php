<?php

declare(strict_types=1);

namespace App\Notification\Infra\Service;

use App\Notification\Business\Exception\PushPodpiskaNedeystvitelnaException;
use App\Notification\Business\Model\PushPodpiska;
use App\Notification\Business\Model\Uvedomlenie;
use App\Notification\Business\Port\UmeetOtpravlyatPush;
use Minishlink\WebPush\Subscription;
use Minishlink\WebPush\WebPush;

/**
 * Отправка Web Push через minishlink/web-push.
 *
 * VAPID-ключи передаются в настроенный экземпляр WebPush из DI.
 */
final class WebPushPeredavatel implements UmeetOtpravlyatPush
{
    public function __construct(
        private readonly WebPush $webPush,
        private readonly PushTekstSostavitel $tekst,
    ) {
    }

    public function otpravit(Uvedomlenie $uvedomlenie, PushPodpiska $podpiska): void
    {
        $subscription = Subscription::create([
            'endpoint' => $podpiska->getEndpoint(),
            'publicKey' => $podpiska->getP256dh(),
            'authToken' => $podpiska->getAuth(),
        ]);

        $tekst = $this->tekst->sostavit($uvedomlenie, $podpiska->getLocale());
        $payload = json_encode([
            'title' => $tekst['title'],
            'body' => $tekst['body'],
            'data' => $tekst['data'],
        ], JSON_THROW_ON_ERROR);

        $report = $this->webPush->sendOneNotification($subscription, $payload);

        if ($report->isSuccess()) {
            return;
        }

        $response = $report->getResponse();
        $status = $response?->getStatusCode();

        if (in_array($status, [404, 410], true)) {
            throw new PushPodpiskaNedeystvitelnaException($report->getReason());
        }

        throw new \RuntimeException($report->getReason());
    }
}
