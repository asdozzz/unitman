<?php

declare(strict_types=1);

namespace App\Unitman\Infra\BackgroundJob\OtlozhennieDeistviya;

use App\BackgroundJob\Infra\Service\AbstractBackgroundJob;
use Psr\Log\LoggerInterface;

/**
 * Страховочный обход отложенных вебхук-действий.
 *
 * Основные пути применения срабатывают по событию: освобождение юнита после
 * хода OpenCode и завершение последней задачи процесса. Этот job нужен на
 * случай, когда событие не наступило — процесс умер, захват истёк по TTL,
 * кто-то забыл вызвать освобождение. Он же чистит истёкшие захваты и
 * просроченные намерения, чтобы таблицы не росли бесконечно.
 */
final class OtlozhennieDeistviyaBackgroundJob extends AbstractBackgroundJob
{
    public function __construct(
        private OtlozhennieDeistviyaActivity $activity,
        private LoggerInterface $logger,
    ) {
    }

    public function getName(): string
    {
        return 'otlozhennie_deistviya';
    }

    public function run(): bool
    {
        $now = new \DateTimeImmutable();

        try {
            $obrabotano = $this->activity->obrabotatOchered($now);
        } catch (\Throwable $e) {
            /*
             * Ошибку не глотаем, но и процесс не роняем: следующий проход
             * повторит попытку, намерения никуда не делись.
             */
            $this->logger->error('unit.otlozhennie_deistviya.batch_failed', [
                'exception' => $e,
            ]);

            return false;
        }

        if ($obrabotano > 0) {
            $this->logger->info('unit.otlozhennie_deistviya.batch_processed', [
                'units' => $obrabotano,
            ]);
        }

        /*
         * Очистка отдельным шагом: она не должна мешать применению намерений
         * и её падение не является поводом считать проход неудачным.
         */
        try {
            $this->activity->ochistit($now);
        } catch (\Throwable $e) {
            $this->logger->error('unit.otlozhennie_deistviya.ochistka_failed', [
                'exception' => $e,
            ]);
        }

        return true;
    }

    public function getDelay(): int
    {
        return 10;
    }
}
