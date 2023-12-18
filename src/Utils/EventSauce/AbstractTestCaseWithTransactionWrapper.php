<?php

namespace App\Utils\EventSauce;

use Doctrine\DBAL\Connection;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;
use Symfony\Component\DependencyInjection\ContainerInterface;

abstract class AbstractTestCaseWithTransactionWrapper extends KernelTestCase
{
    protected static ?Connection $db = null;
    protected static ?ContainerInterface $container = null;
    function setUp(): void
    {
        parent::setUp();
        self::$container = static::getContainer();
        self::$db = self::$container->get(Connection::class);
        self::$db->beginTransaction();
    }

    function tearDown(): void
    {
        if (self::$db) self::$db->rollBack();
        parent::tearDown();
    }
}
