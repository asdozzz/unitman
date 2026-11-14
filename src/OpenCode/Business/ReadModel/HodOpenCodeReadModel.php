<?php

declare(strict_types=1);

namespace App\OpenCode\Business\ReadModel;

use App\OpenCode\Business\Model\HodOpenCode;

/**
 * Ход для отдачи во фронтенд.
 *
 * `result` приходит в формате контракта раннера (`Success`, `Kind`,
 * `Questions`, `Answer`, `ErrorKey`) — API-ключа в нём нет по построению.
 *
 * `progressChannel` вычисляется Runner'ом и передаётся снаружи: бизнес-слой
 * OpenCode не имеет права знать формат имени канала Centrifugo.
 */
final class HodOpenCodeReadModel
{
    /**
     * @param list<array{QuestionId: string, OptionId: string|null, Text: string|null}> $answers
     * @param array<string, mixed>|null $result
     */
    public function __construct(
        public readonly string $id,
        public readonly string $sessionId,
        public readonly int $seq,
        public readonly string $status,
        public readonly string $requestText,
        public readonly array $answers,
        public readonly ?array $result,
        public readonly ?string $errorKey,
        public readonly int $createdAt,
        public readonly ?int $finishedAt,
        public readonly string $progressChannel,
    ) {
    }

    public static function izModela(HodOpenCode $turn, string $progressChannel): self
    {
        return new self(
            $turn->getId(),
            $turn->getSessionId(),
            $turn->getSeq(),
            $turn->getStatus()->value,
            $turn->getRequestText(),
            $turn->getAnswers(),
            $turn->getResult()?->toArray(),
            $turn->getErrorKey(),
            $turn->getCreatedAt(),
            $turn->getFinishedAt(),
            $progressChannel,
        );
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return [
            'id' => $this->id,
            'sessionId' => $this->sessionId,
            'seq' => $this->seq,
            'status' => $this->status,
            'requestText' => $this->requestText,
            'answers' => $this->answers,
            'result' => $this->result,
            'errorKey' => $this->errorKey,
            'createdAt' => $this->createdAt,
            'finishedAt' => $this->finishedAt,
            'progressChannel' => $this->progressChannel,
        ];
    }
}