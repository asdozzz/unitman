<?php

declare(strict_types=1);

namespace App\AiChat\Infra\Command;

/**
 * Валидированный запрос к AI-чату.
 *
 * DTO неизменяем: все per-request данные живут в readonly-свойствах одного
 * экземпляра и не шарятся между запросами воркера RoadRunner.
 *
 * Лимиты подобраны так, чтобы один запрос не мог переполнить контекст модели
 * и память воркера. Точные значения — константы класса.
 */
final class AiChatRequest
{
    /** Максимальное число сообщений истории в одном запросе. */
    public const MAX_MESSAGES = 100;

    /** Максимальная длина содержимого одного сообщения, символов. */
    public const MAX_MESSAGE_LENGTH = 100000;

    /** Максимальное число инструментов (tools) в одном запросе. */
    public const MAX_TOOLS = 64;

    /** Максимальная длина имени инструмента (совпадает с `^[A-Za-z0-9_-]{1,64}$`). */
    public const MAX_TOOL_NAME_LENGTH = 64;

    /** Максимальная длина идентификатора модели. */
    public const MAX_MODEL_LENGTH = 128;

    /** Максимальная длина идентификатора реквизитов. */
    public const MAX_CREDENTIAL_ID_LENGTH = 128;

    /** Максимальная длина идентификатора сессии провайдера, символов. */
    public const MAX_SESSION_ID_LENGTH = 128;

    /**
     * Максимальное число шагов агентского цикла.
     *
     * Ограничение применяется на фронтенде (useAiAgentChat), здесь хранится
     * как единая точка правды и не принимается из тела запроса.
     */
    public const MAX_STEPS = 10;

    private const KEY_INVALID_PAYLOAD = 'ai.chat.invalid_payload';

    private const ROLES = ['system', 'user', 'assistant', 'tool'];

    private const TOOL_NAME_PATTERN = '/^[A-Za-z0-9_-]{1,64}$/';

    /** Допустимый формат идентификатора сессии — защита от header injection. */
    private const SESSION_ID_PATTERN = '/^[A-Za-z0-9._:-]{1,128}$/';

    /**
     * @param list<array<string, mixed>> $messages
     * @param list<array<string, mixed>> $tools
     */
    private function __construct(
        public readonly array $messages,
        public readonly array $tools,
        public readonly ?string $model,
        public readonly ?string $credentialId,
        public readonly ?string $sessionId = null,
    ) {
    }

    /**
     * @param array<string, mixed> $payload
     *
     * @throws \InvalidArgumentException с ключом `ai.chat.invalid_payload`
     */
    public static function fromArray(array $payload): self
    {
        $messages = $payload['messages'] ?? null;

        if (!\is_array($messages) || !array_is_list($messages) || $messages === []) {
            throw new \InvalidArgumentException(self::KEY_INVALID_PAYLOAD);
        }

        if (\count($messages) > self::MAX_MESSAGES) {
            throw new \InvalidArgumentException(self::KEY_INVALID_PAYLOAD);
        }

        foreach ($messages as $message) {
            if (!\is_array($message) || array_is_list($message)) {
                throw new \InvalidArgumentException(self::KEY_INVALID_PAYLOAD);
            }

            $role = $message['role'] ?? null;

            if (!\is_string($role) || !\in_array($role, self::ROLES, true)) {
                throw new \InvalidArgumentException(self::KEY_INVALID_PAYLOAD);
            }

            if (\array_key_exists('content', $message)) {
                $content = $message['content'];

                if ($content !== null && !\is_string($content)) {
                    throw new \InvalidArgumentException(self::KEY_INVALID_PAYLOAD);
                }

                if (\is_string($content) && self::dlina($content) > self::MAX_MESSAGE_LENGTH) {
                    throw new \InvalidArgumentException(self::KEY_INVALID_PAYLOAD);
                }
            }
        }

        $tools = $payload['tools'] ?? [];

        if (!\is_array($tools) || !array_is_list($tools)) {
            throw new \InvalidArgumentException(self::KEY_INVALID_PAYLOAD);
        }

        if (\count($tools) > self::MAX_TOOLS) {
            throw new \InvalidArgumentException(self::KEY_INVALID_PAYLOAD);
        }

        foreach ($tools as $tool) {
            if (!\is_array($tool) || array_is_list($tool)) {
                throw new \InvalidArgumentException(self::KEY_INVALID_PAYLOAD);
            }

            $type = $tool['type'] ?? null;

            if (!\is_string($type) || $type === '') {
                throw new \InvalidArgumentException(self::KEY_INVALID_PAYLOAD);
            }

            if ($type !== 'function') {
                continue;
            }

            $function = $tool['function'] ?? null;

            if (!\is_array($function) || array_is_list($function)) {
                throw new \InvalidArgumentException(self::KEY_INVALID_PAYLOAD);
            }

            $name = $function['name'] ?? null;

            if (
                !\is_string($name)
                || preg_match(self::TOOL_NAME_PATTERN, $name) !== 1
            ) {
                throw new \InvalidArgumentException(self::KEY_INVALID_PAYLOAD);
            }
        }

        return new self(
            $messages,
            $tools,
            self::normalizeOptionalString($payload['model'] ?? null, self::MAX_MODEL_LENGTH),
            self::normalizeOptionalString($payload['credentialId'] ?? null, self::MAX_CREDENTIAL_ID_LENGTH),
            self::normalizeSessionId($payload['sessionId'] ?? null),
        );
    }

    /**
     * Валидирует идентификатор сессии провайдера.
     *
     * Значение не обрезается: допустим только строгий формат без пробельных
     * символов, чтобы исключить header injection. `null` и явная пустая
     * строка трактуются как «сессия не задана».
     */
    private static function normalizeSessionId(mixed $value): ?string
    {
        if ($value === null) {
            return null;
        }

        if (!\is_string($value)) {
            throw new \InvalidArgumentException(self::KEY_INVALID_PAYLOAD);
        }

        if ($value === '') {
            return null;
        }

        if (
            \strlen($value) > self::MAX_SESSION_ID_LENGTH
            || preg_match(self::SESSION_ID_PATTERN, $value) !== 1
        ) {
            throw new \InvalidArgumentException(self::KEY_INVALID_PAYLOAD);
        }

        return $value;
    }

    private static function normalizeOptionalString(mixed $value, int $maxLength): ?string
    {
        if ($value === null) {
            return null;
        }

        if (!\is_string($value)) {
            throw new \InvalidArgumentException(self::KEY_INVALID_PAYLOAD);
        }

        $value = trim($value);

        if ($value === '') {
            return null;
        }

        if (self::dlina($value) > $maxLength) {
            throw new \InvalidArgumentException(self::KEY_INVALID_PAYLOAD);
        }

        return $value;
    }

    /**
     * Длина строки в символах, если доступен mbstring, иначе в байтах.
     * mbstring не является обязательным расширением проекта.
     */
    private static function dlina(string $value): int
    {
        return \function_exists('mb_strlen') ? mb_strlen($value) : \strlen($value);
    }
}
