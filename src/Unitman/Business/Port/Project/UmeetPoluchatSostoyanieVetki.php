<?php

namespace App\Unitman\Business\Port\Project;

use App\Unitman\Business\Model\Repo;
use App\Unitman\Business\ReadModel\SostoyanieVetki;

interface UmeetPoluchatSostoyanieVetki
{
    /**
     * Возвращает состояние ветки и время её HEAD-коммита.
     *
     * `$mainBranch` нужен, чтобы отличить слияние в основную ветку от
     * обычного обновления. Для самой основной ветки слияние не проверяется.
     */
    public function poluchitSostoyanieVetki(
        Repo $repo,
        string $projectCode,
        string $branch,
        string $mainBranch,
    ): SostoyanieVetki;
}
