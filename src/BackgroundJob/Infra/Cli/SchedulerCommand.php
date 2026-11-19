<?php

declare(strict_types=1);

namespace App\BackgroundJob\Infra\Cli;

use App\BackgroundJob\Infra\Service\BackgroundJobGroup;
use App\BackgroundJob\Infra\Service\BackgroundJobScheduler;
use Psr\Log\LoggerInterface;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Command\SignalableCommandInterface;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;

#[AsCommand(name: 'app:services:scheduler')]
final class SchedulerCommand extends Command implements SignalableCommandInterface
{
    /**
     * Без обработки сигналов процесс не завершался по SIGTERM при остановке
     * сервиса RoadRunner (rr reset / перезапуск контейнера) и RR добивал его
     * с кодом 255. Флаг позволяет выйти из цикла штатно.
     */
    private bool $shouldStop = false;

    public function __construct(
        private BackgroundJobScheduler $scheduler,
        private LoggerInterface $logger,
    ) {
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
            ->addArgument('group', InputArgument::OPTIONAL, 'Background job group')
            ->addOption('once', null, InputOption::VALUE_NONE, 'Run a single tick and exit');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $groupValue = $input->getArgument('group');

        if (!is_string($groupValue) || !BackgroundJobGroup::isValid($groupValue)) {
            $this->logger->error('background_job.group_unknown', [
                'group' => is_string($groupValue) ? $groupValue : null,
            ]);

            $output->writeln(sprintf(
                'error: unknown background job group "%s"; allowed: %s',
                is_string($groupValue) ? $groupValue : '',
                implode(', ', BackgroundJobGroup::all()),
            ));

            return Command::FAILURE;
        }

        $group = BackgroundJobGroup::from($groupValue);

        if ($input->getOption('once')) {
            $this->scheduler->tick($group);

            return Command::SUCCESS;
        }

        while (!$this->shouldStop) {
            $this->scheduler->tick($group);

            /** @psalm-suppress TypeDoesNotContainType Psalm не учитывает мутацию из handleSignal */
            if ($this->shouldStop) {
                break;
            }

            // Минимальная пауза между тиками: задачи с delay = 1 не должны
            // превращать цикл в busy-loop.
            sleep(1);
        }

        return Command::SUCCESS;
    }
}
