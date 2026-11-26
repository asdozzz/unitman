<?php

declare(strict_types=1);

namespace App\Secrets\Infra\Cli;

use App\Secrets\Business\Exception\SecretNotFound;
use App\Secrets\Business\Port\EncryptionKeyProvider;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

/**
 * Проверка обязательного ключевого материала для выбранного бэкенда.
 *
 * Печатает только имена и статус (`MISSING:`/`INVALID:`), никогда значения.
 * Пустой/некорректный ключ → ненулевой exit code (fail-closed).
 */
#[AsCommand(
    name: 'app:secrets:health',
    description: 'Checks required secret names and key formats for the configured backend (names only)',
)]
final class SecretsHealthCommand extends Command
{
    public function __construct(
        private readonly EncryptionKeyProvider $keys,
    ) {
        parent::__construct();
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $missing = [];
        $invalid = [];

        foreach ($this->keys->requiredSecretNames() as $name) {
            try {
                $this->keys->validate($name);
            } catch (SecretNotFound) {
                $missing[] = $name;
            } catch (\Throwable) {
                // Не печатаем исходное сообщение: оно может содержать детали
                // бэкенда/пути, но не значение. Достаточно имени и статуса.
                $invalid[] = $name;
            }
        }

        foreach ($missing as $name) {
            $output->writeln('MISSING: ' . $name);
        }

        foreach ($invalid as $name) {
            $output->writeln('INVALID: ' . $name);
        }

        if ($missing !== [] || $invalid !== []) {
            $output->writeln(sprintf(
                'secrets health: FAILED (%d missing, %d invalid)',
                count($missing),
                count($invalid),
            ));

            return Command::FAILURE;
        }

        $output->writeln(sprintf(
            'secrets health: OK (%d required secrets present)',
            count($this->keys->requiredSecretNames()),
        ));

        return Command::SUCCESS;
    }
}
