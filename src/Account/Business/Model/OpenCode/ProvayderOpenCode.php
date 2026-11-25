<?php

declare(strict_types=1);

namespace App\Account\Business\Model\OpenCode;

use App\Account\Business\ReadModel\ProvayderOpenCodeReadModel;

/**
 * Каталог известных провайдеров OpenAI-совместимого API для реквизитов OpenCode.
 *
 * Данные статичны: код, человекочитаемое название, базовый URL, путь к списку
 * моделей, способ авторизации и поддержка live-списка. Список моделей всегда
 * запрашивается у провайдера; для провайдеров без live-списка модели вводятся
 * вручную в форме реквизитов.
 */
enum ProvayderOpenCode: string
{
    case OPENAI = 'openai';
    case ANTHROPIC = 'anthropic';
    case GOOGLE = 'google';
    case OPENROUTER = 'openrouter';
    case DEEPSEEK = 'deepseek';
    case MISTRAL = 'mistral';
    case GROQ = 'groq';
    case XAI = 'xai';
    case TOGETHER = 'together';
    case OLLAMA = 'ollama';
    case OPENCODE = 'opencode';
    case OPENCODE_GO = 'opencode-go';
    case CUSTOM = 'custom';

    public function nazvanie(): string
    {
        return match ($this) {
            self::OPENAI => 'OpenAI',
            self::ANTHROPIC => 'Anthropic',
            self::GOOGLE => 'Google Gemini',
            self::OPENROUTER => 'OpenRouter',
            self::DEEPSEEK => 'DeepSeek',
            self::MISTRAL => 'Mistral',
            self::GROQ => 'Groq',
            self::XAI => 'xAI',
            self::TOGETHER => 'Together AI',
            self::OLLAMA => 'Ollama',
            self::OPENCODE => 'OpenCode Zen',
            self::OPENCODE_GO => 'OpenCode Go',
            self::CUSTOM => 'Другой OpenAI-совместимый',
        };
    }

    /**
     * Базовый URL OpenAI-совместимого API. Пустая строка — URL вводится вручную.
     */
    public function baseUrl(): string
    {
        return match ($this) {
            self::OPENAI => 'https://api.openai.com/v1',
            self::ANTHROPIC => 'https://api.anthropic.com/v1',
            self::GOOGLE => 'https://generativelanguage.googleapis.com/v1beta/openai',
            self::OPENROUTER => 'https://openrouter.ai/api/v1',
            self::DEEPSEEK => 'https://api.deepseek.com/v1',
            self::MISTRAL => 'https://api.mistral.ai/v1',
            self::GROQ => 'https://api.groq.com/openai/v1',
            self::XAI => 'https://api.x.ai/v1',
            self::TOGETHER => 'https://api.together.xyz/v1',
            self::OLLAMA => 'http://localhost:11434/v1',
            self::OPENCODE => 'https://api.opencode.ai/v1',
            self::OPENCODE_GO => 'https://opencode.ai/inference/go/openai/v1',
            self::CUSTOM => '',
        };
    }

    public function modelsPath(): string
    {
        return '/models';
    }

    /**
     * Способ передачи ключа: `bearer`, `x-api-key` или `none`.
     */
    public function authStyle(): string
    {
        return match ($this) {
            self::ANTHROPIC => 'x-api-key',
            self::OLLAMA, self::CUSTOM => 'none',
            default => 'bearer',
        };
    }

    public function podderzhivaetSpisokModelei(): bool
    {
        return match ($this) {
            self::OLLAMA, self::OPENCODE, self::CUSTOM => false,
            default => true,
        };
    }

    /**
     * Разрешает провайдера по коду из запроса.
     *
     * @throws \DomainException `account.opencode_credential.empty_provider` при пустом коде
     * @throws \DomainException `account.opencode_credential.unknown_provider` при неизвестном коде
     */
    public static function izKoda(string $kod): self
    {
        $kod = trim($kod);

        if ($kod === '') {
            throw new \DomainException('account.opencode_credential.empty_provider');
        }

        $provider = self::tryFrom($kod);

        if ($provider === null) {
            throw new \DomainException('account.opencode_credential.unknown_provider');
        }

        return $provider;
    }

    /**
     * @return list<ProvayderOpenCodeReadModel>
     */
    public static function katalog(): array
    {
        return array_map(
            static fn (self $provider): ProvayderOpenCodeReadModel => new ProvayderOpenCodeReadModel(
                $provider->value,
                $provider->nazvanie(),
                $provider->baseUrl(),
                $provider->modelsPath(),
                $provider->authStyle(),
                $provider->podderzhivaetSpisokModelei(),
            ),
            self::cases(),
        );
    }
}
