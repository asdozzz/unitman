<?php

declare(strict_types=1);

namespace App\Account\Business\ReadModel;

/**
 * Результат загрузки списка моделей провайдера.
 *
 * `source` равен `live`, если список получен от провайдера, и `manual`, если
 * провайдер не поддерживает live-список либо запрос завершился ошибкой — тогда
 * `models` пуст, а форма предлагает ручной ввод. `errorKey` содержит ключ
 * ошибки перевода и `null` при успехе или отсутствии live-списка.
 */
final class SpisokModeleiReadModel
{
    /**
     * @param list<string> $models
     */
    public function __construct(
        public readonly string $provider,
        public readonly array $models,
        public readonly string $source,
        public readonly ?string $errorKey,
        public readonly ?int $providerStatus = null,
    ) {
    }
}
