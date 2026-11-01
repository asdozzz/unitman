<?php

declare(strict_types=1);

namespace App\Mediateka\Infra\Adapter;

use App\Mediateka\Business\Port\FileStorage;
use App\Mediateka\Infra\S3\S3Settings;
use App\Mediateka\Infra\S3\SigV4Signer;
use Symfony\Contracts\HttpClient\Exception\TransportExceptionInterface;
use Symfony\Contracts\HttpClient\HttpClientInterface;

/**
 * Реализация хранилища поверх S3-совместимого MinIO.
 *
 * Используется symfony/http-client + локальная реализация SigV4,
 * так как отдельный S3-клиент в проекте не подключён.
 */
final class MinioFileStorage implements FileStorage
{
    private const CHUNK_SIZE = 1048576;

    public function __construct(
        private readonly HttpClientInterface $httpClient,
        private readonly SigV4Signer $signer,
        private readonly S3Settings $settings
    ) {
    }

    /**
     * @param resource $stream
     */
    public function save(string $key, $stream, int $size, string $contentType): void
    {
        [$tempPath, $payloadHash] = $this->spoolToTempFile($stream);

        try {
            [$host, $rawPath] = $this->hostAndPath($key);

            $signedHeaders = $this->signer->signRequest(
                'PUT',
                $rawPath,
                '',
                [
                    'host' => $host,
                    'content-type' => $contentType,
                    'content-length' => (string) $size,
                ],
                $payloadHash
            );

            $response = $this->httpClient->request('PUT', $this->url($rawPath), [
                'headers' => $this->headersForRequest($signedHeaders),
                'body' => $this->streamBody($tempPath),
                'timeout' => 300,
            ]);

            $status = $response->getStatusCode();
            if ($status < 200 || $status >= 300) {
                throw new \RuntimeException(sprintf('Не удалось сохранить файл в хранилище (HTTP %d)', $status));
            }
        } finally {
            @unlink($tempPath);
        }
    }

    public function delete(string $key): void
    {
        [$host, $rawPath] = $this->hostAndPath($key);

        $signedHeaders = $this->signer->signRequest('DELETE', $rawPath, '', ['host' => $host]);

        $status = $this->httpClient
            ->request('DELETE', $this->url($rawPath), ['headers' => $this->headersForRequest($signedHeaders)])
            ->getStatusCode();

        if ($status === 404 || ($status >= 200 && $status < 300)) {
            return;
        }

        throw new \RuntimeException(sprintf('Не удалось удалить файл из хранилища (HTTP %d)', $status));
    }

    public function exists(string $key): bool
    {
        [$host, $rawPath] = $this->hostAndPath($key);

        $signedHeaders = $this->signer->signRequest('HEAD', $rawPath, '', ['host' => $host]);

        try {
            $status = $this->httpClient
                ->request('HEAD', $this->url($rawPath), ['headers' => $this->headersForRequest($signedHeaders)])
                ->getStatusCode();
        } catch (TransportExceptionInterface) {
            return false;
        }

        if ($status === 404) {
            return false;
        }

        return $status >= 200 && $status < 300;
    }

    public function presignedGetUrl(string $key, ?string $downloadName = null, ?int $ttl = null): string
    {
        [, $rawPath] = $this->hostAndPath($key);

        $extraQuery = [];
        if ($downloadName !== null && $downloadName !== '') {
            $extraQuery['response-content-disposition'] = sprintf(
                'attachment; filename="%s"',
                str_replace('"', '', $downloadName)
            );
        }

        return $this->signer->presignedGetUrl(
            $this->settings->publicEndpoint,
            $this->encodePath($rawPath),
            $extraQuery,
            $ttl ?? $this->settings->presignTtl
        );
    }

    /**
     * @return array{0: string, 1: string} [host, rawPath]
     */
    private function hostAndPath(string $key): array
    {
        $endpoint = rtrim($this->settings->endpoint, '/');
        $host = (string) parse_url($endpoint, PHP_URL_HOST);
        $port = parse_url($endpoint, PHP_URL_PORT);
        if (is_int($port)) {
            $host .= ':' . $port;
        }

        $normalizedKey = ltrim($key, '/');

        if ($this->settings->usePathStyle) {
            return [$host, '/' . $this->settings->bucket . '/' . $normalizedKey];
        }

        return [$this->settings->bucket . '.' . $host, '/' . $normalizedKey];
    }

    private function url(string $rawPath): string
    {
        return rtrim($this->settings->endpoint, '/') . $this->encodePath($rawPath);
    }

    private function encodePath(string $path): string
    {
        return implode('/', array_map(
            static fn (string $segment): string => rawurlencode($segment),
            explode('/', $path)
        ));
    }

    /**
     * Приводит подписанные заголовки к виду, пригодному для отправки.
     *
     * @param array<string, string> $signedHeaders
     * @return array<string, string>
     */
    private function headersForRequest(array $signedHeaders): array
    {
        $headers = [];
        foreach ($signedHeaders as $name => $value) {
            $headers[$name] = $value;
        }

        return $headers;
    }

    /**
     * Копирует поток во временный файл, попутно считая SHA-256 содержимого.
     *
     * @param resource $stream
     * @return array{0: string, 1: string}
     */
    private function spoolToTempFile($stream): array
    {
        $tempPath = tempnam(sys_get_temp_dir(), 'mediateka_');
        if ($tempPath === false) {
            throw new \RuntimeException('Не удалось создать временный файл для загрузки');
        }

        $target = fopen($tempPath, 'wb');
        if ($target === false) {
            throw new \RuntimeException('Не удалось открыть временный файл для загрузки');
        }

        $hash = hash_init('sha256');

        try {
            while (!feof($stream)) {
                $chunk = fread($stream, self::CHUNK_SIZE);
                if ($chunk === false) {
                    throw new \RuntimeException('Не удалось прочитать тело загружаемого файла');
                }
                if ($chunk === '') {
                    continue;
                }

                hash_update($hash, $chunk);
                fwrite($target, $chunk);
            }
        } finally {
            fclose($target);
        }

        return [$tempPath, hash_final($hash)];
    }

    /**
     * Тело запроса в виде генератора: файл не загружается в память целиком.
     */
    private function streamBody(string $path): callable
    {
        return static function () use ($path): \Generator {
            $handle = fopen($path, 'rb');
            if ($handle === false) {
                throw new \RuntimeException('Не удалось открыть временный файл для отправки');
            }

            try {
                while (!feof($handle)) {
                    $chunk = fread($handle, self::CHUNK_SIZE);
                    if ($chunk === false || $chunk === '') {
                        break;
                    }

                    yield $chunk;
                }
            } finally {
                fclose($handle);
            }
        };
    }
}
