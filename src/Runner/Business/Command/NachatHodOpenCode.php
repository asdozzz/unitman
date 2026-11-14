<?php

declare(strict_types=1);

namespace App\Runner\Business\Command;

/**
 * Входные данные хода диалога с OpenCode — то, что приходит из модуля OpenCode.
 *
 * Реквизитов подключения здесь нет: их подставляет сам Runner, забирая
 * у Account через `Acl`-адаптер по паре `AccountId` + `CredentialId`.
 * Так ключ не проходит через публичный API Runner и не попадает в вызывающий модуль.
 */
final class NachatHodOpenCode
{
    /**
     * @param list<array{QuestionId: string, OptionId: string|null, Text: string|null}> $Answers
     * @param list<array{Role: string, Text: string}> $History
     */
    public function __construct(
        public readonly string $AccountId,
        public readonly string $CredentialId,
        public readonly ?string $SessionId,
        public readonly string $Prompt,
        public readonly array $Answers = [],
        public readonly array $History = [],
    ) {
    }
}
