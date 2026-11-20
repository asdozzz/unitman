<?php

declare(strict_types=1);

namespace App\OpenCode\Business\Model;

/**
 * Статус жизненного цикла workspace.
 *
 * Переходы проверяются доменной моделью, чтобы фоновая задача и use case
 * не могли молча перевести workspace в несогласованное состояние.
 */
enum StatusWorkspace: string
{
    case CREATING = 'CREATING';
    case STARTING = 'STARTING';
    case RUNNING = 'RUNNING';
    case ARCHIVING = 'ARCHIVING';
    case ARCHIVED = 'ARCHIVED';
    case DELETING = 'DELETING';
    case ERROR = 'ERROR';

    public function mozhnoPerevestiV(self $target): bool
    {
        return in_array($target, $this->dopustimyePerehody(), true);
    }

    /**
     * @throws \DomainException если значение статуса неизвестно
     */
    public static function izStroki(string $value): self
    {
        $status = self::tryFrom($value);

        if ($status === null) {
            throw new \DomainException('opencode.workspace.invalid_status');
        }

        return $status;
    }

    /**
     * @return list<self>
     */
    private function dopustimyePerehody(): array
    {
        return match ($this) {
            self::CREATING => [self::RUNNING, self::ERROR],
            self::RUNNING => [self::ARCHIVING, self::DELETING],
            self::ARCHIVING => [self::ARCHIVED, self::ERROR],
            self::ARCHIVED => [self::STARTING, self::DELETING],
            self::STARTING => [self::RUNNING, self::ERROR],
            self::ERROR => [self::STARTING, self::DELETING],
            self::DELETING => [self::DELETING],
        };
    }
}
