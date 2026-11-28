<?php

declare(strict_types=1);

namespace App\OpenCode\Business\Workspace\Utils;

use App\OpenCode\Business\Model\HodOpenCode;
use App\OpenCode\Business\Model\RezultatHodaOpenCode;
use App\OpenCode\Business\Port\UmeetPeredavatUvedomleniyaOpenCode;
use App\OpenCode\Business\Port\UmeetVypolnyatHodOpenCode;
use App\OpenCode\Business\Port\UuidGenerator;
use App\OpenCode\Business\ReadModel\UvedomlenieONovomSoobsheniiOpenCode;
use App\OpenCode\Business\Workspace\Model\WorkspaceSession;
use App\OpenCode\Business\Workspace\Port\WorkspaceNotificationsRepository;
use App\OpenCode\Business\Workspace\Port\WorkspaceParticipantsRepository;
use App\OpenCode\Business\Workspace\Port\WorkspaceSessionRepository;
use App\OpenCode\Business\Workspace\Port\WorkspaceTurnRepository;
use Psr\Log\LoggerInterface;

/**
 * Завершение хода на самостоятельном workspace-агрегате.
 *
 * Сохраняет результат, запоминает внешнюю сессию, снимает резервацию и
 * рассылает уведомления всем участникам, кроме автора хода. Автор видит ответ
 * в открытом чате, остальным нужен сигнал о новом сообщении.
 */
final class ZavershenieHodaWorkspace
{
    private const MAX_POLUCHATELEI = 20;

    public function __construct(
        private readonly WorkspaceTurnRepository $turns,
        private readonly WorkspaceSessionRepository $workspaces,
        private readonly UmeetVypolnyatHodOpenCode $runner,
        private readonly WorkspaceNotificationsRepository $notifications,
        private readonly UmeetPeredavatUvedomleniyaOpenCode $publisher,
        private readonly UuidGenerator $uuidGenerator,
        private readonly LoggerInterface $logger,
        private readonly WorkspaceParticipantsRepository $uchastniki,
    ) {
    }

    public function zavershit(HodOpenCode $turn): HodOpenCode
    {
        if (!$turn->estVProzesse()) {
            return $turn;
        }

        $result = $this->runner->poluchitResultatHoda($turn->getWorkflowId());

        if ($result === null) {
            return $turn;
        }

        $workspace = $this->workspaces->getById($turn->getSessionId());
        $wasRunning = $turn->estVProzesse();
        $now = time();

        $turn->primenitRezultat($result, $now);
        $this->turns->update($turn);
        $this->zapomnitVneshnyuyuSessiyu($workspace, $result, $now);

        if (!$wasRunning || $turn->estVProzesse()) {
            return $turn;
        }

        $this->osvoboditSessiyu($turn);
        $this->sozdatUvedomleniya($turn, $workspace, $now);

        return $turn;
    }

    private function osvoboditSessiyu(HodOpenCode $turn): void
    {
        try {
            $this->turns->osvoboditSessiyu($turn->getSessionId(), $turn->getId());
        } catch (\Throwable) {
            // Аренда истечёт по TTL; результат хода важнее.
        }
    }

    private function zapomnitVneshnyuyuSessiyu(
        WorkspaceSession $workspace,
        RezultatHodaOpenCode $result,
        int $now,
    ): void {
        $externalSessionId = $result->externalSessionId;

        if ($externalSessionId === null || $externalSessionId === '') {
            return;
        }

        if ($workspace->getExternalSessionId() === $externalSessionId) {
            return;
        }

        $workspace->zapomnitVneshnyuyuSessiyu($externalSessionId, $now);
        $this->workspaces->update($workspace);
    }

    private function sozdatUvedomleniya(HodOpenCode $turn, WorkspaceSession $workspace, int $now): void
    {
        foreach ($this->poluchateli($turn, $workspace) as $accountId) {
            $this->sozdatUvedomlenieDlya($turn, $workspace, $accountId, $now);
        }
    }

    /**
     * Все явные участники плюс неявный владелец, минус автор хода.
     *
     * @return list<string>
     */
    private function poluchateli(HodOpenCode $turn, WorkspaceSession $workspace): array
    {
        $recipients = [];

        foreach ($this->uchastniki->findByWorkspaceId($workspace->getId()) as $uchastnik) {
            $recipients[$uchastnik->getAccountId()] = true;
        }

        $recipients[$workspace->getAccountId()] = true;

        $authorId = $turn->getAuthorAccountId();

        if ($authorId !== null) {
            unset($recipients[$authorId]);
        }

        return array_slice(array_keys($recipients), 0, self::MAX_POLUCHATELEI);
    }

    private function sozdatUvedomlenieDlya(
        HodOpenCode $turn,
        WorkspaceSession $workspace,
        string $accountId,
        int $now,
    ): void {
        $notification = new UvedomlenieONovomSoobsheniiOpenCode(
            $this->uuidGenerator->makeGuid(),
            $accountId,
            $turn->getSessionId(),
            $turn->getId(),
            $workspace->getName(),
            $turn->getStatus()->value,
            $turn->getResult()?->kind,
            $now,
            'workspace',
        );

        try {
            $saved = $this->notifications->save($notification);
        } catch (\Throwable $error) {
            $this->logger->error('opencode.turn.notification_save_failed', [
                'turnId' => $turn->getId(),
                'accountId' => $accountId,
                'exception' => $error,
            ]);

            return;
        }

        if (!$saved) {
            return;
        }

        try {
            $this->publisher->publish($notification);
        } catch (\Throwable $error) {
            // Публикация — best effort: строка уже сохранена и будет отдана API.
            $this->logger->error('opencode.turn.notification_publish_failed', [
                'notificationId' => $notification->id,
                'accountId' => $accountId,
                'turnId' => $turn->getId(),
                'exception' => $error,
            ]);
        }
    }
}
