<?php

declare(strict_types=1);

namespace App\Skill\Business\Utils;

use App\Skill\Business\Model\NavykOpenCode;
use App\Skill\Business\Port\NavykRepository;
use App\Skill\Business\ReadModel\EffektivnyyNavyk;
use App\Skill\Business\ReadModel\RezultatEffektivnyhNavykov;

/**
 * Сборка эффективного набора навыков.
 *
 * Правила:
 *  - набор = все неархивные глобальные навыки + проектные навыки, привязанные
 *    хотя бы к одному из запрошенных проектов;
 *  - дубликаты по `id` отбрасываются;
 *  - сначала идут глобальные навыки, затем проектные; внутри каждой группы
 *    порядок детерминирован: [name, slug, id];
 *  - для проектного навыка `projectId` — первый проект из запроса, к которому
 *    навык привязан;
 *  - лимиты (число навыков и суммарные байты содержимого) применяются
 *    последовательно; первое превышение включает `truncated = true` и
 *    останавливает добавление (детерминированное усечение).
 */
final class PostroenieEffektivnyhNavykov
{
    public const MAX_SKILLS = 50;
    public const MAX_TOTAL_BYTES = 524288;

    public function __construct(private readonly NavykRepository $repository)
    {
    }

    /**
     * @param list<string> $projectIds
     */
    public function postroit(
        array $projectIds,
        int $maxSkills = self::MAX_SKILLS,
        int $maxTotalBytes = self::MAX_TOTAL_BYTES,
    ): RezultatEffektivnyhNavykov {
        $maxSkills = max(0, $maxSkills);
        $maxTotalBytes = max(0, $maxTotalBytes);

        $unikalnyeProekty = $this->unikalnye($projectIds);

        $globalnye = $this->repository->listGlobal();
        $proektnye = $unikalnyeProekty === []
            ? []
            : $this->repository->listForProjects($unikalnyeProekty);

        usort($globalnye, $this->sravnit(...));
        usort($proektnye, $this->sravnit(...));

        /** @var list<array{0: NavykOpenCode, 1: ?string}> $kandidaty */
        $kandidaty = [];
        $uvidennie = [];

        foreach ($globalnye as $navyk) {
            $id = $navyk->getId();

            if (isset($uvidennie[$id])) {
                continue;
            }

            $uvidennie[$id] = true;
            $kandidaty[] = [$navyk, null];
        }

        foreach ($proektnye as $navyk) {
            $id = $navyk->getId();

            if (isset($uvidennie[$id])) {
                continue;
            }

            $projectId = $this->pervyyProekt($navyk, $unikalnyeProekty);

            if ($projectId === null) {
                continue;
            }

            $uvidennie[$id] = true;
            $kandidaty[] = [$navyk, $projectId];
        }

        $otobrannye = [];
        $vsegoBayt = 0;
        $obrezano = false;

        foreach ($kandidaty as [$navyk, $projectId]) {
            if (count($otobrannye) >= $maxSkills) {
                $obrezano = true;
                break;
            }

            $bayt = strlen($navyk->getContent());

            if ($vsegoBayt + $bayt > $maxTotalBytes) {
                $obrezano = true;
                break;
            }

            $vsegoBayt += $bayt;
            $otobrannye[] = EffektivnyyNavyk::izModela($navyk, $projectId);
        }

        return new RezultatEffektivnyhNavykov($otobrannye, $obrezano);
    }

    /**
     * @param list<string> $projectIds
     *
     * @return list<string>
     */
    private function unikalnye(array $projectIds): array
    {
        $rezultat = [];

        foreach ($projectIds as $projectId) {
            $projectId = trim($projectId);

            if ($projectId === '' || in_array($projectId, $rezultat, true)) {
                continue;
            }

            $rezultat[] = $projectId;
        }

        return $rezultat;
    }

    /**
     * @param list<string> $projectIds
     */
    private function pervyyProekt(NavykOpenCode $navyk, array $projectIds): ?string
    {
        $privyazki = $navyk->getProjectIds();

        foreach ($projectIds as $projectId) {
            if (in_array($projectId, $privyazki, true)) {
                return $projectId;
            }
        }

        return null;
    }

    private function sravnit(NavykOpenCode $a, NavykOpenCode $b): int
    {
        return [$a->getName(), $a->getSlug(), $a->getId()]
            <=> [$b->getName(), $b->getSlug(), $b->getId()];
    }
}
