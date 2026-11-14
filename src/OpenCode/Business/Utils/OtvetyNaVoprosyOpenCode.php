<?php

declare(strict_types=1);

namespace App\OpenCode\Business\Utils;

use App\OpenCode\Business\Command\OtvetNaVopros;

/**
 * Проверка и нормализация ответов пользователя на вопросы OpenCode.
 *
 * Модель хода хранит ответы уже в «плоском» виде (`QuestionId`/`OptionId`/`Text`),
 * потому что в этом же виде они уходят в JSON раннера и в `answers_json`.
 * Здесь же отсекаются пустые ответы и ответы на несуществующие вопросы или
 * варианты: раннер не должен получать мусор, который он не сможет сопоставить.
 */
final class OtvetyNaVoprosyOpenCode
{
    /**
     * Вопросы приходят ровно в формате `RezultatHodaOpenCode::$questions` —
     * тип описан точно, чтобы Psalm ловил расхождение с контрактом раннера.
     *
     * @param list<array{
     *     Id: string,
     *     Text: string,
     *     AllowFreeText: bool,
     *     Options: list<array{Id: string, Label: string, Description: string|null}>
     * }> $questions
     * @param list<OtvetNaVopros> $otvety
     *
     * @return array<string, mixed>
     *
     * @psalm-return list<array{QuestionId: string, OptionId: string|null, Text: string|null}>
     *
     * @throws \DomainException если ответ не соответствует ни одному вопросу
     */
    public static function sobrat(array $questions, array $otvety): array
    {
        $poId = [];

        foreach ($questions as $question) {
            $id = $question['Id'];

            if ($id === '') {
                continue;
            }

            $poId[$id] = $question;
        }

        $result = [];

        foreach ($otvety as $otvet) {
            if ($otvet->estPustym()) {
                continue;
            }

            $questionId = trim($otvet->questionId);

            if (!isset($poId[$questionId])) {
                throw new \DomainException('opencode.turn.unknown_question');
            }

            $optionId = $otvet->optionId;

            if ($optionId !== null && $optionId !== '') {
                self::trebovatVariant($poId[$questionId], $optionId);
            }

            if ($optionId === null || $optionId === '') {
                self::trebovatSvobodnyiTekst($poId[$questionId]);
            }

            $result[] = $otvet->toArray();
        }

        if ($result === []) {
            throw new \DomainException('opencode.turn.answers_required');
        }

        return $result;
    }

    /**
     * @param array<string, mixed> $question
     *
     * @throws \DomainException если выбран вариант, которого нет в вопросе
     */
    private static function trebovatVariant(array $question, string $optionId): void
    {
        /** @var array<int|string, mixed> $options */
        $options = is_array($question['Options'] ?? null) ? $question['Options'] : [];

        foreach ($options as $option) {
            if (!is_array($option)) {
                continue;
            }

            if ((string) ($option['Id'] ?? '') === $optionId) {
                return;
            }
        }

        throw new \DomainException('opencode.turn.unknown_option');
    }

    /**
     * @param array<string, mixed> $question
     *
     * @throws \DomainException если вопрос не принимает свободный ответ
     */
    private static function trebovatSvobodnyiTekst(array $question): void
    {
        if (($question['AllowFreeText'] ?? false) === true) {
            return;
        }

        throw new \DomainException('opencode.turn.option_required');
    }
}