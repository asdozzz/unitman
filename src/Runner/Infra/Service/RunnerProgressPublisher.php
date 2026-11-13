<?php

declare(strict_types=1);

namespace App\Runner\Infra\Service;

use App\Runner\Business\Model\RunnerProgress;
use App\Runner\Business\Port\UmeetHranitProgressZadachi;
use Fresh\CentrifugoBundle\Service\CentrifugoInterface;

/**
 * Push-канал прогресса задач раннера.
 *
 * Сборка/подготовка/запуск могут генерировать десятки шагов в секунду, поэтому
 * публикация троттлится: не чаще одного раза в THROTTLE_WINDOW_MS на токен.
 * Финальный снапшот (`finished: true`) публикуется всегда — по нему UI
 * закрывает панель прогресса.
 *
 * Ошибки публикации подавляются намеренно: недоступность Centrifugo не должна
 * ломать приём шагов от раннера, pull-endpoint всё равно отдаст актуальное
 * состояние из KV.
 */
final class RunnerProgressPublisher
{
    public const CHANNEL_PREFIX = 'unit_progress#';

    private const THROTTLE_WINDOW_MS = 250;

    public function __construct(
        private readonly CentrifugoInterface $centrifugo,
        private readonly UmeetHranitProgressZadachi $progressStorage,
    ) {
    }

    public function publish(RunnerProgress $progress): bool
    {
        if (!$progress->finished && !$this->mozhnoPublikovat($progress->token)) {
            return false;
        }

        $this->progressStorage->zapomnitVremyaPublikacii($progress->token, $this->nowMs());

        try {
            $this->centrifugo->publish($progress->toClientPayload(), self::CHANNEL_PREFIX . $progress->token);
        } catch (\Throwable) {
            return false;
        }

        return true;
    }

    private function mozhnoPublikovat(string $token): bool
    {
        $lastPublishedAt = $this->progressStorage->poluchitVremyaPublikacii($token);

        if ($lastPublishedAt === null) {
            return true;
        }

        return ($this->nowMs() - $lastPublishedAt) >= self::THROTTLE_WINDOW_MS;
    }

    private function nowMs(): int
    {
        return (int) (microtime(true) * 1000.0);
    }
}
