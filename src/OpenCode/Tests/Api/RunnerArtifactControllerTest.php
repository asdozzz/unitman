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
use App\Runner\Business\Model\RunnerState;
use App\Runner\Business\Utils\RunnerSecretGenerator;
use App\Runner\Infra\Service\RunnerCallbackSignatureChecker;
use App\Runner\Tests\Support\InMemoryRunnerRepository;
use PHPUnit\Framework\Attributes\Test;
use Symfony\Bundle\FrameworkBundle\KernelBrowser;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;

/**
 * HTTP-контракт HMAC-загрузки артефактов раннером.
 *
 * Маршрут публичный по JWT, поэтому аккаунт в БД не нужен: аутентификация —
 * подпись раннера. Репозитории хода/сессии и файловый порт подменены, Temporal
 * и MinIO не поднимаются.
 */
final class RunnerArtifactControllerTest extends WebTestCase
{
    private const MASTER_KEY = 'test-artifact-runner-master-key';
    private const RUNNER_ID = 'runner-1';
    private const WORKFLOW_ID = 'workflow-1';
    private const TURN_ID = 'turn-1';
    private const WORKSPACE_ID = 'workspace-1';

    private KernelBrowser $client;

    private ImitaciyaUpravleniyaFaylamiWorkspace $files;

    private HodOpenCode $turn;

    private SessiyaOpenCode $session;

    protected function setUp(): void
    {
        parent::setUp();

        $this->client = static::createClient();
        $this->client->disableReboot();

        $container = static::getContainer();

        $repository = new InMemoryRunnerRepository();
        $repository->save(new RunnerState(self::RUNNER_ID, self::RUNNER_ID, true));

        $checker = new RunnerCallbackSignatureChecker(
            $repository,
            RunnerSecretGenerator::fromMasterKey(self::MASTER_KEY),
        );
        $container->set(RunnerCallbackSignatureChecker::class, $checker);

        $this->files = new ImitaciyaUpravleniyaFaylamiWorkspace();
        $container->set(UmeetUpravlyatFaylamiWorkspace::class, $this->files);

        $this->session = FabrikaWorkspace::sozdanie(self::WORKSPACE_ID);
        $this->turn = HodOpenCode::create(
            self::TURN_ID,
            self::WORKSPACE_ID,
            1,
            self::WORKFLOW_ID,
            'Привет',
            [],
            FabrikaWorkspace::NOW,
        );

        $turns = $this->createMock(HodOpenCodeRepository::class);
        $turns->method('findByWorkflowId')->willReturnCallback(
            fn (string $workflowId): ?HodOpenCode => $workflowId === self::WORKFLOW_ID ? $this->turn : null,
        );
        $turns->method('findById')->willReturnCallback(
            fn (string $id): ?HodOpenCode => $id === self::TURN_ID ? $this->turn : null,
        );
        $container->set(HodOpenCodeRepository::class, $turns);

        $sessions = $this->createMock(SessiyaOpenCodeRepository::class);
        $sessions->method('findById')->willReturnCallback(
            fn (string $id): ?SessiyaOpenCode => $id === $this->session->getId() ? $this->session : null,
        );
        $container->set(SessiyaOpenCodeRepository::class, $sessions);
    }

    #[Test]
    public function nevernaya_podpis_daet_401(): void
    {
        $this->otpravit('plan.md', '# План', signature: 'deadbeef');

        self::assertResponseStatusCodeSame(401);
    }

    #[Test]
    public function neizvestnyj_runner_daet_404(): void
    {
        $this->otpravit('plan.md', '# План', runnerId: 'unknown-runner');

        self::assertResponseStatusCodeSame(404);
    }

    #[Test]
    public function neizvestnyj_hod_daet_404(): void
    {
        $this->otpravit('plan.md', '# План', query: ['workflowId' => 'missing-workflow']);

        self::assertResponseStatusCodeSame(404);
    }

    #[Test]
    public function nezavershennyj_hod_daet_409(): void
    {
        $this->turn = HodOpenCode::restore(
            self::TURN_ID,
            self::WORKSPACE_ID,
            1,
            self::WORKFLOW_ID,
            'ANSWERED',
            'Привет',
            [],
            null,
            null,
            FabrikaWorkspace::NOW,
            FabrikaWorkspace::NOW,
        );

        $this->otpravit('plan.md', '# План');

        self::assertResponseStatusCodeSame(409);
    }

    #[Test]
    public function slishkom_bolshoe_telo_daet_413(): void
    {
        $this->otpravit('plan.md', str_repeat('a', 2_097_153));

        self::assertResponseStatusCodeSame(413);
    }

    #[Test]
    public function unit_sessiya_daet_422(): void
    {
        // Тот же id, но тип сессии — unit: проверка workspace_required
        // срабатывает раньше сверки workspaceId.
        $this->session = SessiyaOpenCode::create(
            self::WORKSPACE_ID,
            'account-1',
            'cred-1',
            null,
            [],
            FabrikaWorkspace::NOW,
        );

        $this->otpravit('plan.md', '# План');

        self::assertResponseStatusCodeSame(422);
    }

    #[Test]
    public function chuzhoj_workspace_daet_422(): void
    {
        $this->otpravit('plan.md', '# План', query: ['workspaceId' => 'other-workspace']);

        self::assertResponseStatusCodeSame(422);
    }

    #[Test]
    public function arhivnyj_workspace_daet_422(): void
    {
        $this->session->arhivirovat(SessiyaOpenCode::ARCHIVE_REASON_MANUAL, FabrikaWorkspace::NOW + 1);

        $this->otpravit('plan.md', '# План');

        self::assertResponseStatusCodeSame(422);
    }

    #[Test]
    public function skrytoe_imya_daet_422(): void
    {
        $this->otpravit('.env.md', '# План');

        self::assertResponseStatusCodeSame(422);
    }

    #[Test]
    public function traversal_imya_daet_422(): void
    {
        $this->otpravit('..', 'x');

        self::assertResponseStatusCodeSame(422);
    }

    #[Test]
    public function zapreshchennoe_rasshirenie_daet_422(): void
    {
        $this->otpravit('plan.html', '<html></html>');

        self::assertResponseStatusCodeSame(422);
    }

    #[Test]
    public function slomannyj_utf8_daet_422(): void
    {
        $this->otpravit('plan.md', "\xC3\x28");

        self::assertResponseStatusCodeSame(422);
    }

    #[Test]
    public function nul_bajt_daet_422(): void
    {
        $this->otpravit('plan.md', "a\0b");

        self::assertResponseStatusCodeSame(422);
    }

    #[Test]
    public function shestoj_fajl_daet_422(): void
    {
        for ($i = 1; $i <= 5; ++$i) {
            $this->files->zaregistrirovatFajlHoda(
                self::WORKSPACE_ID,
                'account-1',
                self::TURN_ID,
                'file-' . $i . '.md',
                'text/markdown',
                'x',
                FabrikaWorkspace::NOW,
            );
        }

        $this->otpravit('sixth.md', 'x');

        self::assertResponseStatusCodeSame(422);
    }

    #[Test]
    public function prevyshenie_summarnogo_razmera_daet_422(): void
    {
        $this->files->zaregistrirovatFajlHoda(
            self::WORKSPACE_ID,
            'account-1',
            self::TURN_ID,
            'big.md',
            'text/markdown',
            str_repeat('a', 8_388_608),
            FabrikaWorkspace::NOW,
        );

        $this->otpravit('small.md', 'a');

        self::assertResponseStatusCodeSame(422);
    }

    #[Test]
    public function uspeshnaya_zagruzka_vozvrashchaet_metadannye(): void
    {
        $otvet = $this->otpravit('plan.MD', '# План', query: ['contentType' => 'text/markdown']);

        self::assertResponseStatusCodeSame(200);
        self::assertSame('success', $otvet['status']);

        $data = $otvet['data'];
        self::assertIsArray($data);
        self::assertArrayHasKey('fileId', $data);
        self::assertSame('plan.md', $data['name'], 'Расширение нормализуется в нижний регистр');
        self::assertSame(strlen('# План'), $data['size']);
        self::assertSame('text/markdown', $data['contentType']);
        self::assertSame(self::WORKSPACE_ID, $data['workspaceId']);
        self::assertSame(self::TURN_ID, $data['turnId']);
        self::assertArrayHasKey('createdAt', $data);
    }

    #[Test]
    public function povtornaya_zagruzka_vozvrashchaet_tot_zhe_fileid(): void
    {
        $pervyj = $this->otpravit('plan.md', '# План');
        $vtoroj = $this->otpravit('plan.md', '# План');

        self::assertSame($pervyj['data']['fileId'], $vtoroj['data']['fileId']);
    }

    /**
     * @param array<string, string> $query
     *
     * @return array<string, mixed>
     */
    private function otpravit(
        string $name,
        string $body,
        array $query = [],
        ?string $signature = null,
        string $runnerId = self::RUNNER_ID,
    ): array {
        $parameters = array_merge([
            'workflowId' => self::WORKFLOW_ID,
            'workspaceId' => self::WORKSPACE_ID,
            'name' => $name,
        ], $query);

        $signature ??= hash_hmac(
            'sha256',
            $parameters['workflowId'] . "\n" . $parameters['name'] . "\n" . $body,
            $this->secret(),
        );

        $this->client->request(
            'POST',
            '/api/runner/opencode/artifact?' . http_build_query($parameters),
            [],
            [],
            [
                'CONTENT_TYPE' => 'application/octet-stream',
                'HTTP_X_RUNNER_ID' => $runnerId,
                'HTTP_X_RUNNER_SIGNATURE' => $signature,
            ],
            $body,
        );

        $content = json_decode((string) $this->client->getResponse()->getContent(), true);

        return is_array($content) ? $content : [];
    }

    private function secret(): string
    {
        return hash_hmac('sha256', self::RUNNER_ID, self::MASTER_KEY);
    }
}
