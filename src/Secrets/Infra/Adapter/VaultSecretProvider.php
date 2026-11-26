<?php

declare(strict_types=1);

namespace App\Secrets\Infra\Adapter;

use App\Secrets\Business\Exception\SecretNotFound;
use App\Secrets\Business\Exception\SecretProviderNotConfigured;
use App\Secrets\Business\Exception\SecretProviderUnavailable;
use App\Secrets\Business\Exception\UnsupportedSecretOperation;
use App\Secrets\Business\Model\SecretName;
use App\Secrets\Business\Port\SecretProvider;
use Symfony\Contracts\HttpClient\HttpClientInterface;

/**
 * Vault KV v2 адаптер.
 *
 * Контракт:
 * - `get(NAME)` читает `${VAULT_ADDR}/v1/secret/data/<NAME>` c `X-Vault-Token`
 *   и возвращает `data.data.<NAME>`; 404 → SecretNotFound, иные статусы →
 *   SecretProviderUnavailable (с кодом статуса, без тела ответа).
 * - `has()` — тот же read с обработкой 404.
 * - `rotate()` не поддерживается: KV v2 не умеет атомарно ротировать значения
 *   без внешней политики. Вызывающий получает UnsupportedSecretOperation.
 * - Пустые `VAULT_ADDR`/`VAULT_TOKEN` или отсутствие HTTP-клиента → каждая
 *   операция бросает SecretProviderNotConfigured, сетевых вызовов нет.
 */
final class VaultSecretProvider implements SecretProvider
{
    public function __construct(
        private readonly string $address,
        private readonly string $token,
        private readonly ?HttpClientInterface $httpClient = null,
    ) {
    }

    public function get(string $name): string
    {
        $this->assertConfigured();
        $secretName = SecretName::fromString($name);
        $client = $this->httpClient;

        if ($client === null) {
            throw SecretProviderNotConfigured::forBackend('vault', 'HTTP client is not wired');
        }

        try {
            $response = $client->request(
                'GET',
                rtrim($this->address, '/') . '/v1/secret/data/' . $secretName->value(),
                ['headers' => ['X-Vault-Token' => $this->token]],
            );
            $status = $response->getStatusCode();

            if ($status === 404) {
                throw SecretNotFound::named($secretName->value(), 'vault');
            }

            if ($status !== 200) {
                throw SecretProviderUnavailable::forBackend('vault', 'http_' . $status);
            }

            $payload = $response->toArray(false);
        } catch (SecretNotFound | SecretProviderUnavailable $e) {
            throw $e;
        } catch (\Throwable) {
            throw SecretProviderUnavailable::forBackend('vault', 'transport_error');
        }

        $value = $payload['data']['data'][$secretName->value()] ?? null;

        if (!is_string($value) || $value === '') {
            throw SecretNotFound::named($secretName->value(), 'vault');
        }

        return $value;
    }

    public function has(string $name): bool
    {
        try {
            $this->get($name);

            return true;
        } catch (SecretNotFound) {
            return false;
        }
    }

    public function rotate(string $name): void
    {
        $this->assertConfigured();

        throw new UnsupportedSecretOperation(
            'vault: automatic rotation is not supported; follow the operator rotation runbook',
        );
    }

    private function assertConfigured(): void
    {
        if (trim($this->address) === '' || trim($this->token) === '') {
            throw SecretProviderNotConfigured::forBackend('vault', 'VAULT_ADDR and VAULT_TOKEN must be set');
        }
    }
}
