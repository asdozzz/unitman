<?php

namespace App\Unitman\Infra\BackgroundJob\OcheredUnitov;

use App\BackgroundJob\Infra\Service\AbstractBackgroundJob;
use Psr\Log\LoggerInterface;

final class OcheredUnitovBackgroundJob extends AbstractBackgroundJob
{
    public function __construct(
        private OcheredUnitovActivity $ocheredUnitovActivity,
        private LoggerInterface $logger
    )
    {
    }

    function getName(): string
    {
        return 'ochered_unitov';
    }

    function run(): bool
    {
        try {
            $obrabotano = $this->ocheredUnitovActivity->obrabotatOchered(100);
        } catch (\Throwable $e) {
            // Ошибку не глотаем: раньше здесь писался только $e->getMessage(),
            // из-за чего в логе оставалась голая строка «Unable to persist
            // messages.» без типа исключения, стека и исходной причины.
            // Теперь причина видна, но пачка не роняет процесс: очередь
            // переживёт, транзакция откачена целиком.
            $this->logger->error('ochered_unitov.batch_failed', [
                'exception' => $e,
            ]);

            return false;
        }

        if ($obrabotano > 0) {
            $this->logger->info('ochered_unitov.batch_processed', [
                'tasks' => $obrabotano,
            ]);
        }

        return true;
    }

    function getProcessNum(): int
    {
        return 10;
    }
}
