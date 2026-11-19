<?php

declare(strict_types=1);

namespace App\OpenCode\Infra\BackgroundJob\ZavershenieHodovOpenCode;

use App\BackgroundJob\Infra\Service\AbstractBackgroundJob;
use App\BackgroundJob\Infra\Service\BackgroundJobGroup;
use App\OpenCode\Business\Model\HodOpenCode;
use App\OpenCode\Business\Port\HodOpenCodeRepository;
use App\OpenCode\Business\Utils\ZavershenieHodaOpenCode;
use Psr\Log\LoggerInterface;

/**
 * Watchdog завершения ходов OpenCode.
 *
 * Периодически выбирает ходы в RUNNING и пробует получить их результат у
 * раннера. Ошибка одного хода логируется и не прерывает обработку остальных:
 * следующий тик повторит попытку. Инфраструктурная ошибка выборки также не
 * пробрасывается наружу — планировщик не должен считать задачу упавшей.
 */
final class ZavershenieHodovOpenCodeBackgroundJob extends AbstractBackgroundJob
{
    private const BATCH_LIMIT = 10;

    public function __construct(
        private HodOpenCodeRepository $turnRepository,
        private ZavershenieHodaOpenCode $zavershenie,
        private LoggerInterface $logger,
    ) {
    }

    public function getName(): string
    {
        return 'opencode_turn_watchdog';
    }

    public function getGroup(): BackgroundJobGroup
    {
        return BackgroundJobGroup::timers;
    }

    public function getDelay(): int
    {
        return 5;
    }

    public function getProcessNum(): int
    {
        return 1;
    }

    public function run(): bool
    {
        try {
            $turns = $this->turnRepository->findVProzesse(self::BATCH_LIMIT);
        } catch (\Throwable $error) {
            $this->logger->error('opencode.turn_watchdog.batch_load_failed', [
                'exception' => $error,
            ]);

            return true;
        }

        foreach ($turns as $turn) {
            $this->zavershit($turn);
        }

        return true;
    }

    private function zavershit(HodOpenCode $turn): void
    {
        try {
            $this->zavershenie->zavershit($turn);
        } catch (\Throwable $error) {
            // Один проблемный ход не должен блокировать остальные: следующий
            // тик снова попробует его завершить.
            $this->logger->error('opencode.turn_watchdog.turn_failed', [
                'turnId' => $turn->getId(),
                'exception' => $error,
            ]);
        }
    }
}
