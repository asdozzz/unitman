<?php

declare(strict_types=1);

namespace App\Unitman\Business\Utils;

use App\Unitman\Business\Model\Unit\ConfigUnita\PomoschnikSsylki;

/**
 * Единое правило построения ссылки на сервис юнита.
 *
 * Для протокола `http` ссылка идёт через proxy host проекта:
 *   `{scheme}://{port}.{unit}.{project}.{host}[:{proxyPort}]`
 *
 * Для протоколов `ws` и `wss` ссылка также идёт через proxy host проекта,
 * но схема зависит от схемы proxy: `https` → `wss`, иначе `ws`:
 *   `{wsScheme}://{port}.{unit}.{project}.{host}[:{proxyPort}]`
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
     * `http`, `ws` и `wss` строятся через proxy host проекта; для `ws`/`wss`
     * схема ссылки выбирается по схеме proxy (`https` → `wss`, иначе `ws`).
     * Остальные протоколы строятся напрямую на юнит.
     *
     * @param int|string $port порт сервиса; из YAML может прийти строкой
     *
     * @throws \DomainException если proxy host проекта не содержит схему или хост
     */
    public static function sformirovat(
        ?string $type,
        int|string $port,
        string $unitName,
        string $projectName,
        ?string $proxyHost
    ): string {
        $type = self::normalizovatTip($type);
        $port = (int)$port;

        if (!in_array($type, ['http', 'ws', 'wss'], true)) {
            return $type.'://'.$unitName.'.'.$projectName.':'.$port;
        }

        $pathinfo = parse_url((string)$proxyHost);
        /** @var array|false $pathinfo */
        if ($pathinfo === false || empty($pathinfo['scheme']) || empty($pathinfo['host'])) {
            throw new \DomainException('unit.invalid_proxy_host');
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
