<?php

declare(strict_types=1);

namespace App\Account\Business\Model\OpenCode;

/**
 * Политика provider-сессии для конкретного провайдера.
 *
 * Описывает, куда и как передаётся идентификатор provider-сессии, сколько
 * он живёт и можно ли использовать провайдера в AI-чате. Провайдер с
 * транспортом `Unknown` блокируется в AI-чате (`supportsAiChat = false`).
 */
final class ProviderSessionPolicy
{
    /**
     * Консервативный TTL provider-сессии по умолчанию, секунды.
     *
     * Значение продублировано в `AI_CHAT_PROVIDER_SESSION_MAX_TTL` (верхняя
     * граница для AI-чата), но именно эта константа задаёт минимальный TTL
     * провайдера и не позволяет расползаться «магическим» 300 по коду.
     */
    public const DEFAULT_TTL_SECONDS = 300;

    public function __construct(
        public readonly ProviderSessionTransport $transport,
        public readonly ?string $key,
        public readonly int $ttlSeconds,
        public readonly bool $supportsAiChat,
    ) {
    }

    /**
     * Провайдер не использует provider-сессию.
     */
    public static function none(int $ttlSeconds): self
    {
        return new self(ProviderSessionTransport::None, null, $ttlSeconds, true);
    }

    /**
     * Провайдер принимает идентификатор в заголовке запроса.
     */
    public static function header(string $key, int $ttlSeconds): self
    {
        return new self(ProviderSessionTransport::Header, $key, $ttlSeconds, true);
    }

    /**
     * Провайдер принимает идентификатор в теле запроса.
     */
    public static function body(string $key, int $ttlSeconds): self
    {
        return new self(ProviderSessionTransport::Body, $key, $ttlSeconds, true);
    }

    /**
     * Провайдер принимает идентификатор в query-параметре.
     */
    public static function query(string $key, int $ttlSeconds): self
    {
        return new self(ProviderSessionTransport::Query, $key, $ttlSeconds, true);
    }

    /**
     * Транспорт ещё не описан: провайдер недоступен в AI-чате.
     */
    public static function unknown(): self
    {
        return new self(ProviderSessionTransport::Unknown, null, self::DEFAULT_TTL_SECONDS, false);
    }
}
