<?php

declare(strict_types=1);

namespace App\Mediateka\Business\Utils;

use App\Mediateka\Business\Model\ZagruzkaFaylaToken;
use DomainException;
use InvalidArgumentException;
use Throwable;

/**
 * Кодирование и проверка stateless-токена прямой загрузки.
 *
 * Формат: `base64url(json).base64url(HMAC-SHA256(json))`. Подпись считается
 * секретом приложения (`APP_SECRET`), поэтому подделать метаданные между
 * presign и finalize нельзя, а база данных для «ожидающих» загрузок не нужна.
 */
final class UploadTokenCodec
{
    private const SEPARATOR = '.';
    private const HMAC_ALGORITHM = 'sha256';

    public function __construct(private readonly string $uploadTokenSecret)
    {
        if ($this->uploadTokenSecret === '') {
            throw new InvalidArgumentException('Upload token secret must not be empty');
        }
    }

    public function encode(ZagruzkaFaylaToken $token): string
    {
        // JSON_THROW_ON_ERROR гарантирует строку: проверка на false не нужна.
        $json = json_encode($token->toArray(), JSON_THROW_ON_ERROR);

        $payload = $this->base64UrlEncode($json);
        $signature = $this->syncSignature($payload);

        return $payload . self::SEPARATOR . $signature;
    }

    public function decode(string $token): ZagruzkaFaylaToken
    {
        $parts = explode(self::SEPARATOR, $token);
        if (count($parts) !== 2) {
            throw new DomainException('mediateka.upload.token_invalid');
        }

        $payload = $parts[0];
        $signature = $parts[1];

        if (!hash_equals($this->syncSignature($payload), $signature)) {
            throw new DomainException('mediateka.upload.token_invalid');
        }

        $json = $this->base64UrlDecode($payload);
        if ($json === null) {
            throw new DomainException('mediateka.upload.token_invalid');
        }

        $data = json_decode($json, true);
        if (!is_array($data)) {
            throw new DomainException('mediateka.upload.token_invalid');
        }

        try {
            return ZagruzkaFaylaToken::fromArray($data);
        } catch (Throwable) {
            throw new DomainException('mediateka.upload.token_invalid');
        }
    }

    private function syncSignature(string $payload): string
    {
        return $this->base64UrlEncode(hash_hmac(self::HMAC_ALGORITHM, $payload, $this->uploadTokenSecret, true));
    }

    private function base64UrlEncode(string $value): string
    {
        return rtrim(strtr(base64_encode($value), '+/', '-_'), '=');
    }

    private function base64UrlDecode(string $value): ?string
    {
        $padded = str_pad(
            strtr($value, '-_', '+/'),
            (int) ceil(strlen($value) / 4) * 4,
            '=',
            STR_PAD_RIGHT
        );

        $decoded = base64_decode($padded, true);

        return $decoded === false ? null : $decoded;
    }
}
