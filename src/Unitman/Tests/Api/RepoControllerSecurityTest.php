<?php

declare(strict_types=1);

namespace App\Unitman\Tests\Api;

use App\Unitman\Business\ReadModel\RepoList;
use App\Unitman\Infra\Repository\Repo\RepoListRepository;
use Doctrine\DBAL\Connection;
use Lexik\Bundle\JWTAuthenticationBundle\Encoder\JWTEncoderInterface;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Test;
use Symfony\Bundle\FrameworkBundle\KernelBrowser;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;

/**
 * HTTP-контракт безопасности списков репозиториев (WS-1).
 *
 * Проверяет матрицу доступа для /api/repo/list и /api/repo/activeList,
 * а также отсутствие учётных данных (token) в ответах и наличие
 * необратимого tokenHint.
 *
 * Все значения синтетические и покрыты allowlist WS-10.
 */
final class RepoControllerSecurityTest extends WebTestCase
{
    private const SYNTHETIC_TOKEN = 'TESTTOKENEXAMPLEVALUE';

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

        $repository = $container->get(RepoListRepository::class);
        \assert($repository instanceof RepoListRepository);
        $repository->init();

        $repo = new RepoList();
        $repo->id = 'repo-ws1-fixture';
        $repo->type = 'gitlab';
        $repo->name = 'ws1-fixture-repo';
        $repo->repoUrl = 'https://example.invalid/ws1-fixture-repo';
        $repo->tokenCipher = 'synthetic-cipher-fixture';
        $repo->tokenHint = 'ALUE';
        $repo->confirmed = true;
        $repository->insert($repo);
    }

    protected function tearDown(): void
    {
        if (isset($this->db)) {
            $this->db->rollBack();
        }

        parent::tearDown();
    }

    /**
     * @return iterable<string, array{string}>
     */
    public static function protectedEndpoints(): iterable
    {
        yield 'list' => ['/api/repo/list'];
        yield 'activeList' => ['/api/repo/activeList'];
    }

    #[Test]
    #[DataProvider('protectedEndpoints')]
    public function anonymous_is_rejected_with_401(string $path): void
    {
        $this->request($path);

        self::assertSame(401, $this->client->getResponse()->getStatusCode());
        self::assertResponseHasNoTokenKey();
    }

    #[Test]
    #[DataProvider('protectedEndpoints')]
    public function role_user_is_denied_with_403(string $path): void
    {
        $this->authenticateAs('ROLE_USER');

        $this->request($path);

        self::assertSame(
            403,
            $this->client->getResponse()->getStatusCode(),
            'Обычный пользователь не должен получать список учётных данных репозиториев.',
        );
        self::assertResponseHasNoTokenKey();
    }

    #[Test]
    #[DataProvider('protectedEndpoints')]
    public function admin_receives_payload_without_token_and_with_token_hint(string $path): void
    {
        $this->authenticateAs('ROLE_ADMIN');

        $this->request($path);

        self::assertSame(200, $this->client->getResponse()->getStatusCode());

        $content = $this->decodedResponse();
        self::assertSame('success', $content['status'] ?? null);
        self::assertIsArray($content['data'] ?? null);

        $this->assertNoTokenKeyIn($content);

        $item = $this->findFixture($content['data']);
        self::assertNotNull($item, 'Фикстура репозитория отсутствует в ответе.');

        self::assertArrayNotHasKey('token', $item, 'Поле token не должно сериализоваться.');
        self::assertArrayHasKey('tokenHint', $item, 'Поле tokenHint должно присутствовать.');

        $hint = $item['tokenHint'];
        self::assertIsString($hint);
        self::assertGreaterThanOrEqual(4, strlen($hint));
        self::assertLessThanOrEqual(6, strlen($hint));
        self::assertStringEndsWith($hint, self::SYNTHETIC_TOKEN, 'tokenHint должен быть суффиксом токена.');
        self::assertNotSame(self::SYNTHETIC_TOKEN, $hint, 'tokenHint не должен раскрывать токен целиком.');
    }

    private function authenticateAs(string $role): void
    {
        $email = uniqid('ws1_repo_', true).'@example.invalid';

        $this->db->insert('jwt_user', [
            'id' => 'acc-'.uniqid('', true),
            'email' => $email,
            'password' => 'pass',
            'roles' => $role,
            'is_blocked' => 0,
            'locale' => 'ru',
            'nickname' => null,
        ]);

        $encoder = static::getContainer()->get(JWTEncoderInterface::class);
        \assert($encoder instanceof JWTEncoderInterface);

        $this->client->setServerParameter(
            'HTTP_Authorization',
            'Bearer '.$encoder->encode(['username' => $email, 'password' => 'pass']),
        );
    }

    private function request(string $path): void
    {
        $this->client->request(
            'POST',
            $path,
            [],
            [],
            ['CONTENT_TYPE' => 'application/json'],
            '{}',
        );
    }

    /**
     * @return array<string, mixed>
     */
    private function decodedResponse(): array
    {
        $content = json_decode((string) $this->client->getResponse()->getContent(), true);
        self::assertIsArray($content, 'Ответ не является JSON-объектом.');

        return $content;
    }

    /**
     * @param array<mixed> $data
     * @return array<string, mixed>|null
     */
    private function findFixture(array $data): ?array
    {
        foreach ($data as $item) {
            if (is_array($item) && ($item['id'] ?? null) === 'repo-ws1-fixture') {
                return $item;
            }
        }

        return null;
    }

    private function assertResponseHasNoTokenKey(): void
    {
        $content = json_decode((string) $this->client->getResponse()->getContent(), true);
        if (is_array($content)) {
            $this->assertNoTokenKeyIn($content);
        }
    }

    /**
     * Рекурсивно проверяет отсутствие ключа "token" на любом уровне вложенности
     * и отсутствие значения, равного сохранённому plaintext-токену.
     *
     * @param array<mixed> $node
     */
    private function assertNoTokenKeyIn(array $node, string $path = '$'): void
    {
        foreach ($node as $key => $value) {
            $current = $path.'.'.$key;

            self::assertNotSame('token', $key, sprintf('Ключ "%s" не должен сериализоваться.', $current));

            if (is_array($value)) {
                $this->assertNoTokenKeyIn($value, $current);
                continue;
            }

            if (is_string($value)) {
                self::assertStringNotContainsString(
                    self::SYNTHETIC_TOKEN,
                    $value,
                    sprintf('Значение "%s" содержит plaintext-токен.', $current),
                );
            }
        }
    }
}
