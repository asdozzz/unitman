<?php

declare(strict_types=1);

namespace App\OpenCode\Business\Utils;

use App\OpenCode\Business\Model\HodOpenCode;

/**
 * Сборка истории диалога для активити-«хода».
 *
 * Раннер — stateless исполнитель: чтобы OpenCode «помнил» разговор, история
 * передаётся целиком в каждом ходе. Формат намеренно простой: список пар
 * роль пользователя либо ассистента и текст реплики.
 */
final class IstoriyaDialogaOpenCode
{
    /**
     * @param list<HodOpenCode> $turns
     *
     * @return array<string, mixed>
     *
     * @psalm-return list<array{Role: string, Text: string}>
     */
    public static function sobrat(array $turns): array
    {
        $history = [];

        foreach ($turns as $turn) {
            $history[] = ['Role' => 'user', 'Text' => $turn->getRequestText()];

            $result = $turn->getResult();

            if ($result === null) {
                continue;
            }

            if ($result->answer !== null && trim($result->answer) !== '') {
                $history[] = ['Role' => 'assistant', 'Text' => $result->answer];

                continue;
            }

            if ($result->questions === []) {
                continue;
            }

            $history[] = ['Role' => 'assistant', 'Text' => self::voprosyVTekst($result->questions)];

            $otvety = self::otvetyVTekst($result->questions, $turn->getAnswers());

            if ($otvety !== '') {
                $history[] = ['Role' => 'user', 'Text' => $otvety];
            }
        }

        return $history;
    }

    /**
     * Структура вопроса — контракт `RezultatHodaOpenCode::$questions`;
     * типы описаны точно, чтобы Psalm ловил расхождение с раннером.
     *
     * @param list<array{
     *     Id: string,
     *     Text: string,
     *     AllowFreeText: bool,
     *     Options: list<array{Id: string, Label: string, Description: string|null}>
     * }> $questions
     */
    public static function voprosyVTekst(array $questions): string
    {
        $lines = [];

        foreach ($questions as $question) {
            $lines[] = self::tekstVoprosa($question);
        }

        return implode("\n", $lines);
    }

    /**
     * Человекочитаемая расшифровка ответов — она же становится «промптом»
     * нового хода, поэтому в ней есть и текст вопроса, и выбранные варианты.
     *
     * @param list<array{
     *     Id: string,
     *     Text: string,
     *     AllowFreeText: bool,
     *     Options: list<array{Id: string, Label: string, Description: string|null}>
     * }> $questions
     * @param list<array{QuestionId: string, OptionId: string|null, Text: string|null}> $answers
     */
    public static function otvetyVTekst(array $questions, array $answers): string
    {
        $poVoprosam = [];

        foreach ($questions as $question) {
            $poVoprosam[$question['Id']] = $question;
        }

        $lines = [];

        foreach ($answers as $answer) {
            $questionId = $answer['QuestionId'];
            $question = $poVoprosam[$questionId] ?? null;
            $podpis = $question === null ? $questionId : self::tekstVoprosa($question);
            $znachenie = self::znachenieOtveta($question, $answer);

            if ($znachenie === '') {
                continue;
            }

            $lines[] = $podpis . ': ' . $znachenie;
        }

        return implode("\n", $lines);
    }

    /**
     * @param array<string, mixed> $question
     */
    private static function tekstVoprosa(array $question): string
    {
        $text = trim((string) ($question['Text'] ?? ''));
        $id = (string) ($question['Id'] ?? '');

        return $text !== '' ? $text : $id;
    }

    /**
     * @param array<string, mixed>|null $question
     * @param array<string, mixed> $answer
     */
    private static function znachenieOtveta(?array $question, array $answer): string
    {
        $chasti = [];
        $optionId = self::strokaIliNull($answer['OptionId'] ?? null);
        $text = self::strokaIliNull($answer['Text'] ?? null);

        if ($optionId !== null) {
            $chasti[] = self::podpisVarianta($question, $optionId);
        }

        if ($text !== null) {
            $chasti[] = $text;
        }

        return implode(' — ', $chasti);
    }

    /**
     * @param array<string, mixed>|null $question
     */
    private static function podpisVarianta(?array $question, string $optionId): string
    {
        if ($question === null) {
            return $optionId;
        }

        /** @var array<int|string, mixed> $options */
        $options = is_array($question['Options'] ?? null) ? $question['Options'] : [];

        foreach ($options as $option) {
            if (!is_array($option)) {
                continue;
            }

            if ((string) ($option['Id'] ?? '') !== $optionId) {
                continue;
            }

            $label = trim((string) ($option['Label'] ?? ''));

            return $label !== '' ? $label : $optionId;
        }

        return $optionId;
    }

    private static function strokaIliNull(mixed $value): ?string
    {
        if (!is_string($value)) {
            return null;
        }

        $value = trim($value);

        return $value === '' ? null : $value;
    }
}
