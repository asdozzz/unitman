<?php

declare(strict_types=1);

namespace App\Runner\Business\Command;

/**
 * Команда активити одного хода диалога с OpenCode.
 *
 * Собирается в бэкенде (`RunnerApi::nachatHodOpenCode`): реквизиты подключения
 * подставляет модуль Runner, получив их через `Acl`-адаптер над `AccountApi`.
 *
 * ВАЖНО: `ApiKey` попадает в payload активности, а значит — в историю Temporal.
 * Остаточный риск и ограничения описаны в `plans/opencode-sessions.md` (раздел 7.4).
 * Ключ не должен логироваться, попадать в `result_json`, Centrifugo или trace-контекст.
 *
 * Имена полей — PascalCase, как в остальных командах модуля (`NachatDeistvieUnita`),
 * потому что сериализатор передаёт их в JSON как есть, а Go-раннер читает ровно эти ключи.
 */
final class VipolnitHodOpenCode
{
    /**
     * @param list<array{QuestionId: string, OptionId: string|null, Text: string|null}> $Answers
     * @param list<array{Role: string, Text: string}> $History
     */
    public function __construct(
        public readonly ?string $SessionId,
        public readonly string $Prompt,
        public readonly array $Answers,
        public readonly array $History,
        public readonly string $Url,
        public readonly string $Model,
        public readonly string $ApiKey,
    ) {
    }
}
