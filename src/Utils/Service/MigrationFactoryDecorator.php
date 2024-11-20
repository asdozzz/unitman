<?php

namespace App\Utils\Service;

use Doctrine\Migrations\AbstractMigration;
use Doctrine\Migrations\Version\MigrationFactory;
use Symfony\Component\DependencyInjection\Attribute\AsDecorator;
use Symfony\Component\DependencyInjection\ContainerInterface;

#[AsDecorator(decorates: 'doctrine.migrations.migrations_factory')]
final class MigrationFactoryDecorator implements MigrationFactory
{
    private $migrationFactory;
    private $container;

    public function __construct(MigrationFactory $migrationFactory, ContainerInterface $container)
    {
        $this->migrationFactory = $migrationFactory;
        $this->container        = $container;
    }

    public function createVersion(string $migrationClassName): AbstractMigration
    {
        $instance = $this->migrationFactory->createVersion($migrationClassName);

        if (in_array(MigrationWithContainer::class, class_uses($instance))) {
            /** @var MigrationWithContainer $instance */
            $instance->setContainer($this->container);
        }

        return $instance;
    }
}
