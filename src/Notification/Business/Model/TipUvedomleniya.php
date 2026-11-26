<?php

declare(strict_types=1);

namespace App\Notification\Business\Model;

/**
 * Тип уведомления.
 *
 * Системные типы нельзя отключить в настройках: они создаются всегда.
 * `runner_threat` относится к системным, `unit_started` — к отключаемым.
 */
enum TipUvedomleniya: string
{
    case RUNNER_THREAT = 'runner_threat';
    case UNIT_STARTED = 'unit_started';
    case ACCOUNT_ACTIVATION_REQUEST = 'account_activation_request';

    public function sistemniy(): bool
    {
        return $this === self::RUNNER_THREAT || $this === self::ACCOUNT_ACTIVATION_REQUEST;
    }

    public function dlyaAdministratorov(): bool
    {
        return $this === self::RUNNER_THREAT || $this === self::ACCOUNT_ACTIVATION_REQUEST;
    }

    public function vazhnostPoUmolchaniyu(): UrovenVazhnosti
    {
        return match ($this) {
            self::RUNNER_THREAT => UrovenVazhnosti::CRITICAL,
            self::ACCOUNT_ACTIVATION_REQUEST => UrovenVazhnosti::ATTENTION,
            self::UNIT_STARTED => UrovenVazhnosti::INFO,
        };
    }

    /**
     * Все типы, доступные для управления в настройках.
     *
     * @return list<self>
     */
    public static function vse(): array
    {
        return [self::RUNNER_THREAT, self::UNIT_STARTED, self::ACCOUNT_ACTIVATION_REQUEST];
    }
}
