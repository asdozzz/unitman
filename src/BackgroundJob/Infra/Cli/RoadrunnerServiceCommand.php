<?php

declare(strict_types=1);

namespace App\BackgroundJob\Infra\Cli;

use App\BackgroundJob\Infra\Service\BackgroundJobCollection;
use Psr\Log\LoggerInterface;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Command\SignalableCommandInterface;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

#[AsCommand(name: 'app:service:start')]
final class RoadrunnerServiceCommand extends Command implements SignalableCommandInterface
{
    /**
     * Без обработки сигналов процесс не завершался по SIGTERM при остановке
     * сервиса RoadRunner (rr reset / перезапуск контейнера), RR добивал его
     * и писал в лог "error=exit status 255". Флаг позволяет выйти из цикла
     * штатно и вернуть код успеха.
     */
    private bool $shouldStop = false;

    /**
     * Сколько подряд неудачных итераций допускается до выхода из цикла.
     * Выход не фатален: сервис объявлен в .rr.yaml с remain_after_exit: true
     * и restart_sec: 10, поэтому RR поднимет процесс заново со сброшенными
     * счётчиками.
     */
    private const MAX_CONSECUTIVE_ERRORS = 3;

    public function __construct(private BackgroundJobCollection $backgroundJobCollection, private LoggerInterface $logger)
    {
        parent::__construct();
    }

    /**
     * @return int[]
     */
    public function getSubscribedSignals(): array
    {
        return [SIGTERM, SIGINT];
    }

    public function handleSignal(int $signal, int|false $previousExitCode = 0): int|false
    {
        $this->shouldStop = true;

        return false;
    }

    protected function configure(): void
    {
        $this
            ->addArgument('serviceName', InputArgument::OPTIONAL, 'serviceName');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $serviceName = $input->getArgument('serviceName');

        if (!is_string($serviceName) || '' === $serviceName) {
            $output->writeln('error: serviceName not defined');

            return Command::FAILURE;
        }

        try {
            $job = $this->backgroundJobCollection->getJobByName($serviceName);
        } catch (\Throwable $e) {
            $output->writeln('error: '.$e->getMessage());

            return Command::FAILURE;
        }

        $countError = 0;

        while (!$this->shouldStop) {
            try {
                // run() возвращает false при "мягкой" ошибке (джоба сама поймала
                // исключение). Такой результат нельзя считать успехом, иначе
                // счётчик ошибок никогда не сработает.
                if ($job->run() === false) {
                    throw new \RuntimeException('background job returned false');
                }

                $countError = 0;
            } catch (\Throwable $e) {
                // \Throwable, а не \Exception: TypeError/Error из джобы ранее
                // убивали процесс без записи в лог.
                $countError++;
                $this->logger->error(
                    sprintf('service %s occured error: %s', $serviceName, $e->getMessage()),
                    ['exception' => $e]
                );

                if ($countError >= self::MAX_CONSECUTIVE_ERRORS) {
                    $this->logger->error('Max count error with service '.$serviceName);

                    return Command::SUCCESS;
                }

                $this->pause($job->getDelay() * 5);

                continue;
            }

            $this->pause($job->getDelay());
        }

        return Command::SUCCESS;
    }

    /**
     * getDelay() контрактно допускает 0, а sleep(0) превращает цикл
     * в busy-loop и выжигает CPU — держим минимум в одну секунду.
     */
    private function pause(int $seconds): void
    {
        sleep(max(1, $seconds));
    }
}
