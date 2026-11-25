<?php

declare(strict_types=1);

namespace App\Notification\Business\UseCase;

use App\Notification\Business\Command\SozdatUvedomlenie;
use App\Notification\Business\Model\Uvedomlenie;
use App\Notification\Business\Port\CanGenerateGuid;
use App\Notification\Business\Port\NastroikiUvedomleniyRepository;
use App\Notification\Business\Port\UvedomleniyaRepository;

/**
 * Создаёт уведомление, отсекает отключённые несистемные типы и дедуплицирует.
 */
final class SozdatUvedomlenieUseCase
{
    public const PUSH_CHANNEL = 'push';

    public function __construct(
        private UvedomleniyaRepository $uvedomleniya,
        private NastroikiUvedomleniyRepository $nastroiki,
        private CanGenerateGuid $guid,
    ) {
    }

    public function handle(SozdatUvedomlenie $command): void
    {
        $recipientIds = $this->normalizovatPoluchateley($command->recipientIds);

        if ($recipientIds === []) {
            return;
        }

        if (!$command->type->sistemniy()) {
            $otkluchenni = $this->nastroiki->findOtkluchennih($command->type, $recipientIds);

            if ($otkluchenni !== []) {
                $recipientIds = array_values(array_diff($recipientIds, $otkluchenni));
            }
        }

        if ($recipientIds === []) {
            return;
        }

        if ($command->dedupKey !== null && $this->uvedomleniya->findByDedupKey($command->dedupKey) !== null) {
            return;
        }

        $uvedomlenie = Uvedomlenie::sozdat(
            $this->guid->makeGuid(),
            $command->type,
            $command->severity,
            $command->payload,
            $command->dedupKey,
            $command->createdAt ?? time(),
        );

        $this->uvedomleniya->save($uvedomlenie, $recipientIds, self::PUSH_CHANNEL);
    }

    /**
     * @param list<string> $recipientIds
     *
     * @return list<string>
     */
    private function normalizovatPoluchateley(array $recipientIds): array
    {
        $result = [];

        foreach ($recipientIds as $recipientId) {
            $recipientId = trim($recipientId);

            if ($recipientId !== '') {
                $result[$recipientId] = true;
            }
        }

        return array_keys($result);
    }
}
