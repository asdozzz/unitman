<?php

namespace App\BackgroundJob\Infra\Cli;

use App\BackgroundJob\Infra\Service\BackgroundJobCollection;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

#[AsCommand(name: 'app:jobs')]
final class JobsCommand extends Command
{
    public function __construct(private BackgroundJobCollection $backgroundJobCollection)
    {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this
            ->addArgument('commandName', InputArgument::OPTIONAL, 'command');
        ;
    }

    protected function execute(InputInterface $input, OutputInterface $output)
    {
        try {
            $commandName = $input->getArgument('commandName');

            if (empty($commandName)) {
                throw new \Exception('command name not defined');
            }

            match ($commandName) {
                'start' => $this->backgroundJobCollection->startAll(),
                'stop' => $this->backgroundJobCollection->stopAll(),
                default => throw new \Exception('Invalid command name')
            };

            $output->writeln('success');

            return Command::SUCCESS;
        } catch (\Exception $e) {
            $output->writeln('error: '. $e->getMessage());
            return Command::FAILURE;
        }
    }
}
