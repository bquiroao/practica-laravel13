PRAGMA foreign_keys = ON;

CREATE TABLE tenants (
    id     TEXT PRIMARY KEY,
    name   TEXT NOT NULL,
    active INTEGER NOT NULL DEFAULT 1 CHECK (active IN (0, 1))
);

CREATE TABLE users (
    id            TEXT PRIMARY KEY,
    tenant_id     TEXT NOT NULL REFERENCES tenants (id),
    email         TEXT NOT NULL,
    password_hash TEXT NOT NULL,
    active        INTEGER NOT NULL DEFAULT 1 CHECK (active IN (0, 1)),
    created_at    TEXT NOT NULL,
    UNIQUE (tenant_id, email)
);

CREATE TABLE sessions (
    id          TEXT PRIMARY KEY,
    token_hash  TEXT NOT NULL UNIQUE,
    user_id     TEXT NOT NULL REFERENCES users (id),
    tenant_id   TEXT NOT NULL REFERENCES tenants (id),
    expires_at  TEXT NOT NULL,
    revoked_at  TEXT NULL
);
