<?php

declare(strict_types=1);

namespace App\OpenCode\Business\Model;

/**
 * Состояние юнита глазами OpenCode — без внутренних классов Unitman.
 *
 * `group` — результат единой матрицы готовности (`OcenkaGotovnostiUnita`):
 * сессия может принимать сообщения только в группе `ready`.
 *
 * Поля прав редактирования (`authorId`, `redaktirovanieVklucheno`,
 * `vetkaZashishena`, `redaktory`) — снимок для вычисления read-only режима
 * хода; сами права проверяет `OpredelitelRezhimaHodaOpenCode`.
 */
final class SostoyanieUnita
{
    public const GROUP_READY = 'ready';
    public const GROUP_BLOCKED = 'blocked';
    public const GROUP_ARCHIVED = 'archived';

    /**
     * @param bool $zanyat над юнитом идёт операция: активити Unitman или чужой ход OpenCode
     * @param string|null $prichinaZanyatosti `prozes` | `opencode_hod` — для подсказки в интерфейсе
     * @param string|null $authorId автор юнита
     * @param bool $redaktirovanieVklucheno режим редактирования включён автором
     * @param bool $vetkaZashishena ветка юнита входит в защищённые ветки проекта
     * @param list<string> $redaktory прикреплённые редакторы (идентификаторы пользователей)
     * @param bool $containerStateMismatch основной контейнер юнита отсутствует
     *        или остановлен, хотя ожидается запущенным
     */
    public function __construct(
        public readonly bool $exists,
        public readonly ?string $code,
        public readonly ?string $projectId,
        public readonly ?string $name,
        public readonly ?string $branch,
        public readonly string $group,
        public readonly bool $zanyat = false,
        public readonly ?string $prichinaZanyatosti = null,
        public readonly ?string $authorId = null,
        public readonly bool $redaktirovanieVklucheno = false,
        public readonly bool $vetkaZashishena = false,
        public readonly array $redaktory = [],
        public readonly bool $containerStateMismatch = false,
    )
    {
    }

    /**
     * Пользователь является автором или прикреплённым редактором юнита.
     */
    public function yavlyaetsyaRedaktorom(string $accountId): bool
    {
        $accountId = trim($accountId);

        if ($accountId === '') {
            return false;
        }

        if ($this->authorId !== null && $this->authorId === $accountId) {
            return true;
        }

        return in_array($accountId, $this->redaktory, true);
    }
}
