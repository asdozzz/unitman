-- Личные токены хранилищ (Unitman).
-- Event store агрегата LichniyToken.
CREATE TABLE IF NOT EXISTS unitman_lichniy_token_event_store
(
    id                serial  not null
        constraint unitman_lichniy_token_event_store_pk
            primary key,
    event_id          varchar not null,
    aggregate_root_id varchar not null,
    version           integer not null,
    payload           jsonb   not null,
    constraint unitman_lichniy_token_reconstitution
        unique (aggregate_root_id, version)
);

-- Read-модель проекции lichniy_token_list.
CREATE TABLE IF NOT EXISTS lichniy_token
(
    id               varchar(128) not null
        constraint lichniy_token_pk primary key,
    account_id       varchar(128) not null,
    repo_id          varchar(128) not null,
    nazvanie         varchar(255) not null,
    token_cipher     text         not null,
    token_hint       varchar(64)  not null,
    obyom_prav       varchar(32)  not null,
    proekty_s_pushom jsonb        not null default '[]',
    provereno_v      integer      not null,
    deleted          boolean      not null default false
);

CREATE INDEX IF NOT EXISTS lichniy_token_account_repo_idx ON lichniy_token (account_id, repo_id);
