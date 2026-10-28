CREATE TABLE evaluations (
 id INTEGER PRIMARY KEY AUTOINCREMENT,
 demo_order_id TEXT NOT NULL,
 target TEXT NOT NULL,
 order_status TEXT NOT NULL
);
CREATE TABLE evaluation_events (
 id INTEGER PRIMARY KEY AUTOINCREMENT,
 evaluation_id INTEGER NOT NULL REFERENCES evaluations(id),
 event TEXT NOT NULL
);