<?php

declare(strict_types=1);

namespace App\OpenCode\Business\Model;

/**
 * Роль участника workspace-сессии.
 *
 * Иерархия строгая: owner > writer > viewer. Владелец управляет стендом и
 * составом, writer ведёт диалог, viewer только читает историю и состояние.
 * Отдельной роли «наблюдатель с правом управления» нет намеренно: управление
 * меняет общий стенд и права остальных участников.
 */
enum RolUchastnikaSessii: string
{
    case OWNER = 'owner';
    case WRITER = 'writer';
    case VIEWER = 'viewer';

    public function mozhetChitat(): bool
    {
        return true;
    }

    public function mozhetPisat(): bool
    {
        return $this !== self::VIEWER;
    }

    public function mozhetUpravlyat(): bool
    {
        return $this === self::OWNER;
    }

    /**
     * Достаточно ли роли для требуемой.
     *
     * Сравнение по явному весу, а не по порядку объявления кейсов: добавление
     * новой роли не должно незаметно менять семантику существующих проверок.
     */
    public function dostatochnoDlya(self $trebuemaya): bool
    {
        return $this->ves() >= $trebuemaya->ves();
    }

    public function ves(): int
    {
        return match ($this) {
            self::OWNER => 3,
            self::WRITER => 2,
            self::VIEWER => 1,
        };
    }

    /**
     * @throws \DomainException `opencode.participant.invalid_role`
     */
    public static function izStroki(string $rol): self
    {
        return self::tryFrom(trim($rol))
            ?? throw new \DomainException('opencode.participant.invalid_role');
    }
}
