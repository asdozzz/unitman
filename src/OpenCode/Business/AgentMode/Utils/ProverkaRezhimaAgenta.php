<?php

declare(strict_types=1);

namespace App\OpenCode\Business\AgentMode\Utils;

use App\OpenCode\Business\AgentMode\Model\AgentMode;

/**
 * Валидация мода агента. Границы совпадают с миграцией и фронтенд-формой.
 */
final class ProverkaRezhimaAgenta
{
    public const PROMPT_MAX_BYTES = 64 * 1024;

    public static function proveritId(string $id): void
    {
        if (trim($id) === '' || strlen($id) > 64) {
            throw new \DomainException('opencode.agent_mode.invalid_id');
        }
    }

    public static function proveritSlug(string $slug): void
    {
        if ($slug === '') {
            throw new \DomainException('opencode.agent_mode.slug_required');
        }

        if (strlen($slug) > 64 || preg_match('/^[a-z0-9][a-z0-9-]{0,63}$/', $slug) !== 1) {
            throw new \DomainException('opencode.agent_mode.invalid_slug');
        }
    }

    public static function proveritNazvanie(string $name): void
    {
        $name = trim($name);

        if ($name === '') {
            throw new \DomainException('opencode.agent_mode.name_required');
        }

        if (mb_strlen($name) > 128) {
            throw new \DomainException('opencode.agent_mode.name_too_long');
        }
    }

    public static function proveritOpisanie(string $description): void
    {
        if (mb_strlen($description) > 512) {
            throw new \DomainException('opencode.agent_mode.description_too_long');
        }

        if (str_contains($description, "\0")) {
            throw new \DomainException('opencode.agent_mode.description_invalid');
        }
    }

    public static function proveritPrompt(string $prompt): void
    {
        if (trim($prompt) === '') {
            throw new \DomainException('opencode.agent_mode.prompt_required');
        }

        if (strlen($prompt) > self::PROMPT_MAX_BYTES) {
            throw new \DomainException('opencode.agent_mode.prompt_too_long');
        }

        if (!mb_check_encoding($prompt, 'UTF-8') || str_contains($prompt, "\0")) {
            throw new \DomainException('opencode.agent_mode.prompt_invalid');
        }
    }

    public static function proveritRezhimDostupa(string $accessMode): void
    {
        if (!in_array($accessMode, [AgentMode::ACCESS_BUILD, AgentMode::ACCESS_PLAN], true)) {
            throw new \DomainException('opencode.agent_mode.access_mode_invalid');
        }
    }

    public static function proveritOblast(bool $isGlobal, ?string $ownerAccountId): void
    {
        if ($isGlobal && $ownerAccountId !== null && trim($ownerAccountId) !== '') {
            throw new \DomainException('opencode.agent_mode.global_cannot_have_owner');
        }

        if (!$isGlobal && ($ownerAccountId === null || trim($ownerAccountId) === '')) {
            throw new \DomainException('opencode.agent_mode.owner_required');
        }
    }

    public static function normalizovatSlug(?string $slug, string $name): string
    {
        $slug = $slug !== null ? trim($slug) : '';

        if ($slug !== '') {
            return $slug;
        }

        // Транслитерация повторяет GeneraciyaSlugNavyka: достаточно для
        // латиницы и кириллицы, остальное схлопывается в дефисы.
        $translit = [
            'а' => 'a', 'б' => 'b', 'в' => 'v', 'г' => 'g', 'д' => 'd', 'е' => 'e',
            'ё' => 'e', 'ж' => 'zh', 'з' => 'z', 'и' => 'i', 'й' => 'j', 'к' => 'k',
            'л' => 'l', 'м' => 'm', 'н' => 'n', 'о' => 'o', 'п' => 'p', 'р' => 'r',
            'с' => 's', 'т' => 't', 'у' => 'u', 'ф' => 'f', 'х' => 'h', 'ц' => 'c',
            'ч' => 'ch', 'ш' => 'sh', 'щ' => 'sch', 'ъ' => '', 'ы' => 'y', 'ь' => '',
            'э' => 'e', 'ю' => 'yu', 'я' => 'ya',
        ];

        $value = mb_strtolower(trim($name));
        $value = strtr($value, $translit);
        $value = preg_replace('/[^a-z0-9]+/', '-', $value) ?? '';
        $value = trim($value, '-');

        if ($value === '') {
            $value = 'mode-' . substr(hash('sha256', $name), 0, 8);
        }

        return substr($value, 0, 64);
    }
}
