<?php

declare(strict_types=1);

namespace App\AiChat\Tests\Support;

use App\Account\Business\Model\OpenCode\ProviderSessionPolicy;
use App\AiChat\Business\Port\OtpravitelChatZaprosov;

/**
 * Фейковый отправитель chat-запросов.
 *
 * Запоминает аргументы последнего вызова и позволяет задать готовый ответ
 * upstream или исключение. Счётчик вызовов используется, чтобы проверить, что
 * при доменных ошибках сетевой запрос не делается.
 */
final class RecordingOtpravitelChatZaprosov implements OtpravitelChatZaprosov
{
    public int $yzovov = 0;

    /**
     * @var array{
     *     baseUrl?: string,
     *     apiKey?: string,
     *     model?: string,
     *     messages?: list<array<string, mixed>>,
     *     tools?: list<array<string, mixed>>,
     *     provider?: string,
     *     sessionId?: ?string
     * }
     */
    public array $poslednie = [];

    /**
     * @var array{status: int, payload: array<array-key, mixed>}
     */
    public array $otvet = ['status' => 200, 'payload' => ['choices' => []]];

    public ?\Throwable $oshibka = null;

    public function otpravit(
        string $baseUrl,
        string $apiKey,
        string $model,
        array $messages,
        array $tools,
        string $provider,
        ProviderSessionPolicy $policy,
        ?string $sessionId = null,
    ): array {
        ++$this->yzovov;
        $this->poslednie = [
            'baseUrl' => $baseUrl,
            'apiKey' => $apiKey,
            'model' => $model,
            'messages' => $messages,
            'tools' => $tools,
            'provider' => $provider,
            'sessionId' => $sessionId,
        ];

        if ($this->oshibka !== null) {
            throw $this->oshibka;
        }

        return $this->otvet;
    }
}
