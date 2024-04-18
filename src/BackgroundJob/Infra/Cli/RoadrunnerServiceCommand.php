<?php

namespace App\BackgroundJob\Infra\Cli;

use App\BackgroundJob\Infra\Service\BackgroundJobCollection;
use App\Utils\EventSauce\ProjectionsManager;
use Psr\Log\LoggerInterface;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

#[AsCommand(name: 'app:service:start')]
final class RoadrunnerServiceCommand extends Command
{
    public function __construct(private BackgroundJobCollection $backgroundJobCollection, private LoggerInterface $logger)
    {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this
            ->addArgument('serviceName', InputArgument::OPTIONAL, 'serviceName');
        ;
    }

    protected function execute(InputInterface $input, OutputInterface $output)
    {
        try {
            $serviceName = $input->getArgument('serviceName');

            if (empty($serviceName)) {
                throw new \Exception('serviceName not defined');
            }

            $job = $this->backgroundJobCollection->getJobByName($serviceName);

            $countError = 0;
            while (true) {
                try {
                    $job->run();
                    sleep($job->getDelay());
                } catch (\Exception $e) {
                    $countError++;
                    $this->logger->error(sprintf('service %s occured error: %', $serviceName, $e->getMessage()));
                    if ($countError >= 3) {
                        $this->logger->error('Max count error with service'.$serviceName);
                        continue;
                    }

                    sleep($job->getDelay()*5);
                }
            }

            return Command::SUCCESS;
        } catch (\Exception $e) {
            $output->writeln('error: '. $e->getMessage());
            return Command::FAILURE;
        }
    }
}
