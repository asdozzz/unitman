<?php

declare(strict_types=1);

namespace App\OpenCode\Business\AgentMode\ReadModel;

/**
 * Разрешённый мод для конкретного хода.
 *
 * `modeId = null` означает legacy-ход без `--agent`: поведение полностью
 * совпадает с текущим. `credentialId`/`model` — значения из персональных
 * настроек мода, если они заданы.
 */
final class RezhimAgentaHoda
{
    public function __construct(
        public readonly ?string $modeId,
        public readonly ?string $modeName,
        public readonly ?string $agentName,
        public readonly ?string $prompt,
        public readonly bool $denyEdits,
        public readonly ?string $credentialId = null,
        public readonly ?string $model = null,
    ) {
    }

    public static function bezRezhima(): self
    {
        return new self(null, null, null, null, false);
    }

    public function estRezhim(): bool
    {
        return $this->modeId !== null && $this->agentName !== null && $this->prompt !== null;
    }
}
