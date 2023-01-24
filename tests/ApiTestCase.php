<?php

namespace App\Tests;

use App\Config\Infra\DataFixture\ConfigFixture;
use App\Infra\DataFixtures\AppFixtures;
use Symfony\Bundle\FrameworkBundle\KernelBrowser;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;

abstract class ApiTestCase extends WebTestCase
{
    /**
     * @var KernelBrowser
     */
    protected $client;
    /**
     * @var \Doctrine\ORM\EntityManager
     */
    protected $entityManager;
    protected $enviromentName = 'test';

    protected function setUp(): void
    {
        $this->client = static::createClient(['environment' => $this->enviromentName]);
    }
}
