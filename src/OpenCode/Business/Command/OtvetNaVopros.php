<?php

declare(strict_types=1);

namespace App\OpenCode\Business\Command;

/**
 * Один ответ: выбранный вариант либо свободный текст.
 *
 * `optionId` и `text` взаимоисключающие только логически — вопрос с
 * `AllowFreeText` принимает оба, и тогда вариант дополняется комментарием.
 */
final class OtvetNaVopros
{
    public function __construct(
        public readonly string $questionId = '',
        public readonly ?string $optionId = null,
        public readonly ?string $text = null,
    ) {
    }

    public function estPustym(): bool
    {
        return self::pustoi($this->optionId) && self::pustoi($this->text);
    }

    /**
     * @return array{QuestionId: string, OptionId: string|null, Text: string|null}
     */
    public function toArray(): array
    {
        return [
            'QuestionId' => $this->questionId,
            'OptionId' => self::pustoi($this->optionId) ? null : $this->optionId,
            'Text' => self::pustoi($this->text) ? null : $this->text,
        ];
    }

    private static function pustoi(?string $value): bool
    {
        return $value === null || trim($value) === '';
    }
}