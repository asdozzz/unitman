<?php

declare(strict_types=1);

namespace App\Runner\Migrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Историческая миграция создания «дефолтного» раннера.
 *
 * В multi-runner архитектуре дефолтного раннера больше нет: единственный
 * источник записей `runner_state` — `POST /api/runner/register`. Поэтому
 * миграция сознательно оставлена no-op: она должна применяться на чистой БД
 * без обращения к удалённым классам (`SozdatDefoltniiRunnerUseCase`) и не
 * создавать legacy-состояние, которое могло бы притвориться привязкой.
 *
 * Номер версии сохранён: переписывать уже применённую миграцию нельзя.
 */
final class Version20240415170449 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'No-op: legacy default runner seeding removed (multi-runner)';
    }

    public function up(Schema $schema): void
    {
        // Намеренно пусто. Дефолтный раннер удалён из продукта.
    }

    public function down(Schema $schema): void
    {
        // Симметрично up(): откатывать нечего.
    }
}
