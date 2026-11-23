<?php

declare(strict_types=1);

namespace App\Unitman\Infra\BackgroundJob\VetkiBezVebhuka;

use App\BackgroundJob\Infra\Service\AbstractBackgroundJob;
use App\BackgroundJob\Infra\Service\BackgroundJobGroup;
use Psr\Log\LoggerInterface;

/**
 * Периодический опрос веток проектов, где Git-вебхук ещё не подтверждён.
 *
 * Интервал сознательно нечастый: один проход делает запросы к Git-провайдеру,
 * а лимиты API общие для всех проектов.
 */
final class VetkiBezVebhukaPollingBackgroundJob extends AbstractBackgroundJob
{
    const DELAY_SECONDS = 300;

    public function __construct(
        private OprosVetokBezVebhukaActivity $activity,
        private LoggerInterface $logger,
    ) {
    }

    public function getName(): string
    {
        return 'vetki_bez_vebhuka_polling';
    }

    public function getGroup(): BackgroundJobGroup
    {
        return BackgroundJobGroup::producers;
    }

    public function run(): bool
    {
        try {
            $obrabotano = $this->activity->obrabotat();
        } catch (\Throwable $e) {
            $this->logger->error('unit.polling_vetok.prohod_upal', [
                'exception' => $e,
            ]);

            return false;
        }

        if ($obrabotano > 0) {
            $this->logger->info('unit.polling_vetok.prohod_zaverchen', [
                'units' => $obrabotano,
            ]);
        }

        return true;
    }

    public function getDelay(): int
    {
        return self::DELAY_SECONDS;
    }
}
