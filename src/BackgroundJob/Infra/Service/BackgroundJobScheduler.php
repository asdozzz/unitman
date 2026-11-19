<?php

declare(strict_types=1);

namespace App\BackgroundJob\Infra\Service;

use Psr\Log\LoggerInterface;
use Symfony\Component\DependencyInjection\Attribute\AutowireIterator;

/**
 * Планировщик одной группы фоновых задач.
 *
 * В отличие от прежней схемы «один RoadRunner-сервис на задачу», здесь один
 * процесс последовательно выполняет все задачи своей группы. Состояние
 * (nextRunAt, счётчики ошибок, паузы) живёт в свойствах сервиса: процесс
 * долгоживущий, статику использовать нельзя — иначе состояние переживёт
 * пересоздание контейнера и станет общим для всех воркеров.
 */
final class BackgroundJobScheduler
{
    /**
     * После стольких подряд неудач задача временно снимается с расписания.
     */
    private const MAX_CONSECUTIVE_FAILURES = 3;

    /**
     * Минимальная пауза для «сломанной» задачи: даже при delay = 1 не стоит
     * долбить внешний сервис каждую секунду.
     */
    private const MIN_PAUSE_SECONDS = 60;

    /**
     * @var list<BackgroundJobInterface>
     */
    private array $jobs;

    /**
     * @var array<string, int>
     */
    private array $nextRunAt = [];

    /**
     * @var array<string, int>
     */
    private array $consecutiveFailures = [];

    /**
     * @var array<string, int>
     */
    private array $pausedUntil = [];

    /**
     * @param iterable<BackgroundJobInterface> $jobs
     */
    public function __construct(
        #[AutowireIterator('app.background_job')] iterable $jobs,
        private LoggerInterface $logger,
    ) {
        $this->jobs = [];

        foreach ($jobs as $job) {
            $this->jobs[] = $job;
        }
    }

    /**
     * Выполняет все задачи группы, у которых наступило время запуска.
     *
     * Ошибка одной задачи не прерывает проход: она логируется, а остальные
     * задачи группы выполняются в этом же тике.
     */
    public function tick(BackgroundJobGroup $group): void
    {
        $now = time();

        foreach ($this->jobs as $job) {
            if ($job->getGroup() !== $group) {
                continue;
            }

            $name = $job->getName();

            if (($this->pausedUntil[$name] ?? 0) > $now) {
                continue;
            }

            if (($this->nextRunAt[$name] ?? 0) > $now) {
                continue;
            }

            $this->runJob($job);
        }
    }

    private function runJob(BackgroundJobInterface $job): void
    {
        $name = $job->getName();

        try {
            // false — «мягкая» ошибка: задача сама поймала исключение.
            // Такой результат нельзя считать успехом, иначе счётчик ошибок
            // никогда не сработает.
            if ($job->run() === false) {
                throw new \RuntimeException('background job returned false');
            }

            $this->consecutiveFailures[$name] = 0;
        } catch (\Throwable $e) {
            /** @psalm-var positive-int $failures */
            $failures = ($this->consecutiveFailures[$name] ?? 0) + 1;
            $this->consecutiveFailures[$name] = $failures;

            $this->logger->error(
                sprintf('background job %s failed: %s', $name, $e->getMessage()),
                [
                    'exception' => $e,
                    'job' => $name,
                    'group' => $job->getGroup()->value,
                ],
            );

            if ($failures >= self::MAX_CONSECUTIVE_FAILURES) {
                $pause = max(self::MIN_PAUSE_SECONDS, $job->getDelay() * 5);
                $this->pausedUntil[$name] = time() + $pause;
                $this->consecutiveFailures[$name] = 0;

                $this->logger->error(sprintf(
                    'background job %s paused for %d seconds after %d consecutive failures',
                    $name,
                    $pause,
                    $failures,
                ));
            }
        } finally {
            $this->nextRunAt[$name] = time() + $job->getDelay();
        }
    }
}
