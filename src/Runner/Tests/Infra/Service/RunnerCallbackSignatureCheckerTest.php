<?php

declare(strict_types=1);

namespace App\Runner\Tests\Infra\Service;

use App\Runner\Infra\Service\RunnerCallbackSignatureChecker;
use PHPUnit\Framework\TestCase;

/**
 * `saveSteps` — публичный эндпоинт, поэтому HMAC-подпись является единственной
 * защитой от подделки прогресса. Тесты фиксируют все ветки проверки, включая
 * режим «секрет не задан» (поэтапный выкат: сначала бэкенд, потом раннер).
 */
final class RunnerCallbackSignatureCheckerTest extends TestCase
{
    private const SECRET = 'super-secret-value';
    private const TOKEN = 'job-42';
    private const CONTENT = '[{"Command":"docker build","Success":true}]';

    public function test_vernaya_podpis_prinimaetsya(): void
    {
        $checker = new RunnerCallbackSignatureChecker(self::SECRET);

        $this->assertTrue(
            $checker->estPodpisValidna(self::TOKEN, self::CONTENT, $this->sign(self::TOKEN, self::CONTENT)),
        );
    }

    public function test_podpis_ot_drugogo_sekreta_otvergaetsya(): void
    {
        $checker = new RunnerCallbackSignatureChecker(self::SECRET);

        $this->assertFalse(
            $checker->estPodpisValidna(self::TOKEN, self::CONTENT, $this->sign(self::TOKEN, self::CONTENT, 'other')),
        );
    }

    public function test_podpis_ne_sootvetstvuet_izmenennomu_tele_otvergaetsya(): void
    {
        $checker = new RunnerCallbackSignatureChecker(self::SECRET);
        $signature = $this->sign(self::TOKEN, self::CONTENT);

        $this->assertFalse(
            $checker->estPodpisValidna(self::TOKEN, self::CONTENT . ' ', $signature),
        );
    }

    public function test_podpis_ne_sootvetstvuet_drugomu_tokenu_otvergaetsya(): void
    {
        $checker = new RunnerCallbackSignatureChecker(self::SECRET);
        $signature = $this->sign(self::TOKEN, self::CONTENT);

        $this->assertFalse(
            $checker->estPodpisValidna('another-job', self::CONTENT, $signature),
        );
    }

    public function test_otsutstvie_podpisi_otvergaetsya(): void
    {
        $checker = new RunnerCallbackSignatureChecker(self::SECRET);

        $this->assertFalse($checker->estPodpisValidna(self::TOKEN, self::CONTENT, null));
        $this->assertFalse($checker->estPodpisValidna(self::TOKEN, self::CONTENT, ''));
    }

    public function test_pustoi_sekret_otklyuchaet_proverku(): void
    {
        $checker = new RunnerCallbackSignatureChecker('');

        // Пустой секрет — режим совместимости: подпись не проверяется вообще,
        // иначе старые раннеры перестали бы присылать шаги после деплоя бэкенда.
        $this->assertTrue($checker->estPodpisValidna(self::TOKEN, self::CONTENT, null));
        $this->assertTrue($checker->estPodpisValidna(self::TOKEN, self::CONTENT, 'any-garbage'));
    }

    public function test_podpis_v_verhnem_registre_otvergaetsya(): void
    {
        $checker = new RunnerCallbackSignatureChecker(self::SECRET);
        $signature = $this->sign(self::TOKEN, self::CONTENT);

        // hash_equals чувствителен к регистру: заголовок должен присылаться
        // ровно так, как его отдаёт hex-кодирование hash_hmac.
        $this->assertFalse(
            $checker->estPodpisValidna(self::TOKEN, self::CONTENT, strtoupper($signature)),
        );
    }

    public function test_podpis_dlya_pustogo_tele_validna(): void
    {
        $checker = new RunnerCallbackSignatureChecker(self::SECRET);
        $signature = $this->sign(self::TOKEN, '');

        // Раннер может прислать пустой снапшот в самом начале задачи.
        $this->assertTrue($checker->estPodpisValidna(self::TOKEN, '', $signature));
    }

    private function sign(string $token, string $content, string $secret = self::SECRET): string
    {
        return hash_hmac('sha256', $token . $content, $secret);
    }
}
