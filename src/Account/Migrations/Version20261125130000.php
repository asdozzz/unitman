<?php

declare(strict_types=1);

namespace App\Account\Migrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Статус ожидания активации для самостоятельно зарегистрированных аккаунтов.
 *
 * Существующие аккаунты активны: значение по умолчанию `false` сохраняет
 * прежнее поведение.
 */
final class Version20261125130000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Признак ожидания активации аккаунта (jwt_user.is_pending_activation)';
    }

    public function up(Schema $schema): void
    {
        $this->addSql(
            'alter table jwt_user add column if not exists is_pending_activation boolean not null default false;'
        );
    }

    public function down(Schema $schema): void
    {
        $this->addSql('alter table jwt_user drop column if exists is_pending_activation');
    }
}
