CREATE TABLE lab_result (
    id VARCHAR(100) NOT NULL,
    hospital_id VARCHAR(100) NOT NULL,
    order_item_id VARCHAR(100) NOT NULL,
    examination_id VARCHAR(100) NOT NULL,
    specimen_id VARCHAR(100) NOT NULL,
    version INTEGER NOT NULL CHECK (version > 0),
    value VARCHAR(100) NOT NULL,
    unit VARCHAR(40) NOT NULL,
    status VARCHAR(20) NOT NULL CHECK (status IN ('PENDING', 'VALIDATED', 'REJECTED')),
    PRIMARY KEY (id, hospital_id)
);

CREATE TABLE lab_result_decision (
    result_id VARCHAR(100) NOT NULL,
    version INTEGER NOT NULL CHECK (version > 0),
    hospital_id VARCHAR(100) NOT NULL,
    actor_id VARCHAR(100) NOT NULL,
    decision VARCHAR(20) NOT NULL CHECK (decision IN ('VALIDATE', 'REJECT')),
    reason TEXT NOT NULL,
    decided_at VARCHAR(40) NOT NULL,
    PRIMARY KEY (result_id, version),
    FOREIGN KEY (result_id, hospital_id)
        REFERENCES lab_result (id, hospital_id)
        ON DELETE RESTRICT
);
