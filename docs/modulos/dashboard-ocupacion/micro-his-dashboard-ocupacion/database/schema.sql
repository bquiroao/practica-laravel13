-- Versión SQLite mínima de wards, beds y bed_stays (database/migrations/hospital/..._000300) más los cortes del tablero.
CREATE TABLE IF NOT EXISTS wards (ward_id INTEGER PRIMARY KEY, code TEXT NOT NULL UNIQUE, status TEXT NOT NULL CHECK (status IN ('active', 'inactive')));
CREATE TABLE IF NOT EXISTS beds (bed_id INTEGER PRIMARY KEY, ward_id INTEGER NOT NULL REFERENCES wards(ward_id), code TEXT NOT NULL,
  status TEXT NOT NULL CHECK (status IN ('available', 'occupied', 'maintenance', 'inactive')), UNIQUE (ward_id, code));
CREATE TABLE IF NOT EXISTS bed_stays (bed_stay_id INTEGER PRIMARY KEY, bed_id INTEGER NOT NULL REFERENCES beds(bed_id), ended_at TEXT);
-- R-05 y R-07: cada corte guarda su hora y solo cifras por sala.
CREATE TABLE IF NOT EXISTS occupancy_snapshots (id INTEGER PRIMARY KEY, taken_at INTEGER NOT NULL, payload TEXT NOT NULL);
