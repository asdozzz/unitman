<?php

declare(strict_types=1);

namespace App\OpenCode\Tests\Api;

use App\OpenCode\Business\Model\HodOpenCode;
use App\OpenCode\Business\Model\SessiyaOpenCode;
use App\OpenCode\Business\Port\HodOpenCodeRepository;
use App\OpenCode\Business\Port\SessiyaOpenCodeRepository;
use App\OpenCode\Business\Port\UmeetUpravlyatFaylamiWorkspace;
use App\OpenCode\Tests\Support\FabrikaWorkspace;
use App\OpenCode\Tests\Support\ImitaciyaUpravleniyaFaylamiWorkspace;
use Doctrine\DBAL\Connection;
use Lexik\Bundle\JWTAuthenticationBundle\Encoder\JWTEncoderInterface;
use PHPUnit\Framework\Attributes\Test;
use Symfony\Bundle\FrameworkBundle\KernelBrowser;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;

/**
 * HTTP-контракт owner-checked ссылки и текста файлов-артефактов.
 *
 * JWT проверяется по-настоящему (реальная строка `jwt_user`), репозитории
 * хода/сессии и файловый порт подменены. Чужой пользователь, чужой ход и файл
 * другого хода обязаны давать одинаковый 404.
 */
final class OpenCodeFileControllerTest extends WebTestCase
{
    private const TURN_ID = 'turn-1';
    private const TURN_ID_2 = 'turn-2';
    private const WORKSPACE_ID = 'workspace-1';

    private KernelBrowser $client;

    private Connection $db;

    private JWTEncoderInterface $encoder;

    private ImitaciyaUpravleniyaFaylamiWorkspace $files;

    private SessiyaOpenCode $session;

    private HodOpenCode $turn;

    private HodOpenCode $turn2;

    private string $fileId;

    private string $accountId;

    private string $email;

    protected function setUp(): void
    {
        parent::setUp();

        $this->client = static::createClient();
        $this->client->disableReboot();

        $container = static::getContainer();

        $this->db = $container->get(Connection::class);
        $this->db->beginTransaction();

        $this->accountId = 'acc-' . uniqid('', true);
        $this->email = uniqid('file_api_', true) . '@asd.ru';

        $this->db->insert('jwt_user', [
            'id' => $this->accountId,
            'email' => $this->email,
            'password' => 'pass',
            'roles' => 'ROLE_USER',
            'is_blocked' => 0,
            'locale' => 'ru',
            'nickname' => null,
        ]);

        $now = FabrikaWorkspace::NOW;

        $this->session = SessiyaOpenCode::createWorkspace(
            self::WORKSPACE_ID,
            $this->accountId,
            'cred-1',
            'payments-refactor',
            'feature/payments',
            [FabrikaWorkspace::proekt()],
            'Первый промпт',
            $now,
        );

        $this->turn = HodOpenCode::create(self::TURN_ID, self::WORKSPACE_ID, 1, 'workflow-1', 'Привет', [], $now);
        $this->turn2 = HodOpenCode::create(self::TURN_ID_2, self::WORKSPACE_ID, 2, 'workflow-2', 'Ещё', [], $now);

        $this->files = new ImitaciyaUpravleniyaFaylamiWorkspace();
        $this->fileId = $this->files->zaregistrirovatFajlHoda(
            self::WORKSPACE_ID,
            $this->accountId,
            self::TURN_ID,
            'plan.md',
            'text/markdown',
            '# План',
            $now,
        )->fileId;
        $container->set(UmeetUpravlyatFaylamiWorkspace::class, $this->files);

        $turns = $this->createMock(HodOpenCodeRepository::class);
        $turns->method('findById')->willReturnCallback(
            fn (string $id): ?HodOpenCode => match ($id) {
                self::TURN_ID => $this->turn,
                self::TURN_ID_2 => $this->turn2,
                default => null,
            },
        );
        $container->set(HodOpenCodeRepository::class, $turns);

        $sessions = $this->createMock(SessiyaOpenCodeRepository::class);
        $sessions->method('findById')->willReturnCallback(
            fn (string $id): ?SessiyaOpenCode => $id === self::WORKSPACE_ID ? $this->session : null,
        );
        $container->set(SessiyaOpenCodeRepository::class, $sessions);

        $this->encoder = $container->get(JWTEncoderInterface::class);
        \assert($this->encoder instanceof JWTEncoderInterface);

        $this->avtorizovat($this->email);
    }

    protected function tearDown(): void
    {
        if (isset($this->db)) {
            $this->db->rollBack();
        }

        parent::tearDown();
    }

    #[Test]
    public function ssylka_na_fajl_vozvrashchaet_metadannye_i_url(): void
    {
        $data = $this->dannye($this->zapros('/api/opencode/fajl/ssylka', self::TURN_ID, $this->fileId));

        self::assertSame($this->fileId, $data['fileId']);
        self::assertSame('plan.md', $data['name']);
        self::assertSame('text/markdown', $data['contentType']);
        self::assertSame(strlen('# План'), $data['size']);
        self::assertSame('https://example.test/hod/' . $this->fileId, $data['url']);
    }

    #[Test]
    public function tekst_fajla_vozvrashchaet_soderzhimoe(): void
    {
        $data = $this->dannye($this->zapros('/api/opencode/fajl/tekst', self::TURN_ID, $this->fileId));

        self::assertSame($this->fileId, $data['fileId']);
        self::assertSame('plan.md', $data['name']);
        self::assertSame('text/markdown', $data['contentType']);
        self::assertSame('# План', $data['content']);
    }

    #[Test]
    public function chuzhoj_polzovatel_poluchaet_404(): void
    {
        $chuzhojEmail = uniqid('file_api_foreign_', true) . '@asd.ru';

        $this->db->insert('jwt_user', [
            'id' => 'acc-' . uniqid('', true),
            'email' => $chuzhojEmail,
            'password' => 'pass',
            'roles' => 'ROLE_USER',
            'is_blocked' => 0,
            'locale' => 'ru',
            'nickname' => null,
        ]);

        $this->avtorizovat($chuzhojEmail);

        $this->zapros('/api/opencode/fajl/ssylka', self::TURN_ID, $this->fileId);

        self::assertResponseStatusCodeSame(404);
    }

    #[Test]
    public function neizvestnyj_hod_daet_404(): void
    {
        $this->zapros('/api/opencode/fajl/ssylka', 'missing-turn', $this->fileId);

        self::assertResponseStatusCodeSame(404);
    }

    #[Test]
    public function fajl_iz_drugogo_hoda_daet_404(): void
    {
        $otherFileId = $this->files->zaregistrirovatFajlHoda(
            self::WORKSPACE_ID,
            $this->accountId,
            self::TURN_ID_2,
            'other.md',
            'text/markdown',
            '# Другой',
            FabrikaWorkspace::NOW,
        )->fileId;

        $this->zapros('/api/opencode/fajl/tekst', self::TURN_ID, $otherFileId);

        self::assertResponseStatusCodeSame(404);
    }

    #[Test]
    public function nepreview_tip_daet_422(): void
    {
        $fileId = $this->files->zaregistrirovatFajlHoda(
            self::WORKSPACE_ID,
            $this->accountId,
            self::TURN_ID,
            'plan.pdf',
            'application/pdf',
            '%PDF',
            FabrikaWorkspace::NOW,
        )->fileId;

        $this->zapros('/api/opencode/fajl/tekst', self::TURN_ID, $fileId);

        self::assertResponseStatusCodeSame(422);
    }

    #[Test]
    public function bolshoj_fajl_daet_422(): void
    {
        $fileId = $this->files->zaregistrirovatFajlHoda(
            self::WORKSPACE_ID,
            $this->accountId,
            self::TURN_ID,
            'big.md',
            'text/markdown',
            str_repeat('a', 524_289),
            FabrikaWorkspace::NOW,
        )->fileId;

        $this->zapros('/api/opencode/fajl/tekst', self::TURN_ID, $fileId);

        self::assertResponseStatusCodeSame(422);
    }

    #[Test]
    public function nevalidnyj_utf8_daet_422(): void
    {
        $fileId = $this->files->zaregistrirovatFajlHoda(
            self::WORKSPACE_ID,
            $this->accountId,
            self::TURN_ID,
            'bad.md',
            'text/markdown',
            "\xC3\x28",
            FabrikaWorkspace::NOW,
        )->fileId;

        $this->zapros('/api/opencode/fajl/tekst', self::TURN_ID, $fileId);

        self::assertResponseStatusCodeSame(422);
    }

    #[Test]
    public function bez_jwt_daet_401(): void
    {
        $this->client->setServerParameters([]);
        $this->client->request(
            'POST',
            '/api/opencode/fajl/ssylka',
            [],
            [],
            ['CONTENT_TYPE' => 'application/json'],
            json_encode(['turnId' => self::TURN_ID, 'fileId' => 'file-1'], JSON_THROW_ON_ERROR),
        );

        self::assertResponseStatusCodeSame(401);
    }

    /**
     * @return array<string, mixed>
     */
    private function zapros(string $path, string $turnId, string $fileId): array
    {
        $this->client->request(
            'POST',
            $path,
            [],
            [],
            ['CONTENT_TYPE' => 'application/json'],
            json_encode(['turnId' => $turnId, 'fileId' => $fileId], JSON_THROW_ON_ERROR),
        );

        $content = json_decode((string) $this->client->getResponse()->getContent(), true);

        return is_array($content) ? $content : [];
    }

    /**
     * @param array<string, mixed> $otvet
     *
     * @return array<string, mixed>
     */
    private function dannye(array $otvet): array
    {
        self::assertSame('success', $otvet['status'] ?? null);
        self::assertIsArray($otvet['data'] ?? null);

        /** @var array<string, mixed> $data */
        $data = $otvet['data'];

        return $data;
    }

    private function avtorizovat(string $email): void
    {
        $this->client->setServerParameter(
            'HTTP_Authorization',
            'Bearer ' . $this->encoder->encode(['username' => $email, 'password' => 'pass']),
        );
    }
}
