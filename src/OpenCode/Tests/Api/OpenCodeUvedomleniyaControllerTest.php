 и      <?php

declare(strict_types=1);

namespace App\OpenCode\Tests\Api;

use Doctrine\DBAL\Connection;
use Lexik\Bundle\JWTAuthenticationBundle\Encoder\JWTEncoderInterface;
use PHPUnit\Framework\Attributes\Test;
use Symfony\Bundle\FrameworkBundle\KernelBrowser;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;

/**
 * HTTP-контракт уведомлений OpenCode.
 *
 * Проверяется фильтрация по аккаунту, форма ответа раздела 6.1 и семантика
 * захвата раздела 6.2: в ответ попадают только id, реально переведённые
 * этим запросом в прочитанные.
 */
final class OpenCodeUvedomleniyaControllerTest extends WebTestCase
{
    private const CHANNEL_PREFIX = 'opencode_notifications#';

    private KernelBrowser $client;

    private Connection $db;

    private string $accountId;

    protected function setUp(): void
    {
        parent::setUp();

        $this->client = static::createClient();
        $this->client->disableReboot();

        $container = static::getContainer();
        $this->db = $container->get(Connection::class);
        $this->db->beginTransaction();

        $this->accountId = 'acc-' . uniqid('', true);
        $email = uniqid('opencode_notifications_', true) . '@asd.ru';

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

    #[Test]
    public function spisok_soderzhit_kanal_i_tolko_neprochitannie_svoego_akkaunta(): void
    {
        $this->trebovatTablitsuUvedomlenii();

        $now = time();

        $this->vstavitUvedomlenie('n-1', $this->accountId, 't-1');
        $this->vstavitUvedomlenie('n-2', $this->accountId, 't-2', $now);
        $this->vstavitUvedomlenie('n-3', 'another-account', 't-3');

        $data = $this->dannye($this->zapros('GET', '/api/opencode/uvedomleniya?limit=10'));

        $this->assertIsArray($data);
        $this->assertArrayHasKey('channel', $data);
        $this->assertStringStartsWith(self::CHANNEL_PREFIX, (string) $data['channel']);

        $this->assertIsArray($data['notifications']);
        $this->assertCount(1, $data['notifications'], 'Отдаются только непрочитанные уведомления своего аккаунта');

        $notification = $data['notifications'][0];
        $this->assertIsArray($notification);
        $this->assertSame('n-1', $notification['id']);
        $this->assertSame('new_message', $notification['eventType']);
        $this->assertSame('t-1', $notification['turnId']);
        $this->assertSame('s-1', $notification['sessionId']);
        $this->assertSame('ANSWERED', $notification['status']);
        $this->assertSame('ANSWER', $notification['kind']);
        $this->assertArrayNotHasKey('accountId', $notification);
    }

    #[Test]
    public function zahvat_vozvrashhaet_tolko_realno_prochitannie_id(): void
    {
        $this->trebovatTablitsuUvedomlenii();

        $now = time();

        $this->vstavitUvedomlenie('n-1', $this->accountId, 't-1');
        $this->vstavitUvedomlenie('n-2', $this->accountId, 't-2');
        $this->vstavitUvedomlenie('n-3', $this->accountId, 't-3', $now);
        $this->vstavitUvedomlenie('n-4', 'another-account', 't-4');

        $data = $this->dannye($this->zapros(
            'POST',
            '/api/opencode/otmetitUvedomleniyaProchitannimi',
            ['ids' => ['n-1', 'n-3', 'n-4', 'n-1']],
        ));

        $this->assertIsArray($data);
        $this->assertSame(['n-1'], $data['ids']);

        $povtorno = $this->dannye($this->zapros(
            'POST',
            '/api/opencode/otmetitUvedomleniyaProchitannimi',
            ['ids' => ['n-1']],
        ));

        $this->assertIsArray($povtorno);
        $this->assertSame([], $povtorno['ids'], 'Повторный захват уже прочитанного ничего не возвращает');

        $spisok = $this->dannye($this->zapros('GET', '/api/opencode/uvedomleniya'));

        $this->assertIsArray($spisok);
        $this->assertSame(
            ['n-2'],
            array_map(
                static fn (array $notification): string => (string) $notification['id'],
                $spisok['notifications'],
            ),
        );
    }

    #[Test]
    public function pustoi_spisok_id_otklonyaetsya(): void
    {
        $message = $this->trebovatOtkaz($this->zapros(
            'POST',
            '/api/opencode/otmetitUvedomleniyaProchitannimi',
            ['ids' => []],
        ));

        $this->assertNotSame('', $message);
    }

    #[Test]
    public function bolee_sta_id_otklonyaetsya(): void
    {
        $ids = [];

        for ($i = 0; $i <= 100; ++$i) {
            $ids[] = 'n-' . $i;
        }

        $message = $this->trebovatOtkaz($this->zapros(
            'POST',
            '/api/opencode/otmetitUvedomleniyaProchitannimi',
            ['ids' => $ids],
        ));

        $this->assertNotSame('', $message);
    }

    /**
     * @param array<string, mixed>|null $telo
     *
     * @return array<string, mixed>
     */
    private function zapros(string $method, string $path, ?array $telo = null): array
    {
        $this->client->request(
            $method,
            $path,
            [],
            [],
            ['CONTENT_TYPE' => 'application/json'],
            $telo === null ? null : json_encode($telo, JSON_THROW_ON_ERROR),
        );

        $content = json_decode((string) $this->client->getResponse()->getContent(), true);

        $this->assertIsArray($content, 'Ответ должен быть JSON-конвертом');

        return $content;
    }

    /**
     * @param array<string, mixed> $otvet
     *
     * @return mixed
     */
    private function dannye(array $otvet)
    {
        $this->assertSame(
            'success',
            $otvet['status'] ?? null,
            json_encode($otvet, JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR),
        );
        $this->assertArrayHasKey('data', $otvet);

        return $otvet['data'];
    }

    private function trebovatOtkaz(array $otvet): string
    {
        $this->assertSame(
            'fail',
            $otvet['status'] ?? null,
            json_encode($otvet, JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR),
        );
        $this->assertResponseStatusCodeSame(500);

        $message = $otvet['data']['message'] ?? null;

        $this->assertIsString($message);
        $this->assertNotSame('', $message);

        return $message;
    }

    /**
     * Таблица создаётся миграцией задачи A. Финальный интегратор применяет
     * миграции; до этого API-тесты, требующие БД, пропускаются.
     */
    private function trebovatTablitsuUvedomlenii(): void
    {
        if (!$this->db->createSchemaManager()->tablesExist(['opencode_notification'])) {
            self::markTestSkipped('Таблица opencode_notification ещё не создана миграцией.');
        }
    }

    private function vstavitUvedomlenie(
        string $id,
        string $accountId,
        string $turnId,
        ?int $readAt = null,
    ): void {
        $this->db->insert('opencode_notification', [
            'id' => $id,
            'account_id' => $accountId,
            'session_id' => 's-1',
            'turn_id' => $turnId,
            'session_title' => 'Сессия',
            'status' => 'ANSWERED',
            'kind' => 'ANSWER',
            'created_at' => 1_700_000_000,
            'read_at' => $readAt,
        ]);
    }
}
