<?php

declare(strict_types=1);

namespace App\Notification\Business\UseCase;

use App\Notification\Business\Command\PodpisatsyaNaPush;
use App\Notification\Business\Model\PushPodpiska;
use App\Notification\Business\Port\CanGenerateGuid;
use App\Notification\Business\Port\PushPodpiskiRepository;
use App\Notification\Business\Port\UmeetOpredelyatPolzovatelya;

final class PodpisatsyaNaPushUseCase
{
    public function __construct(
        private PushPodpiskiRepository $podpiski,
        private UmeetOpredelyatPolzovatelya $polzovatel,
        private CanGenerateGuid $guid,
    ) {
    }

    public function handle(PodpisatsyaNaPush $command): void
    {
        $endpoint = trim($command->endpoint);
        $p256dh = trim($command->p256dh());
        $auth = trim($command->auth());

        $this->proveritEndpoint($endpoint);

        if ($p256dh === '' || $auth === '') {
            throw new \DomainException('notification.push.keys_invalid');
        }

        $accountId = $this->polzovatel->tekushiyAccountId();
        $existing = $this->podpiski->findByEndpoint($endpoint);
        $now = time();

        $this->podpiski->save(new PushPodpiska(
            $existing?->getId() ?? $this->guid->makeGuid(),
            $accountId,
            $endpoint,
            $p256dh,
            $auth,
            $this->normalizovatLocale($command->locale),
            $existing?->getCreatedAt() ?? $now,
            $now,
        ));
    }

    private function proveritEndpoint(string $endpoint): void
    {
        $scheme = parse_url($endpoint, PHP_URL_SCHEME);
        $host = parse_url($endpoint, PHP_URL_HOST);

        if ($endpoint === '' || !is_string($scheme) || !is_string($host)) {
            throw new \DomainException('notification.push.endpoint_invalid');
        }

        $https = strtolower($scheme) === 'https';
        $localhost = strtolower($scheme) === 'http' && in_array(strtolower($host), ['localhost', '127.0.0.1'], true);

        if (!$https && !$localhost) {
            throw new \DomainException('notification.push.endpoint_invalid');
        }
    }

    private function normalizovatLocale(string $locale): string
    {
        $locale = strtolower(trim($locale));

        return in_array($locale, ['ru', 'en'], true) ? $locale : 'ru';
    }
}
