<?php

declare(strict_types=1);

namespace App\Unitman\Tests\Api;

use App\Unitman\Business\ReadModel\LichniyToken\LichniyTokenList;
use App\Unitman\Business\ReadModel\RepoList;
use App\Unitman\Infra\Repository\LichniyToken\SqlLichniyTokenListRepository;
use App\Unitman\Infra\Repository\Project\SqlProjectListRepository;
use App\Unitman\Infra\Repository\Repo\RepoListRepository;
use App\Unitman\Tests\Api\Concern\NoCredentialsAssertions;
use Doctrine\DBAL\Connection;
use Lexik\Bundle\JWTAuthenticationBundle\Encoder\JWTEncoderInterface;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Test;
use Symfony\Bundle\FrameworkBundle\KernelBrowser;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;

/**
 * LT-3: матрица авторизации для пяти read-эндпоинтов.
 *
 * Политика закодирована явно в data provider:
 *  - repo/list, repo/activeList, runner/list и project/list — только админ;
 *    ROLE_USER получает 403;
 *  - lichniyToken/spisok — пользовательский: ROLE_USER получает 200 и видит
 *    только свои токены.
 *
 * Для admin 200 ответы дополнительно проверяются на отсутствие ключей и
 * значений учётных данных (общий рекурсивный helper LT-4).
 */
final class ReadEndpointsAuthorizationMatrixTest extends WebTestCase
{
    use NoCredentialsAssertions;

    private const SYNTHETIC_TOKEN_CIPHER = 'synthetic-cipher-fixture';
    private const SYNTHETIC_TOKEN_HINT = 'ALUE';

    private KernelBrowser $client;

    private Connection $db;

    private ?string $currentAccountId = null;

    private ?string $currentEmail = null;

    protected function setUp(): void
    {
        parent::setUp();

        $this->client = static::createClient();
        $this->client->disableReboot();

        $container = static::getContainer();
        $this->db = $container->get(Connection::class);
        $this->db->beginTransaction();

        $repoRepository = $container->get(RepoListRepository::class);
        \assert($repoRepository instanceof RepoListRepository);
        $repoRepository->init();

        $repo = new RepoList();
        $repo->id = 'repo-ws11-matrix-fixture';
        $repo->type = 'gitlab';
        $repo->name = 'ws11-matrix-repo';
        $repo->repoUrl = 'https://example.invalid/ws11-matrix-repo';

        // Совместимость с pre-WS-1 моделью (RepoList::$token) нужна только для
        // negative-evidence прогона на старом коммите; на текущем коде
        // существуют tokenCipher/tokenHint. Все значения синтетические.
        if (property_exists($repo, 'token')) {
            $repo->token = 'TESTTOKENEXAMPLEVALUE';
        }
        if (property_exists($repo, 'tokenCipher')) {
            $repo->tokenCipher = self::SYNTHETIC_TOKEN_CIPHER;
        }
        if (property_exists($repo, 'tokenHint')) {
            $repo->tokenHint = self::SYNTHETIC_TOKEN_HINT;
        }
        $repo->confirmed = true;
        $repoRepository->insert($repo);

        $projectRepository = $container->get(SqlProjectListRepository::class);
        \assert($projectRepository instanceof SqlProjectListRepository);
        $projectRepository->init();

        $tokenRepository = $container->get(SqlLichniyTokenListRepository::class);
        \assert($tokenRepository instanceof SqlLichniyTokenListRepository);
        $tokenRepository->init();

        // В тестовой БД таблица runner_state может отсутствовать; создаём в
        // транзакции — откат снимет её, если она была создана здесь.
        $this->db->executeStatement(
            'CREATE TABLE IF NOT EXISTS runner_state (id varchar(128) PRIMARY KEY, payload jsonb NOT NULL)',
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
     * @return iterable<string, array{string, string, int, string}>
     */
    public static function readEndpoints(): iterable
    {
        // Админские чтения: ROLE_USER должен получить 403.
        yield 'repo/list (admin-only)' => ['POST', '/api/repo/list', 403, '{}'];
        yield 'repo/activeList (admin-only)' => ['POST', '/api/repo/activeList', 403, '{}'];
        yield 'runner/list (admin-only, GET)' => ['GET', '/api/runner/list', 403, ''];
        yield 'project/list (admin-only)' => ['POST', '/api/project/list', 403, '{}'];

        // Пользовательский read: любой аутентифицированный видит свои токены.
        yield 'lichniyToken/spisok (user-scoped)' => ['POST', '/api/lichniyToken/spisok', 200, '{}'];
    }

    #[Test]
    #[DataProvider('readEndpoints')]
    public function anonymous_is_rejected_with_401(string $method, string $path, int $userStatus, string $payload): void
    {
        $this->send($method, $path, $payload);

        self::assertSame(401, $this->client->getResponse()->getStatusCode(), $path);
        $this->assertResponseHasNoCredentials($path);
    }

    #[Test]
    #[DataProvider('readEndpoints')]
    public function role_user_has_the_documented_policy(string $method, string $path, int $expectedStatus, string $payload): void
    {
        $this->authenticateAs('ROLE_USER');

        $this->send($method, $path, $payload);

        self::assertSame($expectedStatus, $this->client->getResponse()->getStatusCode(), $path);
        if ($expectedStatus === 200) {
            $this->assertResponseHasNoCredentials($path);
        }
    }

    #[Test]
    #[DataProvider('readEndpoints')]
    public function role_admin_receives_200_without_credentials(string $method, string $path, int $userStatus, string $payload): void
    {
        $this->authenticateAs('ROLE_ADMIN');

        $this->send($method, $path, $payload);

        self::assertSame(200, $this->client->getResponse()->getStatusCode(), $path);

        $content = json_decode((string) $this->client->getResponse()->getContent(), true);
        self::assertIsArray($content, $path.' вернул не JSON-объект');
        self::assertSame('success', $content['status'] ?? null, $path);

        $this->assertResponseHasNoCredentials($path);
    }

    #[Test]
    public function user_sees_only_own_personal_tokens(): void
    {
        $this->authenticateAs('ROLE_USER');
        $ownAccountId = (string) $this->currentAccountId;

        $repository = static::getContainer()->get(SqlLichniyTokenListRepository::class);
        \assert($repository instanceof SqlLichniyTokenListRepository);

        $repository->insert($this->makeToken('token-ws11-own', $ownAccountId));
        $repository->insert($this->makeToken('token-ws11-foreign', 'acc-other-ws11'));

        $this->send('POST', '/api/lichniyToken/spisok', '{}');

        self::assertSame(200, $this->client->getResponse()->getStatusCode());

        $content = json_decode((string) $this->client->getResponse()->getContent(), true);
        self::assertIsArray($content);
        self::assertSame('success', $content['status'] ?? null);
        self::assertIsArray($content['data'] ?? null);

        $ids = array_map(
            static fn (mixed $item): string => (string) ($item['id'] ?? ''),
            $content['data'],
        );

        self::assertContains('token-ws11-own', $ids);
        self::assertNotContains('token-ws11-foreign', $ids, 'Пользователь не должен видеть чужие токены.');

        $this->assertResponseHasNoCredentials('lichniyToken/spisok (user-scoped)');
    }

    #[Test]
    public function credential_scanner_detects_nested_keys_and_values(): void
    {
        $nested = [
            'data' => [
                ['meta' => ['token' => 'TESTTOKENEXAMPLEVALUE']],
                ['url' => 'https://oauth2:TESTTOKENEXAMPLEVALUE@git.example.invalid/repo.git'],
                ['note' => 'ghp_EXAMPLE000000000000000000000000000000'],
                ['note' => 'github_pat_EXAMPLE000000000000000000000000000000'],
                ['note' => 'glpat-EXAMPLE000000000000000000000'],
                ['header' => 'Bearer TESTJWTEXAMPLEVALUE'],
                ['at' => 'TESTTOKENEXAMPLEVALUE@host'],
            ],
        ];

        self::assertNotEmpty(
            self::findForbiddenCredentialKeys($nested),
            'Вложенный ключ token должен обнаруживаться.',
        );
        self::assertNotEmpty(
            self::findCredentialLookingValues($nested),
            'Вложенные значения-креденшелы должны обнаруживаться.',
        );

        // tokenHint — безопасная подсказка, не токен.
        self::assertSame([], self::findForbiddenCredentialKeys(['tokenHint' => 'ALUE']));
    }

    private function send(string $method, string $path, string $payload): void
    {
        $this->client->request(
            $method,
            $path,
            [],
            [],
            ['CONTENT_TYPE' => 'application/json'],
            $method === 'GET' ? null : $payload,
        );
    }

    private function authenticateAs(string $role): void
    {
        $email = uniqid('ws11_read_', true).'@example.invalid';
        $accountId = 'acc-'.uniqid('', true);

        $this->db->insert('jwt_user', [
            'id' => $accountId,
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

        $this->currentAccountId = $accountId;
        $this->currentEmail = $email;
    }

    private function makeToken(string $id, string $accountId): LichniyTokenList
    {
        return new LichniyTokenList(
            $id,
            $accountId,
            'repo-ws11-matrix-fixture',
            'ws11 personal token',
            self::SYNTHETIC_TOKEN_CIPHER,
            self::SYNTHETIC_TOKEN_HINT,
            'spisok',
            [],
            1700000000,
            false,
        );
    }

    private function assertResponseHasNoCredentials(string $context): void
    {
        $content = json_decode((string) $this->client->getResponse()->getContent(), true);
        if (!is_array($content)) {
            return;
        }

        self::assertNoCredentialsInResponse($content, $context);
    }
}
