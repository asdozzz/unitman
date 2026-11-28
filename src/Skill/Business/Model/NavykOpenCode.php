<?php

declare(strict_types=1);

namespace App\Skill\Business\Model;

use App\Skill\Business\Utils\ProverkaNavyka;

/**
 * Пользовательский навык OpenCode.
 *
 * Навык — это авторская конфигурация (инструкция), которую можно применять
 * глобально или к набору проектов. Привязка к проектам хранится отдельно
 * (`opencode_skill_project`) и в модели представлена списком идентификаторов:
 * FK на проекты намеренно нет — проекты удаляются асинхронно через event
 * sourcing, поэтому «висячие» project_id допустимы и вычищаются отдельно.
 *
 * Версия увеличивается только при изменении содержимого (name/description/
 * content), потому что `content_hash` считается по этим трём полям. Смена
 * slug или признака глобальности версию не поднимает. История версий пишется
 * репозиторием: на каждую новую версию появляется строка в
 * `opencode_skill_version`.
 */
final class NavykOpenCode
{
    /**
     * @param list<string> $projectIds
     */
    private function __construct(
        private readonly string $id,
        private string $slug,
        private string $name,
        private string $description,
        private string $content,
        private string $contentHash,
        private int $version,
        private bool $isGlobal,
        private readonly array $projectIds,
        private readonly string $createdBy,
        private string $updatedBy,
        private readonly int $createdAt,
        private int $updatedAt,
        private ?int $archivedAt,
    ) {
    }

    /**
     * @param list<string> $projectIds
     */
    public static function create(
        string $id,
        string $slug,
        string $name,
        string $description,
        string $content,
        bool $isGlobal,
        array $projectIds,
        string $createdBy,
        int $now,
    ): self {
        ProverkaNavyka::proveritId($id);
        ProverkaNavyka::proveritSlug($slug);
        ProverkaNavyka::proveritNazvanie($name);
        ProverkaNavyka::proveritOpisanie($description);
        ProverkaNavyka::proveritSoderzhimoe($content);

        $normalizovannyeProekty = ProverkaNavyka::normalizovatProekty($projectIds);
        ProverkaNavyka::proveritOblast($isGlobal, $normalizovannyeProekty);

        return new self(
            $id,
            $slug,
            $name,
            $description,
            $content,
            ProverkaNavyka::hashKontenta($name, $description, $content),
            1,
            $isGlobal,
            $normalizovannyeProekty,
            $createdBy,
            $createdBy,
            $now,
            $now,
            null,
        );
    }

    /**
     * Восстановление из хранилища. Не валидирует: данные уже прошли проверку
     * при записи, а старые записи не должны ломать чтение.
     *
     * @param list<string> $projectIds
     */
    public static function restore(
        string $id,
        string $slug,
        string $name,
        string $description,
        string $content,
        string $contentHash,
        int $version,
        bool $isGlobal,
        array $projectIds,
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
            $content,
            $contentHash,
            $version,
            $isGlobal,
            $projectIds,
            $createdBy,
            $updatedBy,
            $createdAt,
            $updatedAt,
            $archivedAt,
        );
    }

    /**
     * Изменение навыка. `null` означает «оставить как есть».
     *
     * Возвращает `true`, если изменилось содержимое и версия была поднята.
     * Смена slug/глобальности обновляет `updated_at`, но версию не меняет.
     */
    public function izmenit(
        string $updatedBy,
        int $now,
        ?string $slug = null,
        ?string $name = null,
        ?string $description = null,
        ?string $content = null,
        ?bool $isGlobal = null,
    ): bool {
        $noviySlug = $slug ?? $this->slug;
        $novoeNazvanie = $name ?? $this->name;
        $novoeOpisanie = $description ?? $this->description;
        $novoeSoderzhimoe = $content ?? $this->content;
        $novayaGlobalnost = $isGlobal ?? $this->isGlobal;

        ProverkaNavyka::proveritSlug($noviySlug);
        ProverkaNavyka::proveritNazvanie($novoeNazvanie);
        ProverkaNavyka::proveritOpisanie($novoeOpisanie);
        ProverkaNavyka::proveritSoderzhimoe($novoeSoderzhimoe);
        ProverkaNavyka::proveritOblast($novayaGlobalnost, $this->projectIds);

        $noviyHash = ProverkaNavyka::hashKontenta($novoeNazvanie, $novoeOpisanie, $novoeSoderzhimoe);
        $izmenilosSoderzhimoe = $noviyHash !== $this->contentHash;

        $izmenilsya = $izmenilosSoderzhimoe
            || $noviySlug !== $this->slug
            || $novayaGlobalnost !== $this->isGlobal;

        $this->slug = $noviySlug;
        $this->name = $novoeNazvanie;
        $this->description = $novoeOpisanie;
        $this->content = $novoeSoderzhimoe;
        $this->isGlobal = $novayaGlobalnost;

        if ($izmenilosSoderzhimoe) {
            $this->contentHash = $noviyHash;
            ++$this->version;
        }

        if ($izmenilsya) {
            $this->updatedBy = $updatedBy;
            $this->updatedAt = $now;
        }

        return $izmenilosSoderzhimoe;
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

    public function isArchived(): bool
    {
        return $this->archivedAt !== null;
    }

    public function isGlobal(): bool
    {
        return $this->isGlobal;
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

    public function getContent(): string
    {
        return $this->content;
    }

    public function getContentHash(): string
    {
        return $this->contentHash;
    }

    public function getVersion(): int
    {
        return $this->version;
    }

    /**
     * @return list<string>
     */
    public function getProjectIds(): array
    {
        return $this->projectIds;
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
}
