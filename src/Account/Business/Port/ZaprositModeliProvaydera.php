<?php

declare(strict_types=1);

namespace App\Account\Business\Port;

use App\Account\Business\Model\OpenCode\ProvayderOpenCode;

/**
 * Live-запрос списка моделей у провайдера OpenAI-совместимого API.
 *
 * Базовый URL и способ авторизации берутся ТОЛЬКО из каталога провайдера —
 * это гарантия того, что подставленный из запроса host/protocol не приведёт
 * к утечке ключа на чужой адрес.
 */
interface ZaprositModeliProvaydera
{
    /**
     * @return list<string>
     *
     * @throws \DomainException если провайдер недоступен, вернул ошибку или пустой список
     */
    public function zaprosit(ProvayderOpenCode $provider, string $apiKey): array;
}
