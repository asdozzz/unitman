<?php

namespace App\Account\Infra\Cli;

use App\Account\Business\UseCase\RegisterSystemAccountUseCase;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

#[AsCommand(name: 'app:account:system_account', description: 'System account')]
final class RegisterSystemAccountCommand extends Command
{
    public function __construct(private RegisterSystemAccountUseCase $registerSystemAccountUseCase)
    {
        parent::__construct();
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        try {
            $this->registerSystemAccountUseCase->handle();

            $output->writeln("System account registered!");

            return Command::SUCCESS;
        } catch (\Exception $e) {
            $output->writeln('error: '. $e->getMessage());
            return Command::FAILURE;
        }
    }
}
