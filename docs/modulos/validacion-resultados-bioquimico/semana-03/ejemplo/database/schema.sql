PRAGMA foreign_keys = ON;
CREATE TABLE lab_result (
 id TEXT NOT NULL, hospital_id TEXT NOT NULL, order_item_id TEXT NOT NULL,
 examination_id TEXT NOT NULL, specimen_id TEXT NOT NULL, version INTEGER NOT NULL CHECK (version > 0),
 value TEXT NOT NULL, unit TEXT NOT NULL, status TEXT NOT NULL CHECK (status IN ('PENDING','VALIDATED','REJECTED')),
 PRIMARY KEY (id, hospital_id)
);
CREATE TABLE lab_result_decision (
 result_id TEXT NOT NULL, version INTEGER NOT NULL, hospital_id TEXT NOT NULL, actor_id TEXT NOT NULL,
 decision TEXT NOT NULL CHECK (decision IN ('VALIDATE','REJECT')), reason TEXT NOT NULL, decided_at TEXT NOT NULL,
 PRIMARY KEY (result_id, hospital_id, version),
 FOREIGN KEY (result_id, hospital_id) REFERENCES lab_result(id, hospital_id) ON DELETE RESTRICT
);