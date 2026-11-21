<?php

declare(strict_types=1);

namespace App\OpenCode\Business\ReadModel;

/**
 * Ссылка на сервис workspace (аналог юнитного `UnitLinkHolder`).
 */
final class WorkspaceLinkHolder
{
    public function __construct(
        public readonly string $link,
    ) {
    }

    /**
     * @return array{link: string}
     */
    public function toArray(): array
    {
        return ['link' => $this->link];
    }
}
