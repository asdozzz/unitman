<?php

declare(strict_types=1);

namespace App\Notification\Business\UseCase;

use App\Notification\Business\Model\TipUvedomleniya;
use App\Notification\Business\Port\NastroikiUvedomleniyRepository;
use App\Notification\Business\Port\UmeetOpredelyatPolzovatelya;
use App\Notification\Business\ReadModel\NastroikaUvedomleniyaReadModel;

final class PoluchitNastroikiUvedomleniyQuery
{
    public function __construct(
        private NastroikiUvedomleniyRepository $nastroiki,
        private UmeetOpredelyatPolzovatelya $polzovatel,
    ) {
    }

    /**
     * @return list<NastroikaUvedomleniyaReadModel>
     */
    public function handle(): array
    {
        $accountId = $this->polzovatel->tekushiyAccountId();
        $isAdmin = $this->polzovatel->isAdmin();
        $sokhranennie = $this->nastroiki->findDlyaAccounta($accountId);
        $result = [];

        foreach (TipUvedomleniya::vse() as $type) {
            if ($type->dlyaAdministratorov() && !$isAdmin) {
                continue;
            }

            $enabled = $type->sistemniy() || ($sokhranennie[$type->value] ?? true);

            $result[] = new NastroikaUvedomleniyaReadModel(
                $type,
                $enabled,
                $type->vazhnostPoUmolchaniyu(),
                $type->dlyaAdministratorov(),
                $type->sistemniy(),
                !$type->sistemniy(),
            );
        }

        return $result;
    }
}
