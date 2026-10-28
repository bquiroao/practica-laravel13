CREATE TABLE IF NOT EXISTS specimen_receptions (
    identifier VARCHAR(35) PRIMARY KEY,
    order_reference VARCHAR(100) NOT NULL,
    patient_reference VARCHAR(100) NOT NULL,
    exam_code VARCHAR(50) NOT NULL,
    specimen_type VARCHAR(20) NOT NULL,
    status VARCHAR(20) NOT NULL CHECK (status IN ('accepted', 'rejected')),
    rejection_reasons TEXT NOT NULL,
    received_at VARCHAR(40) NOT NULL
);
