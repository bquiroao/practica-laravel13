-- Micro-HIS Catálogo de pruebas de laboratorio: tabla lab_test (mismo nombre que en HOSPITAL).
-- Columnas propuestas por el Micro-HIS; se ajustarán al esquema oficial de HOSPITAL cuando esté publicado.
DROP TABLE IF EXISTS lab_test;

CREATE TABLE lab_test (
    id                uuid         PRIMARY KEY,
    tenant_id         varchar(40)  NOT NULL,
    code              varchar(7)   NOT NULL,
    version           integer      NOT NULL CHECK (version > 0),
    name              varchar(150) NOT NULL,
    section           varchar(30)  NOT NULL,
    turnaround_hours  integer      NOT NULL CHECK (turnaround_hours > 0),
    specimen_type     varchar(20)  NOT NULL,
    container         varchar(80)  NOT NULL,
    min_volume_ml     numeric(6,2) NOT NULL CHECK (min_volume_ml > 0),
    fasting_hours     integer      NOT NULL CHECK (fasting_hours >= 0),
    preparation_notes varchar(300) NOT NULL DEFAULT '',
    storage_condition varchar(20)  NOT NULL,
    stability_hours   integer      NOT NULL CHECK (stability_hours > 0),
    valid_from        date         NOT NULL,
    valid_to          date         NULL CHECK (valid_to IS NULL OR valid_to >= valid_from),
    created_by        varchar(80)  NOT NULL,
    created_at        timestamptz  NOT NULL DEFAULT now(),
    CONSTRAINT uq_lab_test_version UNIQUE (tenant_id, code, version)
);

CREATE INDEX ix_lab_test_vigencia ON lab_test (tenant_id, valid_from, valid_to);
