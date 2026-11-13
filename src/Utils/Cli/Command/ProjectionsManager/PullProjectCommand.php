<?php

namespace App\Utils\Cli\Command\ProjectionsManager;

use App\Utils\EventSauce\Model\ProjectionPullResult;
use App\Utils\EventSauce\ProjectionsManager;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\DependencyInjection\Attribute\AutowireIterator;

#[AsCommand(name: 'app:projections:pull')]
final class PullProjectCommand extends Command
{
    /** @var iterable<ProjectionsManager>*/
    private iterable $managers;

    public function __construct(#[AutowireIterator('utils.event_store.projections_manager')] iterable $managers)
    {
        parent::__construct();
        $this->managers = $managers;
    }

    protected function configure(): void
    {
        $this
            ->setDescription('Pull projection')
            ->setHelp('This command allows you to pull a projection')
            ->addArgument('projectionName', InputArgument::REQUIRED, 'projectionName')
        ;
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        try {
            $output->writeln([
                'Pull projection',
                '============',
                '',
            ]);

            // retrieve the argument value using getArgument()
            $projectionName = $input->getArgument('projectionName');

            if (empty($projectionName)) {
                throw new \Exception('Projection Name not defined');
            }

            $result = null;
            foreach ($this->managers as $manager) {
                if ($manager->isExistProjection($projectionName)) {
                    $result = $manager;
                }
            }

            if (empty($result)) {
                throw new \Exception('Projections manager not found for projection with name=' . $projectionName);
            }

            // Skipped означает, что блокировку держит другой процесс и работу
            // выполнит он. Для ручного запуска это не успех, но и не ошибка
            // выполнения — сообщаем явно и выходим с ненулевым кодом, чтобы
            // в скриптах пропуск не выглядел как удачное догоняние.
            if ($result->pullProjectionByName($projectionName) === ProjectionPullResult::Skipped) {
                $output->writeln('SKIPPED: projection lock is held by another process');

                return Command::FAILURE;
            }

            return Command::SUCCESS;
        } catch (\Exception $e) {

            $output->writeln('REBUILD ERROR: '. $e->getMessage());
            return Command::FAILURE;
        }

    }

}
