<?php

namespace App\Initialization\Tests\Api;

use App\Initialization\Infra\Repository\SqlInitializationRepository;
use Doctrine\DBAL\Connection;
use Lexik\Bundle\JWTAuthenticationBundle\Encoder\JWTEncoderInterface;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;
use Symfony\Component\DependencyInjection\ContainerInterface;

final class InitializationControllerTest extends WebTestCase
{
    private
        static ?Connection $db = null;
    private static ?ContainerInterface $container = null;
    private \Symfony\Bundle\FrameworkBundle\KernelBrowser $client;

    protected function setUp(): void
    {
        parent::setUp();
        $this->client = static::createClient();
        $encoder = $this->client->getContainer()->get(JWTEncoderInterface::class);
        $this->client->setServerParameter('HTTP_Authorization', sprintf('Bearer %s', $encoder->encode(['username' => 'asd@asd.ru', 'password' => 'pass'])));
        self::$container = static::getContainer();
        self::$db = self::$container->get(Connection::class);
        self::$db->beginTransaction();
    }

    protected function tearDown(): void
    {
        if (self::$db) {
            self::$db->rollBack();
        }
        parent::tearDown();
    }

    public function test_add_default_proxy_host_success(): void
    {
        $repo = self::$container->get(SqlInitializationRepository::class);
        /** @var SqlInitializationRepository $repo */
        $record = $repo->getByProp('proxy_host');
        $this->assertEquals(null, $record->value);
        $this->assertEquals(null, $record->init);

        // mock security to be admin
        $security = $this->createMock(\App\Initialization\Business\Port\SecurityService::class);
        $security->method('isAdmin')->willReturn(true);
        self::$container->set(\App\Initialization\Business\Port\SecurityService::class, $security);

        $this->client->request(
            'POST',
            '/api/initialization/proxy_host',
            [],
            [],
            ['CONTENT_TYPE' => 'application/json'],
            json_encode(['value' => 'http://proxy.example'])
        );
        $this->assertResponseIsSuccessful();

        $record = $repo->getByProp('proxy_host');
        $this->assertEquals('http://proxy.example', $record->value);
    }

    public function test_add_default_proxy_host_access_denied(): void
    {
        $repo = self::$container->get(SqlInitializationRepository::class);

        // mock security to be not admin
        $security = $this->createMock(\App\Initialization\Business\Port\SecurityService::class);
        $security->method('isAdmin')->willReturn(false);
        self::$container->set(\App\Initialization\Business\Port\SecurityService::class, $security);

        $this->client->request(
            'POST',
            '/api/initialization/proxy_host',
            [],
            [],
            ['CONTENT_TYPE' => 'application/json'],
            json_encode(['value' => 'http://proxy.example'])
        );

        $this->assertResponseStatusCodeSame(200); // controller returns JSON error wrapper with 200
        $content = json_decode($this->client->getResponse()->getContent(), true);
        $this->assertArrayHasKey('status', $content);
        $this->assertEquals('error', $content['status']);
    }

    public function test_get_all_props(): void
    {
        $this->client->request(
            'GET',
            '/api/initialization/list',
            [],
            [],
            ['CONTENT_TYPE' => 'application/json']
        );
        $this->assertResponseIsSuccessful();

        $responseJson = $this->client->getResponse()->getContent();
        $response = json_decode($responseJson, true);
        $expected = [
            'status' => 'success',
            'data' => [
                ['id' => 'proxy_host', 'prop' => 'proxy_host', 'value' => null, 'init' => null]
            ]
        ];
        $this->assertEquals($expected, $response);
    }
}
