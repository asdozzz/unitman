<?php

declare(strict_types=1);

namespace App\Skill\Business\UseCase;

use App\Skill\Business\Command\PoluchitEffektivnieNavyki;
use App\Skill\Business\Utils\PostroenieEffektivnyhNavykov;
use App\Skill\Business\Utils\ProverkaNavyka;
use App\Skill\Business\Utils\ProverkaPravNavykov;

/**
 * Административная/диагностическая выборка эффективных навыков.
 *
 * Требуется членство хотя бы в одном из запрошенных проектов (или системный
 * администратор). Лимиты раннера не применяются: это отладочный вид.
 */
final class PoluchitEffektivnieNavykiQuery
{
    public function __construct(
        private readonly PostroenieEffektivnyhNavykov $postroenie,
        private readonly ProverkaPravNavykov $prava,
    ) {
    }

    /**
     * @return array<string, mixed>
     */
    public function handle(PoluchitEffektivnieNavyki $command): array
    {
        $projectIds = ProverkaNavyka::normalizovatProekty($command->projectIds);

        if ($projectIds === []) {
            $this->prava->tekuschiiAccountId();
        } else {
            $this->prava->trebovatChtenieProektnogo($projectIds);
        }

        return $this->postroenie->postroit($projectIds, PHP_INT_MAX, PHP_INT_MAX)->toArray();
    }
}
