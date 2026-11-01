<?php

declare(strict_types=1);

namespace App\Mediateka\Infra\S3;

/**
 * Подпись запросов AWS Signature Version 4 для S3-совместимого хранилища (MinIO).
 *
 * Реализовано локально, т.к. отдельный S3-клиент в проекте не используется:
 * требуется только PUT/HEAD/DELETE/GET и presigned URL.
 */
final class SigV4Signer
{
    private const ALGORITHM = 'AWS4-HMAC-SHA256';
    private const SERVICE = 's3';

    public function __construct(
        private readonly string $accessKey,
        private readonly string $secretKey,
        private readonly string $region
    ) {
    }

    /**
     * Подпись запроса «здесь и сейчас».
     *
     * @param array<string, string> $headers обязательно включая host и x-amz-content-sha256
     * @return array<string, string> заголовки с добавленным Authorization
     */
    public function signRequest(
        string $method,
        string $path,
        string $query,
        array $headers,
        ?string $payloadHash = null
    ): array {
        $payloadHash ??= $this->hashPayload('');
        $headers['x-amz-content-sha256'] = $payloadHash;

        $amzDate = $this->amzDate();
        $dateStamp = substr($amzDate, 0, 8);

        $headers['x-amz-date'] = $amzDate;

        $normalized = $this->normalizeHeaders($headers);
        $signedHeaders = $this->signedHeaders($normalized);
        $canonicalRequest = $this->canonicalRequest($method, $path, $query, $normalized, $signedHeaders);

        $credentialScope = $dateStamp . '/' . $this->region . '/' . self::SERVICE . '/aws4_request';
        $stringToSign = implode("\n", [
            self::ALGORITHM,
            $amzDate,
            $credentialScope,
            hash('sha256', $canonicalRequest),
        ]);

        $signingKey = $this->signingKey($dateStamp);
        $signature = hash_hmac('sha256', $stringToSign, $signingKey);

        $headers['Authorization'] = sprintf(
            '%s Credential=%s/%s, SignedHeaders=%s, Signature=%s',
            self::ALGORITHM,
            $this->accessKey,
            $credentialScope,
            $signedHeaders,
            $signature
        );

        return $headers;
    }

    /**
     * Presigned URL для GET-запроса (доступен из браузера).
     *
     * @param array<string, string> $extraQuery
     */
    public function presignedGetUrl(
        string $publicEndpoint,
        string $path,
        array $extraQuery = [],
        int $ttl = 3600,
        ?int $timestamp = null
    ): string {
        $now = $timestamp ?? time();
        $amzDate = gmdate('Ymd\THis\Z', $now);
        $dateStamp = substr($amzDate, 0, 8);
        $credentialScope = $dateStamp . '/' . $this->region . '/' . self::SERVICE . '/aws4_request';

        $host = (string) parse_url($publicEndpoint, PHP_URL_HOST);
        $port = parse_url($publicEndpoint, PHP_URL_PORT);
        if (is_int($port)) {
            $host .= ':' . $port;
        }

        $query = array_merge($extraQuery, [
            'X-Amz-Algorithm' => self::ALGORITHM,
            'X-Amz-Credential' => $this->accessKey . '/' . $credentialScope,
            'X-Amz-Date' => $amzDate,
            'X-Amz-Expires' => (string) $ttl,
            'X-Amz-SignedHeaders' => 'host',
        ]);

        ksort($query);
        $canonicalQuery = implode('&', array_map(
            static fn (string $key, string $value): string => rawurlencode($key) . '=' . rawurlencode($value),
            array_keys($query),
            array_values($query)
        ));

        $canonicalHeaders = 'host:' . $host . "\n";
        $canonicalRequest = implode("\n", [
            'GET',
            $path,
            $canonicalQuery,
            $canonicalHeaders,
            'host',
            'UNSIGNED-PAYLOAD',
        ]);

        $stringToSign = implode("\n", [
            self::ALGORITHM,
            $amzDate,
            $credentialScope,
            hash('sha256', $canonicalRequest),
        ]);

        $signature = hash_hmac('sha256', $stringToSign, $this->signingKey($dateStamp));

        $query['X-Amz-Signature'] = $signature;
        ksort($query);

        $queryString = implode('&', array_map(
            static fn (string $key, string $value): string => rawurlencode($key) . '=' . rawurlencode($value),
            array_keys($query),
            array_values($query)
        ));

        return rtrim($publicEndpoint, '/') . $path . '?' . $queryString;
    }

    public function hashPayload(string $payload): string
    {
        return hash('sha256', $payload);
    }

    public function hashPayloadStream(string $pathToFile): string
    {
        return hash_file('sha256', $pathToFile) ?: $this->hashPayload('');
    }

    /**
     * @param array<string, string> $headers
     * @return array<string, string> lowercased key => trimmed value
     */
    private function normalizeHeaders(array $headers): array
    {
        $normalized = [];
        foreach ($headers as $name => $value) {
            $normalized[strtolower($name)] = trim(preg_replace('/\s+/', ' ', $value) ?? $value);
        }
        ksort($normalized);

        return $normalized;
    }

    /**
     * @param array<string, string> $normalizedHeaders
     */
    private function signedHeaders(array $normalizedHeaders): string
    {
        return implode(';', array_keys($normalizedHeaders));
    }

    /**
     * @param array<string, string> $normalizedHeaders
     */
    private function canonicalRequest(
        string $method,
        string $path,
        string $query,
        array $normalizedHeaders,
        string $signedHeaders
    ): string {
        $canonicalHeaders = '';
        foreach ($normalizedHeaders as $name => $value) {
            $canonicalHeaders .= $name . ':' . $value . "\n";
        }

        return implode("\n", [
            $method,
            $this->encodePath($path),
            $this->canonicalQueryString($query),
            $canonicalHeaders,
            $signedHeaders,
            $normalizedHeaders['x-amz-content-sha256'],
        ]);
    }

    private function canonicalQueryString(string $query): string
    {
        if ($query === '') {
            return '';
        }

        $pairs = [];
        foreach (explode('&', $query) as $pair) {
            if ($pair === '') {
                continue;
            }
            $parts = explode('=', $pair, 2);
            $key = rawurldecode($parts[0]);
            $value = rawurldecode($parts[1] ?? '');
            $pairs[] = [rawurlencode($key), rawurlencode($value)];
        }

        usort($pairs, static fn (array $a, array $b): int => [$a[0], $a[1]] <=> [$b[0], $b[1]]);

        return implode('&', array_map(
            static fn (array $pair): string => $pair[0] . '=' . $pair[1],
            $pairs
        ));
    }

    private function encodePath(string $path): string
    {
        return implode('/', array_map(
            static fn (string $segment): string => rawurlencode($segment),
            explode('/', $path)
        ));
    }

    private function signingKey(string $dateStamp): string
    {
        $kDate = hash_hmac('sha256', $dateStamp, 'AWS4' . $this->secretKey, true);
        $kRegion = hash_hmac('sha256', $this->region, $kDate, true);
        $kService = hash_hmac('sha256', self::SERVICE, $kRegion, true);

        return hash_hmac('sha256', 'aws4_request', $kService, true);
    }

    private function amzDate(): string
    {
        return gmdate('Ymd\THis\Z');
    }
}
