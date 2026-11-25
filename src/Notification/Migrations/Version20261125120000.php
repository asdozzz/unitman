<?php

declare(strict_types=1);

namespace App\Notification\Migrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Таблицы модуля уведомлений.
 */
final class Version20261125120000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Notification: уведомления, получатели, настройки, доставки и push-подписки';
    }

    public function up(Schema $schema): void
    {
        $this->addSql(
            'create table if not exists notification ('
            . 'id varchar(64) not null primary key, '
            . 'type varchar(64) not null, '
            . 'severity varchar(16) not null, '
            . 'requires_read boolean not null, '
            . 'payload jsonb not null, '
            . 'dedup_key varchar(255), '
            . 'created_at bigint not null'
            . ')'
        );

        $this->addSql(
            'create unique index if not exists notification_dedup_uq '
            . 'on notification (dedup_key) where dedup_key is not null'
        );
        $this->addSql(
            'create index if not exists notification_created_idx on notification (created_at desc)'
        );
        $this->addSql(
            'create index if not exists notification_type_idx on notification (type, created_at desc)'
        );

        $this->addSql(
            'create table if not exists notification_recipient ('
            . 'notification_id varchar(64) not null references notification(id) on delete cascade, '
            . 'account_id varchar(128) not null, '
            . 'read_at bigint, '
            . 'created_at bigint not null, '
            . 'primary key (notification_id, account_id)'
            . ')'
        );
        $this->addSql(
            'create index if not exists notification_recipient_account_idx '
            . 'on notification_recipient (account_id, read_at)'
        );

        $this->addSql(
            'create table if not exists notification_preference ('
            . 'account_id varchar(128) not null, '
            . 'type varchar(64) not null, '
            . 'enabled boolean not null default true, '
            . 'updated_at bigint not null, '
            . 'primary key (account_id, type)'
            . ')'
        );

        $this->addSql(
            'create table if not exists notification_delivery ('
            . 'notification_id varchar(64) not null references notification(id) on delete cascade, '
            . 'channel varchar(16) not null, '
            . 'state varchar(16) not null default \'pending\', '
            . 'attempts integer not null default 0, '
            . 'dispatched_at bigint, '
            . 'sent_at bigint, '
            . 'last_error text, '
            . 'primary key (notification_id, channel)'
            . ')'
        );
        $this->addSql(
            'create index if not exists notification_delivery_pending_idx '
            . 'on notification_delivery (channel, state, dispatched_at)'
        );

        $this->addSql(
            'create table if not exists notification_push_subscription ('
            . 'id varchar(64) not null primary key, '
            . 'account_id varchar(128) not null, '
            . 'endpoint text not null, '
            . 'p256dh text not null, '
            . 'auth text not null, '
            . 'locale varchar(8) not null default \'ru\', '
            . 'created_at bigint not null, '
            . 'updated_at bigint not null, '
            . 'constraint notification_push_subscription_endpoint_uq unique (endpoint)'
            . ')'
        );
        $this->addSql(
            'create index if not exists notification_push_subscription_account_idx '
            . 'on notification_push_subscription (account_id)'
        );
    }

    public function down(Schema $schema): void
    {
        $this->addSql('drop table if exists notification_push_subscription');
        $this->addSql('drop table if exists notification_delivery');
        $this->addSql('drop table if exists notification_preference');
        $this->addSql('drop table if exists notification_recipient');
        $this->addSql('drop table if exists notification');
    }
}
