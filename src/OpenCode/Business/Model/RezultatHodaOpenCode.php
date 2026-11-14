<?php

declare(strict_types=1);

namespace App\OpenCode\Business\Model;

/**
 * Результат одного хода, уже очищенный от служебных данных раннера.
 *
 * В этой структуре **никогда** не бывает API-ключа — только текст ответа,
 * вопросы с вариантами выбора и ключ ошибки. Именно она сериализуется
 * в `opencode_turn.result_json` и отдаётся во фронтенд.
 */
final class RezultatHodaOpenCode
{
    public const KIND_ANSWER = 'ANSWER';
    public const KIND_QUESTION = 'QUESTION';
    public const KIND_ERROR = 'ERROR';

    /**
     * @param list<array{
     *     Id: string,
     *     Text: string,
     *     AllowFreeText: bool,
     *     Options: list<array{Id: string, Label: string, Description: string|null}>
     * }> $questions
     */
    public function __construct(
        public readonly bool $success,
        public readonly string $kind,
        public readonly array $questions,
        public readonly ?string $answer,
        public readonly ?string $errorKey,
        public readonly ?string $externalSessionId,
    ) {
    }

    /**
     * @param array<array-key, mixed> $data
     */
    public static function fromArray(array $data): self
    {
        return new self(
            (bool) ($data['Success'] ?? false),
            self::stroka($data['Kind'] ?? null, self::KIND_ERROR),
            self::voprosy($data['Questions'] ?? null),
            self::strokaIliNull($data['Answer'] ?? null),
            self::strokaIliNull($data['ErrorKey'] ?? null),
            self::strokaIliNull($data['ExternalSessionId'] ?? null),
        );
    }

    /**
     * @return array{
     *     Success: bool,
     *     Kind: string,
     *     Questions: list<array{
     *         Id: string,
     *         Text: string,
     *         AllowFreeText: bool,
     *         Options: list<array{Id: string, Label: string, Description: string|null}>
     *     }>,
     *     Answer: string|null,
     *     ErrorKey: string|null,
     *     ExternalSessionId: string|null
     * }
     */
    public function toArray(): array
    {
        return [
            'Success' => $this->success,
            'Kind' => $this->kind,
            'Questions' => $this->questions,
            'Answer' => $this->answer,
            'ErrorKey' => $this->errorKey,
            'ExternalSessionId' => $this->externalSessionId,
        ];
    }

    public function estOtvetom(): bool
    {
        return $this->success && $this->kind === self::KIND_ANSWER;
    }

    public function estVoprosom(): bool
    {
        return $this->success && $this->kind === self::KIND_QUESTION;
    }

    public function estOshibkoi(): bool
    {
        return !$this->success || $this->kind === self::KIND_ERROR;
    }

    public static function oshibka(string $errorKey): self
    {
        return new self(false, self::KIND_ERROR, [], null, $errorKey, null);
    }

    /**
     * @return list<array{
     *     Id: string,
     *     Text: string,
     *     AllowFreeText: bool,
     *     Options: list<array{Id: string, Label: string, Description: string|null}>
     * }>
     */
    private static function voprosy(mixed $raw): array
    {
        if (!is_array($raw)) {
            return [];
        }

        $questions = [];

        foreach ($raw as $question) {
            if (!is_array($question)) {
                continue;
            }

            $questions[] = [
                'Id' => self::stroka($question['Id'] ?? null, ''),
                'Text' => self::stroka($question['Text'] ?? null, ''),
                'AllowFreeText' => (bool) ($question['AllowFreeText'] ?? false),
                'Options' => self::varianty($question['Options'] ?? null),
            ];
        }

        return $questions;
    }

    /**
     * @return list<array{Id: string, Label: string, Description: string|null}>
     */
    private static function varianty(mixed $raw): array
    {
        if (!is_array($raw)) {
            return [];
        }

        $options = [];

        foreach ($raw as $option) {
            if (!is_array($option)) {
                continue;
            }

            $options[] = [
                'Id' => self::stroka($option['Id'] ?? null, ''),
                'Label' => self::stroka($option['Label'] ?? null, ''),
                'Description' => self::strokaIliNull($option['Description'] ?? null),
            ];
        }

        return $options;
    }

    private static function stroka(mixed $value, string $default): string
    {
        return is_string($value) && $value !== '' ? $value : $default;
    }

    private static function strokaIliNull(mixed $value): ?string
    {
        return is_string($value) && $value !== '' ? $value : null;
    }
}