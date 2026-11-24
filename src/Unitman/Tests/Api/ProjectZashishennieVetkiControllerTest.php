<?php

declare(strict_types=1);

namespace App\Unitman\Tests\Api;

use App\Unitman\Business\Command\Project\AddProject;
use App\Unitman\Business\Model\Project;
use App\Unitman\Business\ReadModel\ProjectList;
use App\Unitman\Business\ReadModel\ProjectList\NastroikiHukaProekta;
use App\Unitman\Business\ReadModel\ProjectList\ProjectListStateType;
use App\Unitman\Infra\Repository\Project\SqlProjectEventsRepository;
use App\Unitman\Infra\Repository\Project\SqlProjectListRepository;
use Doctrine\DBAL\Connection;
use Lexik\Bundle\JWTAuthenticationBundle\Encoder\JWTEncoderInterface;
use PHPUnit\Framework\Attributes\Test;
use Ramsey\Uuid\Uuid;
use Symfony\Bundle\FrameworkBundle\KernelBrowser;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;

/**
 * HTTP-контракт защищённых веток проекта.
 */
final class ProjectZashishennieVetkiControllerTest extends WebTestCase
{
    private KernelBrowser $client;

    private Connection $db;

    private string $adminEmail;

    private string $userEmail;

    private string $adminId;

    protected function setUp(): void
    {
        parent::setUp();

        $this->client = static::createClient();
        $this->client->disableReboot();

        $container = static::getContainer();
        $this->db = $container->get(Connection::class);
        $this->db->beginTransaction();

        $this->adminId = 'acc-admin-' . uniqid('', true);
        $this->adminEmail = uniqid('project_admin_', true) . '@asd.ru';
        $this->userEmail = uniqid('project_user_', true) . '@asd.ru';

        $this->insertUser($this->adminId, $this->adminEmail, 'ROLE_ADMIN');
        $this->insertUser('acc-user-' . uniqid('', true), $this->userEmail, 'ROLE_USER');

        $listRepository = $container->get(SqlProjectListRepository::class);
        \assert($listRepository instanceof SqlProjectListRepository);
        $listRepository->init();
    }

    protected function tearDown(): void
    {
        if (isset($this->db)) {
            $this->db->rollBack();
        }

        parent::tearDown();
    }

    #[Test]
    public function read_vozvrashchaet_zashishennie_vetki(): void
    {
        $projectId = Uuid::uuid7()->toString();
        $repository = static::getContainer()->get(SqlProjectListRepository::class);
        \assert($repository instanceof SqlProjectListRepository);

        $repository->insert(new ProjectList(
            $projectId,
            Uuid::uuid7()->toString(),
            'unitman-tests/' . $projectId,
            'Project-' . $projectId,
            'main',
            true,
            ProjectListStateType::NEW,
            new NastroikiHukaProekta(false, false, false),
            [],
            [],
            false,
            3072,
            ['main', 'release/*'],
        ));

        $this->authenticateAsAdmin();

        $this->client->request('POST', '/api/project/read/' . $projectId);

        $this->assertResponseIsSuccessful();

        $content = json_decode((string) $this->client->getResponse()->getContent(), true);
        self::assertIsArray($content);
        self::assertSame('success', $content['status'] ?? null);
        self::assertSame(['main', 'release/*'], $content['data']['zashishennieVetki'] ?? null);
    }

    #[Test]
    public function ne_admin_ne_mozhet_ustanovit_zashishennie_vetki(): void
    {
        $projectId = $this->sozdatProekt();

        $this->authenticate($this->userEmail);

        $this->client->request(
            'POST',
            '/api/project/ustanovitZashishennieVetki',
            [],
            [],
            ['CONTENT_TYPE' => 'application/json'],
            json_encode(['id' => $projectId, 'zashishennieVetki' => ['*']], JSON_THROW_ON_ERROR),
        );

        self::assertFalse($this->client->getResponse()->isSuccessful());

        $content = json_decode((string) $this->client->getResponse()->getContent(), true);
        self::assertIsArray($content);
        self::assertContains($content['status'] ?? null, ['fail', 'error']);
    }

    #[Test]
    public function admin_ustanavlivaet_zashishennie_vetki_cherez_endpoint(): void
    {
        $projectId = $this->sozdatProekt();

        $this->authenticateAsAdmin();

        $this->client->request(
            'POST',
            '/api/project/ustanovitZashishennieVetki',
            [],
            [],
            ['CONTENT_TYPE' => 'application/json'],
            json_encode(['id' => $projectId, 'zashishennieVetki' => ['main', 'release/*']], JSON_THROW_ON_ERROR),
        );

        $this->assertResponseIsSuccessful();

        $content = json_decode((string) $this->client->getResponse()->getContent(), true);
        self::assertIsArray($content);
        self::assertSame('success', $content['status'] ?? null);

        $repository = static::getContainer()->get(SqlProjectListRepository::class);
        \assert($repository instanceof SqlProjectListRepository);

        self::assertSame(['main', 'release/*'], $repository->getById($projectId)->zashishennieVetki);
    }

    private function sozdatProekt(): string
    {
        $projectId = Uuid::uuid7()->toString();

        $project = Project::addProject(
            $projectId,
            new AddProject(Uuid::uuid7()->toString(), 'unitman-tests/' . $projectId, 'Project-' . $projectId, 'main',
                300,
            ),
            $this->adminId,
        );

        $repository = static::getContainer()->get(SqlProjectEventsRepository::class);
        \assert($repository instanceof SqlProjectEventsRepository);
        $repository->save($project);

        return $projectId;
    }

    private function authenticateAsAdmin(): void
    {
        $this->authenticate($this->adminEmail);
    }

    private function authenticate(string $email): void
    {
        $encoder = static::getContainer()->get(JWTEncoderInterface::class);
        \assert($encoder instanceof JWTEncoderInterface);

        $this->client->setServerParameter(
            'HTTP_Authorization',
            'Bearer ' . $encoder->encode(['username' => $email, 'password' => 'pass']),
        );
    }

    private function insertUser(string $id, string $email, string $role): void
    {
        $this->db->insert('jwt_user', [
            'id' => $id,
            'email' => $email,
            'password' => 'pass',
            'roles' => $role,
            'is_blocked' => 0,
            'locale' => 'ru',
            'nickname' => null,
        ]);
    }
}
