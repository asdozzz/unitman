<?php

declare(strict_types=1);

namespace App\Account\Migrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Детерминированный порядок набора моделей реквизитов OpenCode.
 *
 * Набор моделей — это `list<string>`, порядок значим для UI и контракта
 * read-моделей, поэтому храним явную позицию вставки. Восстановить порядок из
 * уже существующей схемы нельзя: primary key — (credential_id, model),
 * `created_at` у всего набора одинаковый (пишется одной транзакцией), а
 * физический порядок строк (ctid) меняется после `update` (снятие is_default).
 *
 * Ранее сохранённые строки бэкфиллятся детерминированно — в том порядке, в
 * котором они отдавались до появления колонки: сначала дефолт, затем по имени
 * модели. Новые записи получают позицию по порядку из агрегата.
 */
final class Version20261124110000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Позиция в наборе моделей реквизитов OpenCode (opencode_credential_model.position)';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('alter table opencode_credential_model
            add column if not exists position integer not null default 0;');

        $this->addSql('update opencode_credential_model m
            set position = s.rn - 1
            from (
                select credential_id,
                       model,
                       row_number() over (
                           partition by credential_id
                           order by is_default desc, model
                       ) as rn
                from opencode_credential_model
            ) s
            where m.credential_id = s.credential_id
              and m.model = s.model;');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('alter table opencode_credential_model drop column if exists position');
    }
}
