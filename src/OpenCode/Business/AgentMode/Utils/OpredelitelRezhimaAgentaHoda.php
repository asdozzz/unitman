<?php

declare(strict_types=1);

namespace App\OpenCode\Business\AgentMode\Utils;

use App\OpenCode\Business\AgentMode\Model\AgentMode;
use App\OpenCode\Business\AgentMode\Model\NastroykaRezhimaAgenta;
use App\OpenCode\Business\AgentMode\Port\AgentModeRepository;
use App\OpenCode\Business\AgentMode\ReadModel\RezhimAgentaHoda;

/**
 * Выбирает мод для хода: запрос -> мод сессии -> дефолт типа чата -> первый
 * включённый мод пользователя. Ошибка только у явно запрошенного недоступного
 * мода; отсутствие дефолта деградирует до legacy-хода без `--agent`.
 */
final class OpredelitelRezhimaAgentaHoda
{
    public const CHAT_UNIT = 'unit';
    public const CHAT_WORKSPACE = 'workspace';

    public function __construct(private AgentModeRepository $rezhimy)
    {
    }

    public function opredelit(
        string $accountId,
        string $chatType,
        ?string $requestedModeId = null,
        ?string $sessionModeId = null,
    ): RezhimAgentaHoda {
        $modes = $this->rezhimy->findDostupnye($accountId);

        if ($modes === []) {
            return RezhimAgentaHoda::bezRezhima();
        }

        $poId = [];

        foreach ($modes as $mode) {
            $poId[$mode->getId()] = $mode;
        }

        $nastroyki = $this->rezhimy->findNastroyki($accountId);
        $requested = $this->normalize($requestedModeId);

        if ($requested !== null) {
            if (!isset($poId[$requested])) {
                throw new \DomainException('opencode.agent_mode.not_found');
            }

            return $this->izModa($poId[$requested], $nastroyki[$requested] ?? null);
        }

        $session = $this->normalize($sessionModeId);

        if ($session !== null && isset($poId[$session])) {
            return $this->izModa($poId[$session], $nastroyki[$session] ?? null);
        }

        $defaulty = $this->rezhimy->findDefaulty($accountId);
        $default = $defaulty[$this->normalizeChatType($chatType)] ?? null;

        if ($default !== null && isset($poId[$default])) {
            return $this->izModa($poId[$default], $nastroyki[$default] ?? null);
        }

        foreach ($this->otsortirovat($modes, $nastroyki) as $mode) {
            $nastroyka = $nastroyki[$mode->getId()] ?? null;

            if ($nastroyka !== null && !$nastroyka->enabled) {
                continue;
            }

            return $this->izModa($mode, $nastroyka);
        }

        return RezhimAgentaHoda::bezRezhima();
    }

    public function normalizeChatType(string $chatType): string
    {
        $chatType = strtolower(trim($chatType));

        return $chatType === self::CHAT_WORKSPACE ? self::CHAT_WORKSPACE : self::CHAT_UNIT;
    }

    /**
     * @param list<AgentMode> $modes
     * @param array<string, NastroykaRezhimaAgenta> $nastroyki
     *
     * @return list<AgentMode>
     */
    private function otsortirovat(array $modes, array $nastroyki): array
    {
        usort($modes, static function (AgentMode $left, AgentMode $right) use ($nastroyki): int {
            $leftSetting = $nastroyki[$left->getId()] ?? null;
            $rightSetting = $nastroyki[$right->getId()] ?? null;

            $byOrder = ($leftSetting?->sortOrder ?? 0) <=> ($rightSetting?->sortOrder ?? 0);

            if ($byOrder !== 0) {
                return $byOrder;
            }

            $byGlobal = ($right->isGlobal() ? 1 : 0) <=> ($left->isGlobal() ? 1 : 0);

            if ($byGlobal !== 0) {
                return $byGlobal;
            }

            return $left->getName() <=> $right->getName();
        });

        return $modes;
    }

    private function izModa(AgentMode $mode, ?NastroykaRezhimaAgenta $nastroyka): RezhimAgentaHoda
    {
        return new RezhimAgentaHoda(
            $mode->getId(),
            $mode->getName(),
            $mode->agentName(),
            $mode->getPrompt(),
            $mode->zapreshchaetPravki(),
            $nastroyka?->credentialId,
            $nastroyka?->model,
        );
    }

    private function normalize(?string $value): ?string
    {
        if ($value === null) {
            return null;
        }

        $value = trim($value);

        return $value === '' ? null : $value;
    }
}
