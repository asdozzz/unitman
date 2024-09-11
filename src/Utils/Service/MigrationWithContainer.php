<?php

namespace App\Utils\Service;

use Symfony\Component\DependencyInjection\ContainerInterface;

trait MigrationWithContainer
{
    /**
     * @var ContainerInterface|null
     */
    protected $container;

    public function setContainer(ContainerInterface $container = null): void
    {
        $this->container = $container;
    }
}
