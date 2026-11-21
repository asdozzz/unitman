<?php

declare(strict_types=1);

namespace App\Unitman\Tests\Api;

use Doctrine\DBAL\Connection;
use Lexik\Bundle\JWTAuthenticationBundle\Encoder\JWTEncoderInterface;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Test;
use Symfony\Bundle\FrameworkBundle\KernelBrowser;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;

/**
 * HTTP-контракт редакторов юнита: маршруты, JWT и JSON-конверт.
 */
final class RedaktorControllerTest extends WebTestCase
{
    private KernelBrowser $client;

    private Connection $db;

    protected function setUp(): void
    {
        parent::setUp();

        $this->client = static::createClient();
        $this->client->disableReboot();

        $container = static::getContainer();
        $this->db = $container->get(Connection::class);
        $this->db->beginTransaction();

        $email = uniqid('redaktor_api_', true) . '@asd.ru';

        $this->db->insert('jwt_user', [
            'id' => 'acc-' . uniqid('', true),
            'email' => $email,
            'password' => 'pass',
            'roles' => 'ROLE_USER',
            'is_blocked' => 0,
            'locale' => 'ru',
            'nickname' => null,
        ]);

        $encoder = $container->get(JWTEncoderInterface::class);
        \assert($encoder instanceof JWTEncoderInterface);

        $this->client->setServerParameter(
            'HTTP_Authorization',
            'Bearer ' . $encoder->encode(['username' => $email, 'password' => 'pass']),
        );
    }

    protected function tearDown(): void
    {
        if (isset($this->db)) {
            $this->db->rollBack();
        }

        parent::tearDown();
    }

    /**
     * @return iterable<string, array{string, array<string, string>}>
     */
    public static function marshruti(): iterable
    {
        yield 'включить редактирование' => ['/api/unit/vklyuchitRedaktirovanie', ['unitId' => 'unknown']];
        yield 'выключить редактирование' => ['/api/unit/vykluchitRedaktirovanie', ['unitId' => 'unknown']];
        yield 'прикрепить редактора' => ['/api/unit/prikrepitRedaktora', ['unitId' => 'unknown', 'userId' => 'user-2']];
        yield 'открепить редактора' => ['/api/unit/otkrepitRedaktora', ['unitId' => 'unknown', 'userId' => 'user-2']];
        yield 'список редакторов' => ['/api/unit/redaktory', ['unitId' => 'unknown']];
        yield 'ссылка редактора' => ['/api/unit/poluchitSsylkuRedaktora', ['unitId' => 'unknown']];
    }

    /**
     * @param array<string, string> $payload
     */
    #[Test]
    #[DataProvider('marshruti')]
    public function neizvestnii_unit_vozvrashchaet_fail(string $route, array $payload): void
    {
        $this->client->request(
            'POST',
            $route,
            [],
            [],
            ['CONTENT_TYPE' => 'application/json'],
            json_encode($payload, JSON_THROW_ON_ERROR),
        );

        $content = json_decode((string) $this->client->getResponse()->getContent(), true);
        self::assertIsArray($content);
        self::assertSame('fail', $content['status'] ?? null);
    }

    #[Test]
    public function bez_jwt_marshrut_nedostupen(): void
    {
        $this->client->setServerParameter('HTTP_Authorization', '');

        $this->client->request(
            'POST',
            '/api/unit/redaktory',
            [],
            [],
            ['CONTENT_TYPE' => 'application/json'],
            json_encode(['unitId' => 'unknown'], JSON_THROW_ON_ERROR),
        );

        self::assertResponseStatusCodeSame(401);
    }
}
