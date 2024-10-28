<?php

namespace App\Utils\Cli\Command\ProjectionsManager;

use App\Utils\EventSauce\ProjectionsManager;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\DependencyInjection\Attribute\TaggedIterator;

#[AsCommand(name: 'app:projections:rebuild')]
final class RebuildProjectionCommand extends Command
{
    /** @var iterable<ProjectionsManager>*/
    private iterable $managers;

    public function __construct(#[TaggedIterator('utils.event_store.projections_manager')] iterable $managers)
    {
        parent::__construct();
        $this->managers = $managers;
    }

    protected function configure(): void
    {
        $this
            ->setDescription('Rebuild projection')
            ->setHelp('This command allows you to rebuild a projection')
            ->addArgument('projectionName', InputArgument::REQUIRED, 'projectionName')
            ->addArgument('disableReset', InputArgument::OPTIONAL, 'disableReset')
        ;
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        try {
            $output->writeln([
                'Rebuild projection',
                '============',
                '',
            ]);

            // retrieve the argument value using getArgument()
            $projectionName = $input->getArgument('projectionName');

            if (empty($projectionName)) {
                throw new \Exception('Projection Name not defined');
            }

            $disableReset = $input->getArgument('disableReset');

            if ($projectionName == 'all') {
                foreach ($this->managers as $manager) {
                    $manager->rebuildAll();
                }
            } else {
                $this->rebuildSingle($projectionName, (int) $disableReset);
            }

            return Command::SUCCESS;
        } catch (\Exception $e) {

            $output->writeln('REBUILD ERROR: '. $e->getMessage());
            return Command::FAILURE;
        }

    }

    /**
     * @param mixed $projectionName
     * @param int $disableReset
     * @return void
     * @throws \Exception
     */
    private function rebuildSingle(mixed $projectionName, int $disableReset = 0): void
    {
        $result = null;
        foreach ($this->managers as $manager) {
            if ($manager->isExistProjection($projectionName)) {
                $result = $manager;
            }
        }

        if (empty($result)) {
            throw new \Exception('Projections manager not found for projection with name=' . $projectionName);
        }

        $result->rebuild($projectionName, $disableReset);
    }
}
