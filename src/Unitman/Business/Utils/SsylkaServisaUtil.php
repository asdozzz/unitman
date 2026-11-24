<?php

declare(strict_types=1);

namespace App\Unitman\Business\Utils;

use App\Unitman\Business\Model\Unit\ConfigUnita\PomoschnikSsylki;

/**
 * Единое правило построения ссылки на сервис юнита.
 *
 * Пятый аргумент — публичный адрес (`publicAddress`) раннера, к которому
 * привязан юнит/workspace (multi-runner). Вызывающая сторона обязана получить
 * его через `RunnerPublicAddressProvider`; default/fallback host запрещён.
 *
 * Для протокола `http` ссылка идёт через публичный адрес раннера:
 *   `{scheme}://{port}.{unit}.{project}.{host}[:{publicPort}]`
 *
 * Для протоколов `ws` и `wss` ссылка также идёт через публичный адрес,
 * но схема зависит от схемы адреса: `https` → `wss`, иначе `ws`:
 *   `{wsScheme}://{port}.{unit}.{project}.{host}[:{publicPort}]`
 *
 * Для любого другого протокола (`tcp`, ...) — напрямую на юнит:
 *   `{type}://{unit}.{project}:{port}`
 *
 * Путь (`startUri` из конфига или `suffix` из запроса хелпера) дописывается
 * вызывающей стороной: правило одинаково для всех протоколов.
 */
final class SsylkaServisaUtil
{
    /**
     * Протокол приводится к нижнему регистру; пустое значение трактуется как `http`.
     */
    public static function normalizovatTip(?string $type): string
    {
        $type = strtolower(trim((string)$type));

        return $type === '' ? PomoschnikSsylki::DEFAULT_TYPE : $type;
    }

    /**
     * Базовая ссылка на сервис без пути.
     *
     * `http`, `ws` и `wss` строятся по `publicAddress` привязанного раннера;
     * для `ws`/`wss` схема ссылки выбирается по схеме адреса
     * (`https` → `wss`, иначе `ws`).
     * Остальные протоколы строятся напрямую на юнит.
     *
     * @param int|string $port порт сервиса; из YAML может прийти строкой
     *
     * @throws \DomainException `unit.runner_public_address_missing`, если
     *                          publicAddress раннера не содержит схему или хост
     */
    public static function sformirovat(
        ?string $type,
        int|string $port,
        string $unitName,
        string $projectName,
        ?string $publicAddress
    ): string {
        $type = self::normalizovatTip($type);
        $port = (int)$port;

        if (!in_array($type, ['http', 'ws', 'wss'], true)) {
            return $type.'://'.$unitName.'.'.$projectName.':'.$port;
        }

        $pathinfo = parse_url((string)$publicAddress);
        /** @var array|false $pathinfo */
        if ($pathinfo === false || empty($pathinfo['scheme']) || empty($pathinfo['host'])) {
            throw new \DomainException('unit.runner_public_address_missing');
        }

        $scheme = strtolower((string)$pathinfo['scheme']);
        if ($type === 'ws' || $type === 'wss') {
            $scheme = $scheme === 'https' ? 'wss' : 'ws';
        }

        $link = $scheme.'://'.$port.'.'.$unitName.'.'.$projectName.'.'.(string)$pathinfo['host'];
        if (!empty($pathinfo['port'])) {
            $link .= ':'.$pathinfo['port'];
        }

        return $link;
    }
}
