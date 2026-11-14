<?php

declare(strict_types=1);

namespace App\OpenCode\Business\Model;

/**
 * Один ход диалога: промпт пользователя + результат раннера.
 *
 * Ход необратим: как только раннер вернул результат, статус фиксируется
 * (AWAITING_INPUT или ANSWERED) и ход больше не меняется. Ответ пользователя
 * на вопрос создаёт **новый** ход, поэтому стейт-машина остаётся линейной.
 */
final class HodOpenCode
{
    /**
     * @param list<array{QuestionId: string, OptionId: string|null, Text: string|null}> $answers
     */
    private function __construct(
        private string $id,
        private string $sessionId,
        private int $seq,
        private string $workflowId,
        private StatusHoda $status,
        private string $requestText,
        private array $answers,
        private ?RezultatHodaOpenCode $result,
        private ?string $errorKey,
        private int $createdAt,
        private ?int $finishedAt,
    ) {
    }

    /**
     * @param list<array{QuestionId: string, OptionId: string|null, Text: string|null}> $answers
     */
    public static function create(
        string $id,
        string $sessionId,
        int $seq,
        string $workflowId,
        string $requestText,
        array $answers,
        int $now,
    ): self {
        if (trim($requestText) === '') {
            throw new \DomainException('opencode.turn.request_required');
        }

        return new self(
            $id,
            $sessionId,
            $seq,
            $workflowId,
            StatusHoda::RUNNING,
            $requestText,
            $answers,
            null,
            null,
            $now,
            null,
        );
    }

    /**
     * @param list<array{QuestionId: string, OptionId: string|null, Text: string|null}> $answers
     */
    public static function restore(
        string $id,
        string $sessionId,
        int $seq,
        string $workflowId,
        string $status,
        string $requestText,
        array $answers,
        ?RezultatHodaOpenCode $result,
        ?string $errorKey,
        int $createdAt,
        ?int $finishedAt,
    ): self {
        return new self(
            $id,
            $sessionId,
            $seq,
            $workflowId,
            StatusHoda::poStrokoi($status),
            $requestText,
            $answers,
            $result,
            $errorKey,
            $createdAt,
            $finishedAt,
        );
    }

    public function getId(): string
    {
        return $this->id;
    }

    public function getSessionId(): string
    {
        return $this->sessionId;
    }

    public function getSeq(): int
    {
        return $this->seq;
    }

    public function getWorkflowId(): string
    {
        return $this->workflowId;
    }

    public function getStatus(): StatusHoda
    {
        return $this->status;
    }

    public function getRequestText(): string
    {
        return $this->requestText;
    }

    /**
     * @return list<array{QuestionId: string, OptionId: string|null, Text: string|null}>
     */
    public function getAnswers(): array
    {
        return $this->answers;
    }

    public function getResult(): ?RezultatHodaOpenCode
    {
        return $this->result;
    }

    public function getErrorKey(): ?string
    {
        return $this->errorKey;
    }

    public function getCreatedAt(): int
    {
        return $this->createdAt;
    }

    public function getFinishedAt(): ?int
    {
        return $this->finishedAt;
    }

    /**
     * Ход ещё не завершён — результата нет, повторный опрос имеет смысл.
     */
    public function estVProzesse(): bool
    {
        return $this->status === StatusHoda::RUNNING;
    }

    public function zhdetOtveta(): bool
    {
        return $this->status->trebuetOtveta();
    }

    public function prinadlezhitSessii(string $sessionId): bool
    {
        return $this->sessionId === $sessionId;
    }

    /**
     * Применяет результат раннера. Повторный вызов с тем же результатом
     * безопасен: статус уже зафиксирован, ничего не меняется.
     */
    public function primenitRezultat(RezultatHodaOpenCode $result, int $now): void
    {
        if (!$this->estVProzesse()) {
            return;
        }

        $this->result = $result;
        $this->finishedAt = $now;

        if ($result->estOshibkoi()) {
            $this->status = StatusHoda::FAILED;
            $this->errorKey = $result->errorKey ?? 'opencode.turn.failed';

            return;
        }

        if ($result->estVoprosom()) {
            $this->status = StatusHoda::AWAITING_INPUT;

            return;
        }

        if (!$result->estOtvetom()) {
            // Неизвестный Kind нельзя трактовать как ответ: иначе ход закрылся
            // бы «успешно» без текста, и пользователь потерял бы результат.
            $this->status = StatusHoda::FAILED;
            $this->errorKey = 'opencode.turn.unexpected_kind';

            return;
        }

        $this->status = StatusHoda::ANSWERED;
    }

    public function otmetitOshibku(string $errorKey, int $now): void
    {
        if (!$this->estVProzesse()) {
            return;
        }

        $this->status = StatusHoda::FAILED;
        $this->errorKey = $errorKey;
        $this->finishedAt = $now;
    }

    /**
     * @return array<string, mixed>
     *
     * @psalm-return array{
     *     id: string,
     *     sessionId: string,
     *     seq: int,
     *     workflowId: string,
     *     status: string,
     *     requestText: string,
     *     answers: list<array{QuestionId: string, OptionId?: string|null, Text?: string|null}>,
     *     result: array<string, mixed>|null,
     *     errorKey: string|null,
     *     createdAt: int,
     *     finishedAt: int|null
     * }
     */
    public function toArray(): array
    {
        return [
            'id' => $this->id,
            'sessionId' => $this->sessionId,
            'seq' => $this->seq,
            'workflowId' => $this->workflowId,
            'status' => $this->status->value,
            'requestText' => $this->requestText,
            'answers' => $this->answers,
            'result' => $this->result?->toArray(),
            'errorKey' => $this->errorKey,
            'createdAt' => $this->createdAt,
            'finishedAt' => $this->finishedAt,
        ];
    }
}