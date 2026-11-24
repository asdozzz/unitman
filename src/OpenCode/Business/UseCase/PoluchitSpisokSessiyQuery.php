<?php

declare(strict_types=1);

namespace App\OpenCode\Business\UseCase;

use App\OpenCode\Business\Command\PoluchitSpisokSessiy;
use App\OpenCode\Business\Model\ProektWorkspace;
use App\OpenCode\Business\Model\SessiyaOpenCode;
use App\OpenCode\Business\Model\SessiyaOpenCodeFilter;
use App\OpenCode\Business\Model\TipSessiiOpenCode;
use App\OpenCode\Business\Port\SessiyaOpenCodeRepository;
use App\OpenCode\Business\Port\UmeetProveryatRekvizityOpenCode;
use App\OpenCode\Business\Port\UmeetUpravlyatProektamiWorkspace;
use App\OpenCode\Business\ReadModel\SessiyaOpenCodeReadModel;
use App\OpenCode\Business\Utils\SlugWorkspace;

/**
 * Сессии текущего пользователя: фильтр по владельцу задаёт сам запрос,
 * поэтому чужую сессию получить этим эндпоинтом нельзя.
 *
 * По умолчанию показываются все сессии, включая архивные: архив — это состояние,
 * а не удаление, и пользователь должен видеть свои прошлые диалоги.
 *
 * Для workspace-сессий вычисляется `ideLink`. Если Unitman недоступен или у
 * первого проекта нет публичного адреса раннера, ссылка остаётся null — список при этом
 * продолжает работать.
 */
final class PoluchitSpisokSessiyQuery
{
    public function __construct(
        private SessiyaOpenCodeRepository $sessionRepository,
        private UmeetProveryatRekvizityOpenCode $credentials,
        private ?UmeetUpravlyatProektamiWorkspace $projectami = null,
    ) {
    }

    /**
     * @return list<SessiyaOpenCodeReadModel>
     */
    public function handle(PoluchitSpisokSessiy $query): array
    {
        $accountId = $this->credentials->tekuschiiAccountId();

        $filter = new SessiyaOpenCodeFilter(
            $query->onlyActive,
            $query->search,
            $this->tip($query->type),
        );

        $result = [];

        foreach ($this->sessionRepository->findByAccountId($accountId, $filter) as $session) {
            $result[] = SessiyaOpenCodeReadModel::izModela(
                $session,
                $this->ideLink($session, $accountId),
            );
        }

        return $result;
    }

    private function tip(?string $type): ?TipSessiiOpenCode
    {
        if ($type === null || trim($type) === '') {
            return null;
        }

        return TipSessiiOpenCode::tryFrom(trim($type));
    }

    private function ideLink(SessiyaOpenCode $session, string $accountId): ?string
    {
        if (!$session->estWorkspace() || $this->projectami === null) {
            return null;
        }

        $first = $session->getWorkspace()?->getProjects()[0] ?? null;

        if (!$first instanceof ProektWorkspace) {
            return null;
        }

        try {
            return $this->projectami->poluchitSsylkuRedaktora(
                $first->projectId,
                $accountId,
                SlugWorkspace::dlya($session->getTitle(), $session->getId()),
            );
        } catch (\Throwable) {
            return null;
        }
    }
}
