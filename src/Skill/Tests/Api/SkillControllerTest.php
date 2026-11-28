<?php

declare(strict_types=1);

namespace App\Skill\Tests\Api;

use App\Skill\Business\Port\NavykRepository;
use App\Skill\Business\Port\PravaNavykov;
use App\Skill\Business\Port\UuidGenerator;
use App\Skill\Tests\Support\FakePravaNavykov;
use App\Skill\Tests\Support\FixedUuidGenerator;
use App\Skill\Tests\Support\InMemoryNavykRepository;
use Doctrine\DBAL\Connection;
use Lexik\Bundle\JWTAuthenticationBundle\Encoder\JWTEncoderInterface;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Test;
use Symfony\Bundle\FrameworkBundle\KernelBrowser;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;

final class SkillControllerTest extends WebTestCase
{
    private KernelBrowser $client;
    private FakePravaNavykov $prava;
    private InMemoryNavykRepository $repo;
    private Connection $db;
    private string $accountId;

    protected function setUp(): void
    {
        parent::setUp();

        $this->client = static::createClient();
        $this->client->disableReboot();

        $container = static::getContainer();

        $this->repo = new InMemoryNavykRepository();
        $this->prava = new FakePravaNavykov();
        $container->set(NavykRepository::class, $this->repo);
        $container->set(PravaNavykov::class, $this->prava);
        $container->set(UuidGenerator::class, new FixedUuidGenerator());

        $this->db = $container->get(Connection::class);
        $this->db->beginTransaction();

        $this->accountId = 'acc-' . uniqid('', true);
        $this->prava->setAccountId($this->accountId);

        $email = uniqid('skill_api_', true) . '@asd.ru';

        $this->db->insert('jwt_user', [
            'id' => $this->accountId,
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
            'Bearer ' . $encoder->encode(['username' => $email, 'password' => 'pass'])
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
     * @return iterable<string, array{string}>
     */
    public static function skillEndpoints(): iterable
    {
        yield 'spisok' => ['/api/skill/spisok'];
        yield 'read' => ['/api/skill/read'];
        yield 'sozdat' => ['/api/skill/sozdat'];
        yield 'izmenit' => ['/api/skill/izmenit'];
        yield 'udalit' => ['/api/skill/udalit'];
        yield 'prikrepit' => ['/api/skill/prikrepit'];
        yield 'otkrepit' => ['/api/skill/otkrepit'];
        yield 'versii' => ['/api/skill/versii'];
        yield 'effektivnie' => ['/api/skill/effektivnie'];
    }

    #[Test]
    #[DataProvider('skillEndpoints')]
    public function requests_without_jwt_are_rejected(string $path): void
    {
        $this->client->setServerParameter('HTTP_Authorization', '');

        [$status] = $this->zapros('POST', $path, []);

        self::assertSame(401, $status);
    }

    #[Test]
    public function invalid_scope_returns_422(): void
    {
        [$status] = $this->zapros('POST', '/api/skill/spisok', ['scope' => 'bogus']);

        self::assertSame(422, $status);
    }

    #[Test]
    public function create_project_without_projects_returns_422(): void
    {
        [$status] = $this->zapros('POST', '/api/skill/sozdat', [
            'name' => 'Project',
            'description' => '',
            'content' => 'c',
            'scope' => 'project',
            'projectIds' => [],
        ]);

        self::assertSame(422, $status);
    }

    #[Test]
    public function update_without_id_returns_422(): void
    {
        $this->prava->setAdmin(true);

        [$status] = $this->zapros('POST', '/api/skill/izmenit', ['id' => '', 'content' => 'x']);

        self::assertSame(422, $status);
    }

    #[Test]
    public function attach_without_projects_returns_422(): void
    {
        $this->prava->setAdmin(true);

        [$status] = $this->zapros('POST', '/api/skill/prikrepit', ['id' => 'x', 'projectIds' => []]);

        self::assertSame(422, $status);
    }

    #[Test]
    public function detach_without_project_returns_422(): void
    {
        $this->prava->setAdmin(true);

        [$status] = $this->zapros('POST', '/api/skill/otkrepit', ['id' => 'x', 'projectId' => '']);

        self::assertSame(422, $status);
    }

    #[Test]
    public function non_admin_cannot_create_global_skill(): void
    {
        [$status, $body] = $this->zapros('POST', '/api/skill/sozdat', [
            'name' => 'Global',
            'description' => '',
            'content' => 'c',
            'scope' => 'global',
        ]);

        self::assertSame(403, $status);
        self::assertSame('skill.forbidden', $body['data']['message'] ?? null);
    }

    #[Test]
    public function validation_error_returns_422(): void
    {
        $this->prava->setAdmin(true);

        [$status] = $this->zapros('POST', '/api/skill/sozdat', [
            'name' => '',
            'description' => '',
            'content' => 'c',
            'scope' => 'global',
        ]);

        self::assertSame(422, $status);
    }

    #[Test]
    public function global_skill_full_flow(): void
    {
        $this->prava->setAdmin(true);

        [$createStatus, $created] = $this->zapros('POST', '/api/skill/sozdat', [
            'name' => 'Global Skill',
            'description' => 'desc',
            'content' => 'v1',
            'scope' => 'global',
        ]);

        self::assertSame(200, $createStatus);
        $id = (string) $created['data']['id'];
        self::assertSame('global-skill', $created['data']['slug']);

        [$listStatus, $list] = $this->zapros('POST', '/api/skill/spisok', ['scope' => 'global']);
        self::assertSame(200, $listStatus);
        self::assertCount(1, $list['data']);

        [$readStatus, $detail] = $this->zapros('POST', '/api/skill/read', ['id' => $id]);
        self::assertSame(200, $readStatus);
        self::assertSame('v1', $detail['data']['content']);
        self::assertTrue($detail['data']['canEdit']);

        [$editStatus, $edited] = $this->zapros('POST', '/api/skill/izmenit', [
            'id' => $id,
            'content' => 'v2',
        ]);
        self::assertSame(200, $editStatus);
        self::assertSame(2, $edited['data']['version']);

        [$versionsStatus, $versions] = $this->zapros('POST', '/api/skill/versii', ['id' => $id]);
        self::assertSame(200, $versionsStatus);
        self::assertCount(2, $versions['data']);

        [$deleteStatus] = $this->zapros('POST', '/api/skill/udalit', ['id' => $id]);
        self::assertSame(200, $deleteStatus);

        [$afterStatus, $after] = $this->zapros('POST', '/api/skill/spisok', ['scope' => 'global']);
        self::assertSame(200, $afterStatus);
        self::assertCount(0, $after['data']);
    }

    #[Test]
    public function project_skill_attach_detach_and_effective_flow(): void
    {
        $this->prava->setRol('project-a', 'ADMIN');
        $this->prava->setRol('project-b', 'ADMIN');

        [$createStatus, $created] = $this->zapros('POST', '/api/skill/sozdat', [
            'name' => 'Project Skill',
            'description' => '',
            'content' => 'project content',
            'scope' => 'project',
            'projectIds' => ['project-a'],
        ]);

        self::assertSame(200, $createStatus);
        $id = (string) $created['data']['id'];

        [$listStatus, $list] = $this->zapros('POST', '/api/skill/spisok', [
            'scope' => 'project',
            'projectId' => 'project-a',
        ]);
        self::assertSame(200, $listStatus);
        self::assertCount(1, $list['data']);

        [$attachStatus, $attached] = $this->zapros('POST', '/api/skill/prikrepit', [
            'id' => $id,
            'projectIds' => ['project-b'],
        ]);
        self::assertSame(200, $attachStatus);
        self::assertSame(['project-a', 'project-b'], $attached['data']['projectIds']);

        [$detachStatus, $detached] = $this->zapros('POST', '/api/skill/otkrepit', [
            'id' => $id,
            'projectId' => 'project-b',
        ]);
        self::assertSame(200, $detachStatus);
        self::assertSame(['project-a'], $detached['data']['projectIds']);

        [$effectiveStatus, $effective] = $this->zapros('POST', '/api/skill/effektivnie', [
            'projectIds' => ['project-a'],
        ]);
        self::assertSame(200, $effectiveStatus);
        self::assertCount(1, $effective['data']['skills']);
        self::assertSame('project', $effective['data']['skills'][0]['scope']);
        self::assertSame(64, strlen($effective['data']['manifestHash']));
    }

    #[Test]
    public function project_read_is_forbidden_for_non_member(): void
    {
        $this->prava->setRol('project-a', 'ADMIN');

        [, $created] = $this->zapros('POST', '/api/skill/sozdat', [
            'name' => 'Project Skill',
            'description' => '',
            'content' => 'c',
            'scope' => 'project',
            'projectIds' => ['project-a'],
        ]);

        // Убираем роль из project-a: пользователь не участник.
        $this->prava->udalitRol('project-a');

        [$status] = $this->zapros('POST', '/api/skill/read', ['id' => $created['data']['id']]);

        self::assertSame(403, $status);
    }

    /**
     * @param array<string, mixed> $body
     *
     * @return array{0: int, 1: array<string, mixed>}
     */
    private function zapros(string $method, string $path, array $body): array
    {
        $this->client->request(
            $method,
            $path,
            [],
            [],
            ['CONTENT_TYPE' => 'application/json'],
            json_encode($body, JSON_THROW_ON_ERROR),
        );

        $content = json_decode((string) $this->client->getResponse()->getContent(), true);

        return [
            $this->client->getResponse()->getStatusCode(),
            is_array($content) ? $content : [],
        ];
    }
}
