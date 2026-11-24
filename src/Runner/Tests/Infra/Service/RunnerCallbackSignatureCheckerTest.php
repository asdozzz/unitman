<?php

declare(strict_types=1);

namespace App\Runner\Tests\Infra\Service;

use App\Runner\Business\Model\RunnerState;
use App\Runner\Business\Utils\RunnerSecretGenerator;
use App\Runner\Infra\Service\RunnerCallbackSignatureChecker;
use App\Runner\Tests\Support\InMemoryRunnerRepository;
use PHPUnit\Framework\TestCase;

/**
 * Callback-подпись v2: секрет детерминированно вычисляется по X-Runner-Id,
 * поэтому валидность подписи зависит и от payload, и от самого раннера.
 */
final class RunnerCallbackSignatureCheckerTest extends TestCase
{
    private const MASTER_KEY = 'super-secret-value';
    private const RUNNER_ID = 'runner-1';
    private const TOKEN = 'job-42';
    private const CONTENT = '[{"Command":"docker build","Success":true}]';

    public function test_vernaya_podpis_tela_prinimaetsya(): void
    {
        $checker = $this->checker();

        $this->assertTrue(
            $checker->estPodpisTelaValidna(self::RUNNER_ID, self::CONTENT, $this->signBody(self::CONTENT)),
        );
    }

    public function test_podpis_tela_ot_drugogo_sekreta_otvergaetsya(): void
    {
        $checker = $this->checker();

        $wrong = hash_hmac('sha256', self::CONTENT, 'other-master-key');

        $this->assertFalse($checker->estPodpisTelaValidna(self::RUNNER_ID, self::CONTENT, $wrong));
    }

    public function test_vernaya_podpis_save_steps_prinimaetsya(): void
    {
        $checker = $this->checker();

        $this->assertTrue(
            $checker->estPodpisValidna(
                self::RUNNER_ID,
                self::TOKEN,
                self::CONTENT,
                $this->signSteps(self::TOKEN, self::CONTENT),
            ),
        );
    }

    public function test_podpis_save_steps_ot_izmenennogo_tele_otvergaetsya(): void
    {
        $checker = $this->checker();
        $signature = $this->signSteps(self::TOKEN, self::CONTENT);

        $this->assertFalse(
            $checker->estPodpisValidna(self::RUNNER_ID, self::TOKEN, self::CONTENT . ' ', $signature),
        );
    }

    public function test_podpis_save_steps_ot_drugogo_tokena_otvergaetsya(): void
    {
        $checker = $this->checker();
        $signature = $this->signSteps(self::TOKEN, self::CONTENT);

        $this->assertFalse(
            $checker->estPodpisValidna(self::RUNNER_ID, 'another-job', self::CONTENT, $signature),
        );
    }

    public function test_podpis_pustogo_tela_prinimaetsya_dlya_get_build_state(): void
    {
        $checker = $this->checker();

        $this->assertTrue(
            $checker->estPodpisPustogoTelaValidna(self::RUNNER_ID, $this->signBody('')),
        );
    }

    public function test_otsutstvie_podpisi_otvergaetsya(): void
    {
        $checker = $this->checker();

        $this->assertFalse($checker->estPodpisTelaValidna(self::RUNNER_ID, self::CONTENT, null));
        $this->assertFalse($checker->estPodpisTelaValidna(self::RUNNER_ID, self::CONTENT, ''));
        $this->assertFalse($checker->estPodpisValidna(self::RUNNER_ID, self::TOKEN, self::CONTENT, null));
    }

    public function test_podpis_v_verhnem_registre_otvergaetsya(): void
    {
        $checker = $this->checker();

        $this->assertFalse(
            $checker->estPodpisTelaValidna(
                self::RUNNER_ID,
                self::CONTENT,
                strtoupper($this->signBody(self::CONTENT)),
            ),
        );
    }

    public function test_neizvestnyi_runner_daet_not_found(): void
    {
        $checker = $this->checker();

        $this->expectException(\DomainException::class);
        $this->expectExceptionMessage('runner.not_found');

        $checker->estPodpisTelaValidna('unknown-runner', self::CONTENT, 'deadbeef');
    }

    public function test_otsutstvie_runner_id_daet_not_found(): void
    {
        $checker = $this->checker();

        $this->expectException(\DomainException::class);
        $this->expectExceptionMessage('runner.not_found');

        $checker->estPodpisTelaValidna(null, self::CONTENT, 'deadbeef');
    }

    private function checker(): RunnerCallbackSignatureChecker
    {
        $repository = new InMemoryRunnerRepository();
        $repository->save(RunnerState::sozdatRunner(self::RUNNER_ID, self::RUNNER_ID));

        return new RunnerCallbackSignatureChecker(
            $repository,
            new RunnerSecretGenerator(self::MASTER_KEY),
        );
    }

    private function signBody(string $body): string
    {
        return hash_hmac('sha256', $body, $this->runnerSecret());
    }

    private function signSteps(string $token, string $content): string
    {
        return hash_hmac('sha256', $token . $content, $this->runnerSecret());
    }

    private function runnerSecret(): string
    {
        return hash_hmac('sha256', self::RUNNER_ID, self::MASTER_KEY);
    }
}
