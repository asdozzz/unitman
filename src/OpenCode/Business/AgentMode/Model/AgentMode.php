<?php

declare(strict_types=1);

namespace App\OpenCode\Business\AgentMode\Model;

use App\OpenCode\Business\AgentMode\Utils\ProverkaRezhimaAgenta;

/**
 * Мод агента OpenCode: именованный системный промпт и режим доступа.
 *
 * Общий мод (`is_global = true`) управляется администратором и доступен всем.
 * Персональный мод принадлежит одному аккаунту. Архивный мод остаётся в
 * истории, но не участвует в выборе новых ходов.
 */
final class AgentMode
{
    public const ACCESS_BUILD = 'build';
    public const ACCESS_PLAN = 'plan';

    private function __construct(
        private readonly string $id,
        private string $slug,
        private string $name,
        private string $description,
        private string $prompt,
        private string $accessMode,
        private bool $isGlobal,
        private readonly ?string $ownerAccountId,
        private int $version,
        private readonly string $createdBy,
        private string $updatedBy,
        private readonly int $createdAt,
        private int $updatedAt,
        private ?int $archivedAt,
    ) {
    }

    public static function create(
        string $id,
        ?string $slug,
        string $name,
        string $description,
        string $prompt,
        string $accessMode,
        bool $isGlobal,
        ?string $ownerAccountId,
        string $createdBy,
        int $now,
    ): self {
        ProverkaRezhimaAgenta::proveritId($id);
        $slug = ProverkaRezhimaAgenta::normalizovatSlug($slug, $name);
        ProverkaRezhimaAgenta::proveritSlug($slug);
        ProverkaRezhimaAgenta::proveritNazvanie($name);
        ProverkaRezhimaAgenta::proveritOpisanie($description);
        ProverkaRezhimaAgenta::proveritPrompt($prompt);
        ProverkaRezhimaAgenta::proveritRezhimDostupa($accessMode);
        ProverkaRezhimaAgenta::proveritOblast($isGlobal, $ownerAccountId);

        return new self(
            $id,
            $slug,
            $name,
            $description,
            $prompt,
            $accessMode,
            $isGlobal,
            $isGlobal ? null : $ownerAccountId,
            1,
            $createdBy,
            $createdBy,
            $now,
            $now,
            null,
        );
    }

    public static function restore(
        string $id,
        string $slug,
        string $name,
        string $description,
        string $prompt,
        string $accessMode,
        bool $isGlobal,
        ?string $ownerAccountId,
        int $version,
        string $createdBy,
        string $updatedBy,
        int $createdAt,
        int $updatedAt,
        ?int $archivedAt,
    ): self {
        return new self(
            $id,
            $slug,
            $name,
            $description,
            $prompt,
            $accessMode,
            $isGlobal,
            $ownerAccountId,
            $version,
            $createdBy,
            $updatedBy,
            $createdAt,
            $updatedAt,
            $archivedAt,
        );
    }

    /**
     * Изменение мода. `null` означает «оставить как есть».
     *
     * Возвращает `true`, если изменилось содержимое и версия была поднята.
     */
    public function izmenit(
        string $updatedBy,
        int $now,
        ?string $slug = null,
        ?string $name = null,
        ?string $description = null,
        ?string $prompt = null,
        ?string $accessMode = null,
    ): bool {
        $noviySlug = $slug !== null ? trim($slug) : $this->slug;
        $novoeNazvanie = $name !== null ? trim($name) : $this->name;
        $novoeOpisanie = $description !== null ? $description : $this->description;
        $noviyPrompt = $prompt !== null ? $prompt : $this->prompt;
        $noviyRezhim = $accessMode !== null ? $accessMode : $this->accessMode;

        if ($noviySlug === '') {
            $noviySlug = ProverkaRezhimaAgenta::normalizovatSlug(null, $novoeNazvanie);
        }

        ProverkaRezhimaAgenta::proveritSlug($noviySlug);
        ProverkaRezhimaAgenta::proveritNazvanie($novoeNazvanie);
        ProverkaRezhimaAgenta::proveritOpisanie($novoeOpisanie);
        ProverkaRezhimaAgenta::proveritPrompt($noviyPrompt);
        ProverkaRezhimaAgenta::proveritRezhimDostupa($noviyRezhim);

        $izmenilos = $noviyPrompt !== $this->prompt
            || $novoeNazvanie !== $this->name
            || $novoeOpisanie !== $this->description
            || $noviyRezhim !== $this->accessMode;

        $this->slug = $noviySlug;
        $this->name = $novoeNazvanie;
        $this->description = $novoeOpisanie;
        $this->prompt = $noviyPrompt;
        $this->accessMode = $noviyRezhim;

        if ($izmenilos) {
            ++$this->version;
            $this->updatedBy = $updatedBy;
            $this->updatedAt = $now;
        }

        return $izmenilos;
    }

    public function arhivirovat(string $updatedBy, int $now): void
    {
        if ($this->archivedAt !== null) {
            return;
        }

        $this->archivedAt = $now;
        $this->updatedBy = $updatedBy;
        $this->updatedAt = $now;
    }

    public function dostupen(string $accountId): bool
    {
        if ($this->archivedAt !== null) {
            return false;
        }

        return $this->isGlobal || ($this->ownerAccountId !== null && $this->ownerAccountId === $accountId);
    }

    public function prinadlezhit(string $accountId): bool
    {
        return !$this->isGlobal && $this->ownerAccountId === $accountId;
    }

    /** Имя агента в конфиге OpenCode и аргументе `--agent`. */
    public function agentName(): string
    {
        return 'unitman-' . $this->slug;
    }

    /** plan-подобный мод запрещает правку файлов. */
    public function zapreshchaetPravki(): bool
    {
        return $this->accessMode === self::ACCESS_PLAN;
    }

    public function getId(): string
    {
        return $this->id;
    }

    public function getSlug(): string
    {
        return $this->slug;
    }

    public function getName(): string
    {
        return $this->name;
    }

    public function getDescription(): string
    {
        return $this->description;
    }

    public function getPrompt(): string
    {
        return $this->prompt;
    }

    public function getAccessMode(): string
    {
        return $this->accessMode;
    }

    public function isGlobal(): bool
    {
        return $this->isGlobal;
    }

    public function getOwnerAccountId(): ?string
    {
        return $this->ownerAccountId;
    }

    public function getVersion(): int
    {
        return $this->version;
    }

    public function getCreatedBy(): string
    {
        return $this->createdBy;
    }

    public function getUpdatedBy(): string
    {
        return $this->updatedBy;
    }

    public function getCreatedAt(): int
    {
        return $this->createdAt;
    }

    public function getUpdatedAt(): int
    {
        return $this->updatedAt;
    }

    public function getArchivedAt(): ?int
    {
        return $this->archivedAt;
    }

    public function isArchived(): bool
    {
        return $this->archivedAt !== null;
    }
}
