<?php

declare(strict_types=1);

namespace App\OpenCode\Business\Command;

use App\Utils\Converter\JsonBodySerializableInterface;

/**
 * Ответ пользователя на вопрос, заданный OpenCode в ходе `turnId`.
 *
 * Ответ запускает **новый** ход: старый уже завершён со статусом
 * `AWAITING_INPUT` и не переоткрывается.
 */
readonly class OtvetitNaVopros implements JsonBodySerializableInterface
{
    /**
     * @param list<OtvetNaVopros> $answers
     */
    public function __construct(
        public string $turnId = '',
        public array $answers = [],
    ) {
    }
}