<?php

namespace App\BackgroundJob\Infra\Cli;

use App\BackgroundJob\Infra\Service\BackgroundJobCollection;
use Psr\Log\LoggerInterface;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

#[AsCommand(name: 'app:service:run')]
final class RoadrunnerServiceSingleRunCommand extends Command
{
    public function __construct(private BackgroundJobCollection $backgroundJobCollection)
    {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this
            ->addArgument('serviceName', InputArgument::OPTIONAL, 'serviceName');
        ;
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        try {
            $serviceName = $input->getArgument('serviceName');

            if (empty($serviceName)) {
                throw new \Exception('serviceName not defined');
            }

            $job = $this->backgroundJobCollection->getJobByName($serviceName);
            $job->run();

            return Command::SUCCESS;
        } catch (\Exception $e) {
            $output->writeln('error: ' . $e->getMessage());
            return Command::FAILURE;
        }
    }
}
