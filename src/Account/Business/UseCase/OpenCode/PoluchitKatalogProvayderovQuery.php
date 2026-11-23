<?php

declare(strict_types=1);

namespace App\Account\Business\UseCase\OpenCode;

use App\Account\Business\Model\OpenCode\ProvayderOpenCode;
use App\Account\Business\ReadModel\ProvayderOpenCodeReadModel;

/**
 * Статический каталог провайдеров OpenCode для формы реквизитов.
 */
final class PoluchitKatalogProvayderovQuery
{
    /**
     * @return list<ProvayderOpenCodeReadModel>
     */
    public function handle(): array
    {
        return ProvayderOpenCode::katalog();
    }
}
