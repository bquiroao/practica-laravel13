CREATE TABLE IF NOT EXISTS lab_result_versions (
    version_id TEXT PRIMARY KEY,
    order_item_id TEXT NOT NULL,
    result_value TEXT NOT NULL CHECK (length(trim(result_value)) > 0),
    state TEXT NOT NULL CHECK (state IN ('pending', 'validated')),
    version_number INTEGER NOT NULL CHECK (version_number > 0),
    previous_version_id TEXT NULL REFERENCES lab_result_versions(version_id),
    correction_reason TEXT NULL,
    entered_by TEXT NOT NULL CHECK (length(trim(entered_by)) > 0),
    entered_at TEXT NOT NULL,
    UNIQUE (order_item_id, version_number),
    CHECK (
        (version_number = 1 AND previous_version_id IS NULL AND correction_reason IS NULL)
        OR
        (version_number > 1 AND previous_version_id IS NOT NULL AND length(trim(correction_reason)) > 0)
    )
);

CREATE INDEX IF NOT EXISTS idx_lab_result_versions_current
    ON lab_result_versions(order_item_id, version_number DESC);
