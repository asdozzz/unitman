<?php

declare(strict_types=1);

namespace App\OpenCode\Business\UseCase;

use App\OpenCode\Business\Command\ArhivirovatSessiyu;
use App\OpenCode\Business\Model\SessiyaOpenCode;
use App\OpenCode\Business\Port\SessiyaOpenCodeRepository;
use App\OpenCode\Business\Port\UmeetProveryatRekvizityOpenCode;

/**
 * Архивация сессии OpenCode текущего пользователя.
 *
 * Полноценное удаление заменено архивацией: сессия остаётся в списке с флагом
 * `archived` и причиной, история ходов не стирается. Архивация идемпотентна —
 * повторный вызов не меняет время и причину первой архивации.
 */
final class ArhivirovatSessiyuUseCase
{
    public function __construct(
        private SessiyaOpenCodeRepository $sessionRepository,
        private UmeetProveryatRekvizityOpenCode $credentials,
    ) {
    }

    /**
     * @throws \DomainException если идентификатор пуст, сессия не найдена
     *                          или принадлежит другому пользователю
     */
    public function handle(ArhivirovatSessiyu $command): void
    {
        $accountId = $this->credentials->tekuschiiAccountId();
        $sessionId = trim($command->sessionId);

        if ($sessionId === '') {
            throw new \DomainException('opencode.session.id_required');
        }

        $session = $this->sessionRepository->getById($sessionId);
        $session->trebovatVladelca($accountId);

        $session->arhivirovat(SessiyaOpenCode::ARCHIVE_REASON_MANUAL, time());

        $this->sessionRepository->update($session);
    }
}