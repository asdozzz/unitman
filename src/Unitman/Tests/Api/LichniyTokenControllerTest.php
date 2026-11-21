<?php

declare(strict_types=1);

namespace App\Unitman\Tests\Api;

use App\Unitman\Infra\Repository\LichniyToken\SqlLichniyTokenListRepository;
use Doctrine\DBAL\Connection;
use Lexik\Bundle\JWTAuthenticationBundle\Encoder\JWTEncoderInterface;
use PHPUnit\Framework\Attributes\Test;
use Symfony\Bundle\FrameworkBundle\KernelBrowser;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;

/**
 * HTTP-контракт личных токенов: маршруты, JWT и JSON-конверт.
 */
final class LichniyTokenControllerTest extends WebTestCase
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

        $accountId = 'acc-' . uniqid('', true);
        $email = uniqid('lichniy_token_api_', true) . '@asd.ru';

        $this->db->insert('jwt_user', [
            'id' => $accountId,
            'email' => $email,
            'password' => 'pass',
            'roles' => 'ROLE_USER',
            'is_blocked' => 0,
            'locale' => 'ru',
            'nickname' => null,
        ]);

        $listRepository = $container->get(SqlLichniyTokenListRepository::class);
        \assert($listRepository instanceof SqlLichniyTokenListRepository);
        $listRepository->init();

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

    #[Test]
    public function spisok_vozvrashchaet_pustoy_spisok_dlya_novogo_polzovatelya(): void
    {
        $this->client->request(
            'POST',
            '/api/lichniyToken/spisok',
            [],
            [],
            ['CONTENT_TYPE' => 'application/json'],
            '{}',
        );

        $this->assertResponseIsSuccessful();

        $content = json_decode((string) $this->client->getResponse()->getContent(), true);
        self::assertIsArray($content);
        self::assertSame('success', $content['status'] ?? null);
        self::assertSame([], $content['data'] ?? null);
    }

    #[Test]
    public function udalit_neizvestniy_token_vozvrashchaet_oshibku(): void
    {
        $this->client->request(
            'POST',
            '/api/lichniyToken/udalit',
            [],
            [],
            ['CONTENT_TYPE' => 'application/json'],
            json_encode(['id' => 'unknown'], JSON_THROW_ON_ERROR),
        );

        $this->assertFalse($this->client->getResponse()->isSuccessful());

        $content = json_decode((string) $this->client->getResponse()->getContent(), true);
        self::assertIsArray($content);
        self::assertSame('fail', $content['status'] ?? null);
    }
}
