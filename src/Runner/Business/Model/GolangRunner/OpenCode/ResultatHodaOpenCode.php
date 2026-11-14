<?php

declare(strict_types=1);

namespace App\Runner\Business\Model\GolangRunner\OpenCode;

/**
 * Результат одного хода диалога с OpenCode, полученный от Go-раннера.
 *
 * Раннер не «висит» в ожидании ответа пользователя: если OpenCode задал вопрос,
 * активити завершается с `Kind = QUESTION` и списком вопросов с вариантами.
 * Продолжение диалога — отдельный ход, который стартует новый workflow.
 *
 * API-ключ здесь отсутствует принципиально: результат хода уходит в `result_json`
 * и отдаётся клиенту, поэтому реквизиты в него попадать не должны.
 */
final class ResultatHodaOpenCode
{
    public const KIND_ANSWER = 'ANSWER';
    public const KIND_QUESTION = 'QUESTION';
    public const KIND_ERROR = 'ERROR';

    /**
     * @param bool $Success
     * @param string $Kind одно из значений KIND_*
     * @param string|null $Answer заполнен при Kind = ANSWER
     * @param list<array{
     *     Id: string,
     *     Text: string,
     *     AllowFreeText: bool,
     *     Options: list<array{Id: string, Label: string, Description?: string}>
     * }> $Questions заполнен при Kind = QUESTION
     * @param string|null $ErrorKey ключ ошибки для переводов, заполнен при Kind = ERROR
     * @param string|null $ExternalSessionId идентификатор сессии на стороне OpenCode
     */
    public function __construct(
        public readonly bool $Success,
        public readonly string $Kind,
        public readonly array $Questions,
        public readonly ?string $Answer = null,
        public readonly ?string $ErrorKey = null,
        public readonly ?string $ExternalSessionId = null,
    ) {
    }

    public function estOtvetom(): bool
    {
        return $this->Success && $this->Kind === self::KIND_ANSWER;
    }

    public function estVoprosom(): bool
    {
        return $this->Success && $this->Kind === self::KIND_QUESTION;
    }

    public function estOshibkoi(): bool
    {
        return !$this->Success || $this->Kind === self::KIND_ERROR;
    }
}
