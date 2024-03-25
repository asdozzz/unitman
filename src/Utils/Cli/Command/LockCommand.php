<?php

namespace App\Utils\Cli\Command;

use App\Utils\Service\LockService;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

#[AsCommand(name: 'app:lock')]
final class LockCommand extends Command
{
    public function __construct(private LockService $lockService)
    {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this
            ->setDescription('Lock the application')
            ->setHelp('This command allows you to lock the application')
            ->addArgument('value', InputArgument::REQUIRED, '0 - unblock, 1 - block');
        ;
    }
    protected function execute(InputInterface $input, OutputInterface $output)
    {
        try {
            $output->writeln([
                'Lock application',
                '============',
                '',
            ]);

            // retrieve the argument value using getArgument()
            $value = $input->getArgument('value');

            if ((int)$value === 1) {
                $this->lockService->lock();
                $output->writeln('The application was lock');
            } else if ((int)$value === 0) {
                $this->lockService->unlock();
                $output->writeln('The application was unlock');
            } else {
                throw new \Exception('invalid value: 0 - unblock, 1 - block');
            }

            return Command::SUCCESS;
        } catch (\Exception $e) {

            $output->writeln('error: '. $e->getMessage());
            return Command::FAILURE;
        }

    }
}
